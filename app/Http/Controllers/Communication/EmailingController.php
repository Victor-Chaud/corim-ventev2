<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Mail\Communication\HtmlMail;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmailingController extends Controller
{
    private const SEARCH_COLUMNS = ['date', 'source', 'projets', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget'];

    public function index(Request $request): View
    {
        $clients = DB::table('clients')->select('nom', 'email')->orderBy('nom')->get();
        $users = MessageController::prescripteurs()->get();

        $errors = [];
        $valid = null;
        if ($request->isMethod('post')) {
            $errors = MessageController::required($request, ['objet', 'message']);
            if ($errors === []) {
                $errors = $this->send($request, $users->pluck('email')->all(), $clients->pluck('email')->all());
                $valid = $errors === [] ? 'Votre message à bien été envoyé' : null;
            }
        }

        return view('communication.emailing', [
            'title' => 'Corim Promotion - Envoyer un emailing',
            'erreurs' => $errors,
            'valid' => $valid,
            'callback' => $errors === [] ? [] : $request->post(),
            'users' => $users,
            'clients' => $clients,
            'programmes' => MessageController::programmes(),
        ]);
    }

    private function send(Request $request, array $prescripteurs, array $clients): array
    {
        $method = (string) $request->input('method', '');
        $select = fn (string $key) => (string) $request->input($key, '');

        [$type, $destinataire, $emails, $error] = match ($method) {
            'prescripteur' => $select('prescripteur_select') === '0'
                ? [null, null, [], 'Veuillez choisir un prescripteur']
                : ['prescripteur', $select('prescripteur_select'), $select('prescripteur_select') === 'all' ? $prescripteurs : [$select('prescripteur_select')], null],
            'programme' => $this->programme($select('programme_select')),
            'client' => $select('client_select') === '0'
                ? [null, null, [], 'Veuillez choisir un client']
                : ['client', $select('client_select'), $select('client_select') === 'all' ? $clients : [$select('client_select')], null],
            'keywords' => $select('keyword_select') === ''
                ? [null, null, [], "Attention, aucun client n'a été sélectionné"]
                : ['autre', $select('keyword_select'), explode(';', $select('keyword_select')), null],
            default => [null, null, [], "Attention, aucun type de destinataire n'a été sélectionné"],
        };

        if ($error !== null) {
            return [$error];
        }

        $sujet = $select('objet');
        $message = $select('message');
        DB::table('emailing')->insert([
            'type' => $type,
            'destinataire' => $destinataire,
            'sujet' => $sujet,
            'message' => $message,
            'date' => now()->format('Y-m-d H:i:s'),
        ]);
        HtmlMail::queueTo($emails, $sujet, self::body($sujet, $message));

        return [];
    }

    private function programme(string $id): array
    {
        if ($id === '0') {
            return [null, null, [], 'Veuillez choisir un programme'];
        }
        $emails = DB::table('acces')->join('user', 'acces.iduser', '=', 'user.id')->where('acces.idprojet', (int) $id)->pluck('user.email')->all();
        if ($emails === []) {
            return [null, null, [], "Aucun prescripteur n'est enregistré sur ce projet"];
        }

        return ['programme', $id, $emails, null];
    }

    public static function body(string $sujet, string $message): string
    {
        return '<html><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><title>'.e($sujet).'</title><body><table width="500"><tr><td align="left"><img src="https://corim-vente.com/medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>'
            .$message
            .'</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77</td></tr></table></body></html>';
    }

    public function search(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }

        $keywords = array_values(array_filter(
            array_map(fn (string $k) => htmlspecialchars((string) $request->input($k, ''), ENT_QUOTES), ['keyword1', 'keyword2', 'keyword3']),
            fn (string $k) => $k !== '',
        ));
        if ($keywords === []) {
            return LegacyJson::respond(['errors' => 0]);
        }

        $groups = [];
        $bindings = [];
        foreach ($keywords as $keyword) {
            $groups[] = implode(' OR ', array_map(fn (string $c) => 'clients.'.$c.' LIKE ?', self::SEARCH_COLUMNS));
            array_push($bindings, ...array_fill(0, count(self::SEARCH_COLUMNS), '%'.$keyword.'%'));
        }

        $rows = DB::table('clients')->whereRaw(implode(' AND ', $groups), $bindings)->get()
            ->map(fn (object $row) => array_map(fn ($v) => $v === null ? null : (string) $v, (array) $row))
            ->all();

        return LegacyJson::respond($rows === [] ? ['errors' => 0] : ['call' => $rows, 'errors' => 1]);
    }

    public function emails(): View
    {
        $titres = DB::table('projet')->pluck('projet_titre', 'projet_titre_key');
        $emails = DB::table('docs')->orderByDesc('time')->get()->map(function (object $doc) use ($titres) {
            $parts = explode('/', str_replace(['https://corim-vente.com/medias/programmes/', 'http://corim-vente.com/medias/programmes/'], '', (string) $doc->url));
            $doc->programme = (string) ($titres[$parts[0]] ?? '') !== '' ? (string) $titres[$parts[0]] : $parts[0];
            $doc->plan = $parts[2] ?? '';

            return $doc;
        });

        return view('communication.emails', ['title' => 'Corim promotion - Emails provenant de Corim Promotion', 'emails' => $emails]);
    }
}
