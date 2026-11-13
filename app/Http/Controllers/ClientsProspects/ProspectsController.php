<?php

namespace App\Http\Controllers\ClientsProspects;

use App\Http\Controllers\Controller;
use App\Mail\ClientsProspects\ReferralMail;
use App\Models\User;
use App\Support\Access;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ProspectsController extends Controller
{
    private const SEARCH_COLUMNS = ['1' => 'prospects.nomprospect', '2' => 'projet.projet_titre', '3' => 'user.name'];

    private static function normalize(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES);
    }

    public function search(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $type = (string) $request->input('f_recherche_denonciation_type', '');
        $search = (string) $request->input('f_recherche_denonciation_search', '');
        if ($search === '') {
            return LegacyJson::respond(['errors' => 0]);
        }
        if (! isset(self::SEARCH_COLUMNS[$type])) {
            return response('', 422);
        }
        $user = $request->user();
        assert($user instanceof User);

        $rows = DB::table('prospects')
            ->join('projet', 'prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'prospects.iduser', '=', 'user.id')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('prospects.iduser', $user->id))
            ->where(self::SEARCH_COLUMNS[$type], 'like', '%'.self::normalize($search).'%')
            ->select('prospects.idprospect', 'prospects.nomprospect', 'prospects.cpprospect', 'prospects.villeprospect', 'prospects.smspromoteur', 'prospects.idprojet', 'prospects.iduser')
            ->selectRaw("DATE_FORMAT(prospects.date, '%d.%m.%Y %H:%i') AS date")
            ->addSelect('projet.projet_titre', 'user.name AS prescripteur')
            ->get();
        if ($rows->isEmpty()) {
            return LegacyJson::respond(['errors' => 2]);
        }

        return LegacyJson::respond(['errors' => 1, 'call' => $rows->map(fn ($row) => array_map(fn ($value) => (string) $value, (array) $row))->all()]);
    }

    public function add(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $user = $request->user();
        assert($user instanceof User);
        $input = fn (string $key) => (string) $request->input($key, '');
        $sender = (string) $user->id;
        $prescriber = $input('prescripteur_select');
        if ($user->isAdmin() && $prescriber !== '' && $prescriber !== '0') {
            $sender = $prescriber;
        }
        $program = $input('f_new_prospect_projet');

        if ($input('f_new_prospect_name') === '' || $input('f_new_prospect_prenom') === '' || $input('f_new_prospect_cp') === '' || $input('f_new_prospect_ville') === '' || $program === '') {
            return LegacyJson::respond(['errors' => 0]);
        }
        if (! ctype_digit($sender) || ! DB::table('user')->where('id', (int) $sender)->exists()) {
            return LegacyJson::respond(['errors' => 3]);
        }
        $cp = self::normalize($input('f_new_prospect_cp'));
        if (! ctype_digit($program) || mb_strlen($cp) > 6 || ! Access::canSeeProgram($user, (int) $program)) {
            return LegacyJson::respond(['errors' => 2]);
        }

        $sms = self::normalize($input('f_new_prospect_sms'));
        $date = now()->format('Y-m-d H:i:s');
        $prospect = [
            'nomprospect' => mb_substr(self::normalize($input('f_new_prospect_civilite')).' '.self::normalize($input('f_new_prospect_name').' '.$input('f_new_prospect_prenom')), 0, 150),
            'cpprospect' => $cp,
            'villeprospect' => mb_substr(self::normalize($input('f_new_prospect_ville')), 0, 150),
            'idprojet' => (int) $program,
            'iduser' => (int) $sender,
        ];
        if (DB::table('d_prospects')->where($prospect)->exists()) {
            return LegacyJson::respond(['errors' => 1]);
        }
        $inserted = DB::table('d_prospects')->insert($prospect + ['smspromoteur' => $sms, 'date' => $date]);
        DB::table('mp')->insert([
            'idemetteur' => (int) $sender,
            'idreceveur' => 1,
            'sujet' => self::normalize('Vous avez une dénonciation en attente'),
            'message' => $sms,
            'date' => $date,
            'lu' => 0,
            'cle' => bin2hex(random_bytes(20)),
            'parent' => 0,
        ]);
        if (! $inserted) {
            return LegacyJson::respond(['errors' => 2]);
        }
        Mail::to((string) config('mail.corim.address'))->send(new ReferralMail);

        return LegacyJson::respond(['errors' => 1]);
    }
}
