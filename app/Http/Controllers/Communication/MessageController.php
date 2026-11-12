<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Mail\Communication\HtmlMail;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MessageController extends Controller
{
    public static function required(Request $request, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if ((string) $request->input($field, '') === '') {
                $errors[] = 'Le champ '.$field.' est requis <br />';
            }
        }

        return $errors;
    }

    public static function normalize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    public function repondre(Request $request, string $cle): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $original = MessagerieController::readable($user, $cle);
        abort_if($original === null, 404);

        $errors = [];
        $valid = null;
        $callback = [];
        if ($request->isMethod('post')) {
            $errors = self::required($request, ['objet', 'message', 'destinataire']);
            if ($errors === []) {
                MessagerieController::send($user->id, (int) $original->idemetteur, (string) $original->sujet, self::normalize((string) $request->input('message')), (int) $original->id);
                $valid = 'Votre message à bien été envoyé';
            } else {
                $callback = $request->post();
            }
        }

        return view('communication.message', [
            'title' => 'Corim Promotion - Répondre à un message',
            'erreurs' => $errors,
            'valid' => $valid,
            'callback' => $callback,
            'message' => $original,
            'users' => null,
        ]);
    }

    public function envoyer(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $users = self::prescripteurs()->get();
        $clients = DB::table('clients')->select('nom', 'email')->orderBy('nom')->get();

        $errors = [];
        $valid = null;
        if ($request->isMethod('post')) {
            $errors = self::required($request, ['objet', 'message']);
            $method = (string) $request->input('method', '');
            $sujet = (string) $request->input('objet', '');
            $message = (string) $request->input('message', '');
            if ($errors === [] && $method === '') {
                $errors[] = 'Veuillez choisir un destinataire';
            } elseif ($errors === [] && $method === 'prescripteur') {
                $select = (string) $request->input('prescripteur_select', '');
                if ($select === '0') {
                    $errors[] = 'Veuillez choisir un prescripteur';
                } else {
                    $ids = $select === 'all' ? $users->pluck('id')->all() : [(int) $select];
                    foreach ($ids as $id) {
                        MessagerieController::send($user->id, (int) $id, self::normalize($sujet), self::normalize($message));
                    }
                    $valid = 'Votre message à bien été envoyé';
                }
            } elseif ($errors === [] && $method === 'client') {
                $select = (string) $request->input('client_select', '');
                if ($select === '0') {
                    $errors[] = 'Veuillez choisir un client';
                } else {
                    $emails = $select === 'all' ? $clients->pluck('email')->all() : [$select];
                    HtmlMail::queueTo($emails, $sujet, $message);
                    $valid = 'Votre message à bien été envoyé';
                }
            } elseif ($errors === [] && $method === 'programme') {
                $select = (string) $request->input('programme_select', '');
                if ($select === '0') {
                    $errors[] = 'Veuillez choisir un programme';
                } else {
                    $ids = self::prescripteurs()->whereIn('user.id', DB::table('acces')->select('iduser')->where('idprojet', (int) $select))->pluck('user.id');
                    foreach ($ids as $id) {
                        MessagerieController::send($user->id, (int) $id, self::normalize($sujet), self::normalize($message));
                    }
                    $valid = 'Votre message à bien été envoyé';
                }
            }
        }

        return view('communication.message', [
            'title' => 'Corim Promotion - Envoyer un nouveau message',
            'erreurs' => $errors,
            'valid' => $valid,
            'callback' => $errors === [] ? [] : $request->post(),
            'message' => null,
            'users' => $users,
            'clients' => $clients,
            'programmes' => self::programmes(),
        ]);
    }

    public static function prescripteurs(): Builder
    {
        return DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')
            ->select('user.*', 'privileges.acces')->where('privileges.acces', 0)->orderBy('name');
    }

    public static function programmes(): Collection
    {
        return DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get();
    }
}
