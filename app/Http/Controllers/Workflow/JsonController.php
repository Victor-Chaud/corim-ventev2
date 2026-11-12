<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Mail\Workflow\WorkflowMail;
use App\Models\User;
use App\Support\Access;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class JsonController extends Controller
{
    public const SEPARATOR = '<div class="separator"></div>';

    private const DEMANDES = [
        'option' => [
            'champ' => 'id_option', 'table' => 'd_options', 'statut' => 'opt',
            'sujet' => "Vous avez une demande d'option en attente",
            'phrase' => "Vous avez reçu un nouveau message concernant une demande d'option",
        ],
        'reservation' => [
            'champ' => 'id_reserver', 'table' => 'd_reservations', 'statut' => 'res',
            'sujet' => 'Vous avez une demande de réservation en attente',
            'phrase' => 'Vous avez reçu un nouveau message concernant une demande de réservation',
        ],
    ];

    private const DATES = [
        'askoption' => ['d_options', 'date', 'iddlot', false],
        'askresa' => ['d_reservations', 'date', 'iddlot', false],
        'askden' => ['d_prospects', 'date', 'idprospect', false],
        'option' => ['options', 'date', 'idlot', true],
        'resa' => ['reservations', 'date', 'idlot', true],
        'den' => ['prospects', 'date', 'idprospect', false],
        'vente' => ['ventes', 'date', 'idlot', true],
        'venteresa' => ['ventes', 'date_resa', 'idlot', true],
    ];

    public function addOption(Request $request): Response
    {
        return $this->demande($request, self::DEMANDES['option']);
    }

    public function addReservation(Request $request): Response
    {
        return $this->demande($request, self::DEMANDES['reservation']);
    }

    public function acceptAddOption(Request $request): Response
    {
        return $this->valider($request, 'f_confirm_ask_option', 'd_options', 'options', "Votre demande d'option a été validée", 0);
    }

    public function acceptAddResa(Request $request): Response
    {
        return $this->valider($request, 'f_confirm_ask_resa', 'd_reservations', 'reservations', 'Votre demande de réservation a été validée', 3);
    }

    public function rejetAddOption(Request $request): Response
    {
        return $this->rejeter($request, 'f_refuser_ask_option', 'd_options', 'r_options', "Votre demande d'option a été refusée");
    }

    public function rejetAddResa(Request $request): Response
    {
        return $this->rejeter($request, 'f_refuser_ask_resa', 'd_reservations', 'r_reservations', 'Votre demande de réservation a été refusée');
    }

    public function acceptAddDenonciation(Request $request): Response
    {
        return $this->denonciation($request, 'f_confirm_ask_denonciation', 'prospects', 'Votre demande de dénonciation a été validée', 3);
    }

    public function rejetAddDenonciation(Request $request): Response
    {
        return $this->denonciation($request, 'f_refuser_ask_denonciation', 'r_prospects', 'Votre demande de dénonciation a été refusée', 0);
    }

    public function passerEnResa(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, 'f_passer_en_resa');
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->remplir($sms, $user, $lot, DB::table('options')->where('iduser', (int) $user)->where('idlot', (int) $lot)->where('statut', 1)->value('nom'));

        $ok = DB::transaction(function () use ($lot, $user) {
            $option = DB::table('options')->where('iduser', (int) $user)->where('idlot', (int) $lot)->where('statut', 1)->lockForUpdate()->get();
            if ($option->count() !== 1) {
                return false;
            }
            $row = $option->first();
            DB::table('reservations')->insert([
                'iduser' => $row->iduser, 'nom' => $row->nom, 'adresse' => $row->adresse, 'message' => $row->message,
                'idlot' => $row->idlot, 'idprojet' => $row->idprojet, 'date' => $row->date, 'statut' => $row->statut,
            ]);
            DB::table('options')->where('iduser', (int) $user)->where('idlot', (int) $lot)->where('statut', 1)->delete();

            return $this->setStatut($lot, 'res');
        });

        return $this->conclure($ok, $user, $lot, $sms, 'Votre option a été passée en réservation');
    }

    public function annulerOption(Request $request): Response
    {
        return $this->annuler($request, 'f_annuler_option', 'options', 'Votre option a été annulée');
    }

    public function annulerResa(Request $request): Response
    {
        return $this->annuler($request, 'f_annuler_resa', 'reservations', 'Votre réservation a été annulée');
    }

    public function passerEnActer(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $type = $this->input($request, 'passer_en_act_type');
        $lot = $this->input($request, 'passer_en_act_lot');
        $sms = $this->input($request, 'f_passer_en_act_sms');
        $table = ['option' => 'options', 'resa' => 'reservations'][$type] ?? null;
        if ($lot === '' || $table === null) {
            return $this->errors(0);
        }
        $client = DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->first(['iduser', 'nom']);
        $sms = $this->remplir($sms, (string) ($client->iduser ?? ''), $lot, $client->nom ?? null);

        $client = DB::transaction(function () use ($table, $lot) {
            $rows = DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->lockForUpdate()->get();
            $row = $rows->first();
            if ($rows->count() !== 1 || $row === null) {
                return null;
            }
            $updated = DB::table('appartements')->where('id', (int) $lot)->update(['statut' => 'act', 'client' => $row->nom, 'vendeur' => $row->iduser]);
            if ($updated !== 1) {
                return null;
            }
            DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->delete();
            DB::table('ventes')->insert([
                'iduser' => $row->iduser, 'idlot' => (int) $lot, 'date' => now()->format('Y-m-d H:i:s'), 'statut' => 1, 'date_resa' => $row->date,
            ]);

            return (string) $row->iduser;
        });

        return $this->conclure($client !== null, (string) $client, $lot, $sms, 'Votre dossier a été acté');
    }

    public function annulerLaVente(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $vendeur = $this->input($request, 'f_annuler_la_vente_vendeur');
        $lot = $this->input($request, 'f_annuler_la_vente_idlot');
        $sms = $this->input($request, 'f_annuler_la_vente_sms');
        if ($lot === '' || $vendeur === '') {
            return $this->errors(0);
        }
        $sms = $this->remplir($sms, $vendeur, $lot, DB::table('appartements')->where('id', (int) $lot)->value('client'));

        $ok = DB::transaction(function () use ($lot, $vendeur) {
            $updated = DB::table('appartements')->where('id', (int) $lot)->where('vendeur', (int) $vendeur)
                ->update(['statut' => '', 'client' => '', 'vendeur' => 0]);
            if ($updated !== 1) {
                return false;
            }
            DB::table('ventes')->where('idlot', (int) $lot)->where('iduser', (int) $vendeur)->where('statut', 1)->update(['statut' => 0]);

            return true;
        });

        return $this->conclure($ok, $vendeur, $lot, $sms, 'La vente a été annulée');
    }

    public function deleteDenonciation(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $id = $this->input($request, 'denonciation');

        return $this->errors(ctype_digit($id) && DB::table('prospects')->where('idprospect', (int) $id)->delete() >= 1 ? 1 : 0);
    }

    public function updateDate(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $id = $this->input($request, 'idlot');
        $time = $this->input($request, 'time');
        $cible = self::DATES[$this->input($request, 'type')] ?? null;
        if ($cible === null || ! ctype_digit($id) || ! $this->isDateTime($time)) {
            return $this->errors(0);
        }
        [$table, $colonne, $cle, $actif] = $cible;
        $count = DB::table($table)->where($cle, (int) $id)->when($actif, fn ($q) => $q->where('statut', 1))->update([$colonne => $time]);

        return $this->errors($count === 1 ? 1 : 0);
    }

    private function demande(Request $request, array $demande): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errors(0);
        }
        $civilite = $this->input($request, 'civilite');
        $name = $this->input($request, 'name');
        $prenom = $this->input($request, 'prenom');
        $adr = $this->input($request, 'adr');
        $cp = $this->input($request, 'cp');
        $message = $this->input($request, 'message');
        $idlot = $this->input($request, $demande['champ']);
        $idprojet = $this->input($request, 'id_projet');
        $sender = $user->id;
        $choix = $this->input($request, 'prescripteur_select');
        if ($user->isAdmin() && $choix !== '' && $choix !== '0') {
            if (! ctype_digit($choix)) {
                return $this->errors(0);
            }
            $sender = (int) $choix;
        }
        if (! DB::table('user')->where('id', $sender)->exists()) {
            return $this->errors(0);
        }
        if (in_array('', [$prenom, $adr, $message, $idlot, $idprojet], true)) {
            return $this->errors(0);
        }
        if (! ctype_digit($idlot) || ! ctype_digit($idprojet) || ! Access::canSeeLot($user, (int) $idlot)) {
            return $this->errors(2);
        }

        $ok = DB::transaction(function () use ($demande, $civilite, $name, $prenom, $adr, $cp, $message, $idlot, $idprojet, $sender) {
            if (DB::table($demande['table'])->where('iddlot', (int) $idlot)->lockForUpdate()->exists()) {
                return false;
            }
            $updated = DB::table('appartements')->where('id', (int) $idlot)->where('lot', $idprojet)
                ->where(fn ($q) => $q->where('statut', '')->orWhereNull('statut'))
                ->update(['statut' => $demande['statut']]);
            if ($updated !== 1) {
                return false;
            }
            $now = now()->format('Y-m-d H:i:s');
            DB::table($demande['table'])->insert([
                'nom' => self::normalize($civilite.' '.$name.' '.$prenom),
                'iduser' => $sender,
                'adresse' => self::normalize($adr.' '.$cp),
                'message' => self::normalize($message),
                'iddlot' => (int) $idlot,
                'idprojet' => (int) $idprojet,
                'date' => $now,
            ]);
            DB::table('mp')->insert([
                'idemetteur' => $sender, 'idreceveur' => 1, 'sujet' => self::normalize($demande['sujet']),
                'message' => self::normalize($message), 'date' => $now, 'lu' => 0,
                'cle' => bin2hex(random_bytes(20)), 'parent' => 0,
            ]);

            return true;
        });
        if (! $ok) {
            return $this->errors(2);
        }
        Mail::to((string) config('mail.corim.address'))->send(WorkflowMail::corim($demande['sujet'], $demande['phrase']));

        return $this->errors(1);
    }

    private function valider(Request $request, string $prefix, string $from, string $to, string $sujet, int $vide): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors($vide);
        }
        $sms = $this->remplir($sms, $user, $lot, DB::table($from)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->value('nom'));

        $ok = DB::transaction(function () use ($from, $to, $lot, $user, $sms) {
            $row = DB::table($from)->join('projet', "$from.idprojet", '=', 'projet.projet_id')
                ->where("$from.iduser", (int) $user)->where("$from.iddlot", (int) $lot)->lockForUpdate()->first(["$from.*"]);
            if ($row === null) {
                return false;
            }
            DB::table($to)->insert([
                'iduser' => $row->iduser, 'nom' => $row->nom, 'adresse' => $row->adresse,
                'message' => $row->message.self::SEPARATOR.self::normalize($sms),
                'idlot' => $row->iddlot, 'idprojet' => $row->idprojet, 'date' => $row->date,
            ]);

            return DB::table($from)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->delete() === 1;
        });

        return $this->conclure($ok, $user, $lot, $sms, $sujet);
    }

    private function rejeter(Request $request, string $prefix, string $table, string $refus, string $sujet): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->remplir($sms, $user, $lot, DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->value('nom'));

        $ok = DB::transaction(function () use ($table, $refus, $lot, $user, $sms) {
            $row = DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->lockForUpdate()->first();
            if ($row === null) {
                return false;
            }
            DB::table($refus)->insert([
                'iduser' => $row->iduser, 'nom' => $row->nom, 'adresse' => $row->adresse,
                'message' => $row->message.self::SEPARATOR.self::normalize($sms),
                'idlot' => $row->iddlot, 'idprojet' => $row->idprojet,
                'numero' => (string) DB::table('appartements')->where('id', $row->iddlot)->value('numero'),
                'date' => $row->date, 'date_refus' => now()->format('Y-m-d H:i:s'),
            ]);

            return DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->delete() >= 1
                && $this->setStatut($lot, '');
        });

        return $this->conclure($ok, $user, $lot, $sms, $sujet);
    }

    private function annuler(Request $request, string $prefix, string $table, string $sujet): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->remplir($sms, $user, $lot, DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->value('nom'));

        $ok = DB::transaction(fn () => DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->update(['statut' => 0]) >= 1
            && $this->setStatut($lot, ''));

        return $this->conclure($ok, $user, $lot, $sms, $sujet);
    }

    private function denonciation(Request $request, string $prefix, string $to, string $sujet, int $vide): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$id, $user, $sms] = $this->triplet($request, $prefix);
        if ($id === '' || $user === '') {
            return $this->errors($vide);
        }
        $refus = $to === 'r_prospects';
        $demande = DB::table('d_prospects')->where('idprospect', (int) $id)->first(['nomprospect', 'idprojet', 'iduser']);
        $sms = $this->remplir($sms, (string) ($demande->iduser ?? $user), null, $demande->nomprospect ?? null, (int) ($demande->idprojet ?? 0));

        $prospect = DB::transaction(function () use ($id, $user, $sms, $to, $refus) {
            $row = DB::table('d_prospects')->join('projet', 'd_prospects.idprojet', '=', 'projet.projet_id')
                ->where('d_prospects.idprospect', (int) $id)->when(! $refus, fn ($q) => $q->where('d_prospects.iduser', (int) $user))
                ->lockForUpdate()->first(['d_prospects.*', 'projet.projet_titre']);
            if ($row === null) {
                return null;
            }
            $copie = [
                'nomprospect' => $row->nomprospect, 'cpprospect' => $row->cpprospect, 'villeprospect' => $row->villeprospect,
                'smspromoteur' => $row->smspromoteur.self::SEPARATOR.self::normalize($sms),
                'idprojet' => $row->idprojet, 'iduser' => $row->iduser, 'date' => $row->date,
            ];
            if ($refus) {
                DB::table('r_prospects')->where('idprospect', $row->idprospect)->delete();
                $copie = ['idprospect' => $row->idprospect] + $copie + ['date_refus' => now()->format('Y-m-d H:i:s')];
            }
            DB::table($to)->insert($copie);
            DB::table('d_prospects')->where('idprospect', $row->idprospect)->delete();

            return $row;
        });
        if ($prospect === null) {
            return $this->errors(0);
        }
        $this->envoyer($user, WorkflowMail::prescripteur($sujet, $sms, (string) $prospect->projet_titre, 'Client', (string) $prospect->nomprospect));

        return $this->errors(1);
    }

    private function conclure(bool $ok, string $user, string $lot, string $sms, string $sujet): Response
    {
        if (! $ok) {
            return $this->errors(0);
        }
        $infos = DB::table('appartements')->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->where('appartements.id', (int) $lot)->first(['projet.projet_titre', 'appartements.numero']);
        $this->envoyer($user, WorkflowMail::prescripteur($sujet, $sms, (string) ($infos->projet_titre ?? ''), 'Référence lot', (string) ($infos->numero ?? '')));

        return $this->errors(1);
    }

    private function envoyer(string $user, WorkflowMail $mail): void
    {
        $email = (string) DB::table('user')->where('id', (int) $user)->value('email');
        if ($email !== '') {
            Mail::to($email)->send($mail);
        }
    }

    private function remplir(string $sms, string $user, ?string $lot, mixed $acquereur, ?int $projet = null): string
    {
        if (! str_contains($sms, '{')) {
            return $sms;
        }
        $appartement = $lot !== null ? DB::table('appartements')->where('id', (int) $lot)->first(['numero', 'lot']) : null;
        $projet ??= (int) ($appartement->lot ?? 0);
        $texte = fn (mixed $value) => html_entity_decode((string) $value, ENT_QUOTES);

        return strtr($sms, [
            '{prescripteur}' => $texte(DB::table('user')->where('id', (int) $user)->value('name')),
            '{programme}' => $texte(DB::table('projet')->where('projet_id', $projet)->value('projet_titre')),
            '{lot}' => $texte($appartement->numero ?? ''),
            '{acquereur}' => $texte($acquereur),
        ]);
    }

    private function setStatut(string $lot, string $statut): bool
    {
        return DB::table('appartements')->where('id', (int) $lot)->update(['statut' => $statut]) === 1;
    }

    private function triplet(Request $request, string $prefix): array
    {
        return [$this->input($request, $prefix.'_lot'), $this->input($request, $prefix.'_user'), $this->input($request, $prefix.'_sms')];
    }

    private function isDateTime(string $time): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $time);

        return $date !== false && $date->format('Y-m-d H:i:s') === $time;
    }

    private function posted(Request $request): bool
    {
        return $request->isMethod('post') && count(array_diff_key($request->request->all(), ['_token' => true])) > 0;
    }

    private function input(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function errors(int $code): Response
    {
        return LegacyJson::respond(['errors' => $code]);
    }

    public static function normalize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
