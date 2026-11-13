<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
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

    public function reply(Request $request, string $key): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $original = MessagingController::readable($user, $key);
        abort_if($original === null, 404);

        $errors = [];
        $valid = null;
        $callback = [];
        if ($request->isMethod('post')) {
            $errors = self::required($request, ['objet', 'message', 'destinataire']);
            if ($errors === []) {
                MessagingController::send($user->id, (int) $original->idemetteur, (string) $original->sujet, self::normalize((string) $request->input('message')), (int) $original->id);
                $valid = 'Votre message à bien été envoyé';
            } else {
                $callback = $request->post();
            }
        }

        return view('communication.message', [
            'title' => 'Corim Promotion - Répondre à un message',
            'formErrors' => $errors,
            'valid' => $valid,
            'callback' => $callback,
            'message' => $original,
            'users' => null,
        ]);
    }

    public function send(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $users = self::prescribers()->get();

        $errors = [];
        $valid = null;
        if ($request->isMethod('post')) {
            $errors = self::required($request, ['objet', 'message']);
            $method = (string) $request->input('method', '');
            $subject = (string) $request->input('objet', '');
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
                        MessagingController::send($user->id, (int) $id, self::normalize($subject), self::normalize($message));
                    }
                    $valid = 'Votre message à bien été envoyé';
                }
            } elseif ($errors === [] && $method === 'programme') {
                $select = (string) $request->input('programme_select', '');
                if ($select === '0') {
                    $errors[] = 'Veuillez choisir un programme';
                } else {
                    $ids = self::prescribers()->whereIn('user.id', DB::table('acces')->select('iduser')->where('idprojet', (int) $select))->pluck('user.id');
                    foreach ($ids as $id) {
                        MessagingController::send($user->id, (int) $id, self::normalize($subject), self::normalize($message));
                    }
                    $valid = 'Votre message à bien été envoyé';
                }
            }
        }

        return view('communication.message', [
            'title' => 'Corim Promotion - Envoyer un nouveau message',
            'formErrors' => $errors,
            'valid' => $valid,
            'callback' => $errors === [] ? [] : $request->post(),
            'message' => null,
            'users' => $users,
            'programs' => self::programs(),
        ]);
    }

    public static function prescribers(): Builder
    {
        return DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')
            ->select('user.*', 'privileges.acces')->where('privileges.acces', 0)->orderBy('name');
    }

    public static function programs(): Collection
    {
        return DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get();
    }
}
