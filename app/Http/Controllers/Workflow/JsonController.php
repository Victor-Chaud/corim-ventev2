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

    private const REQUEST_TYPES = [
        'option' => [
            'field' => 'id_option', 'table' => 'd_options', 'status' => 'opt',
            'subject' => "Vous avez une demande d'option en attente",
            'phrase' => "Vous avez reçu un nouveau message concernant une demande d'option",
        ],
        'reservation' => [
            'field' => 'id_reserver', 'table' => 'd_reservations', 'status' => 'res',
            'subject' => 'Vous avez une demande de réservation en attente',
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
        return $this->createRequest($request, self::REQUEST_TYPES['option']);
    }

    public function addReservation(Request $request): Response
    {
        return $this->createRequest($request, self::REQUEST_TYPES['reservation']);
    }

    public function acceptAddOption(Request $request): Response
    {
        return $this->approve($request, 'f_confirm_ask_option', 'd_options', 'options', "Votre demande d'option a été validée", 0);
    }

    public function acceptAddReservation(Request $request): Response
    {
        return $this->approve($request, 'f_confirm_ask_resa', 'd_reservations', 'reservations', 'Votre demande de réservation a été validée', 3);
    }

    public function rejectAddOption(Request $request): Response
    {
        return $this->reject($request, 'f_refuser_ask_option', 'd_options', 'r_options', "Votre demande d'option a été refusée");
    }

    public function rejectAddReservation(Request $request): Response
    {
        return $this->reject($request, 'f_refuser_ask_resa', 'd_reservations', 'r_reservations', 'Votre demande de réservation a été refusée');
    }

    public function acceptAddReferral(Request $request): Response
    {
        return $this->referral($request, 'f_confirm_ask_denonciation', 'prospects', 'Votre demande de dénonciation a été validée', 3);
    }

    public function rejectAddReferral(Request $request): Response
    {
        return $this->referral($request, 'f_refuser_ask_denonciation', 'r_prospects', 'Votre demande de dénonciation a été refusée', 0);
    }

    public function convertToReservation(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, 'f_passer_en_resa');
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->fill($sms, $user, $lot, DB::table('options')->where('iduser', (int) $user)->where('idlot', (int) $lot)->where('statut', 1)->value('nom'));

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

            return $this->setStatus($lot, 'res');
        });

        return $this->conclude($ok, $user, $lot, $sms, 'Votre option a été passée en réservation');
    }

    public function cancelOption(Request $request): Response
    {
        return $this->cancel($request, 'f_annuler_option', 'options', 'Votre option a été annulée');
    }

    public function cancelReservation(Request $request): Response
    {
        return $this->cancel($request, 'f_annuler_resa', 'reservations', 'Votre réservation a été annulée');
    }

    public function convertToDeed(Request $request): Response
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
        $sms = $this->fill($sms, (string) ($client->iduser ?? ''), $lot, $client->nom ?? null);

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

        return $this->conclude($client !== null, (string) $client, $lot, $sms, 'Votre dossier a été acté');
    }

    public function cancelSale(Request $request): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $seller = $this->input($request, 'f_annuler_la_vente_vendeur');
        $lot = $this->input($request, 'f_annuler_la_vente_idlot');
        $sms = $this->input($request, 'f_annuler_la_vente_sms');
        if ($lot === '' || $seller === '') {
            return $this->errors(0);
        }
        $sms = $this->fill($sms, $seller, $lot, DB::table('appartements')->where('id', (int) $lot)->value('client'));

        $ok = DB::transaction(function () use ($lot, $seller) {
            $updated = DB::table('appartements')->where('id', (int) $lot)->where('vendeur', (int) $seller)
                ->update(['statut' => '', 'client' => '', 'vendeur' => 0]);
            if ($updated !== 1) {
                return false;
            }
            DB::table('ventes')->where('idlot', (int) $lot)->where('iduser', (int) $seller)->where('statut', 1)->update(['statut' => 0]);

            return true;
        });

        return $this->conclude($ok, $seller, $lot, $sms, 'La vente a été annulée');
    }

    public function deleteReferral(Request $request): Response
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
        $target = self::DATES[$this->input($request, 'type')] ?? null;
        if ($target === null || ! ctype_digit($id) || ! $this->isDateTime($time)) {
            return $this->errors(0);
        }
        [$table, $column, $key, $active] = $target;
        $count = DB::table($table)->where($key, (int) $id)->when($active, fn ($q) => $q->where('statut', 1))->update([$column => $time]);

        return $this->errors($count === 1 ? 1 : 0);
    }

    private function createRequest(Request $request, array $pendingRequest): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errors(0);
        }
        $salutation = $this->input($request, 'civilite');
        $name = $this->input($request, 'name');
        $firstName = $this->input($request, 'prenom');
        $address = $this->input($request, 'adr');
        $cp = $this->input($request, 'cp');
        $message = $this->input($request, 'message');
        $idlot = $this->input($request, $pendingRequest['field']);
        $programId = $this->input($request, 'id_projet');
        $sender = $user->id;
        $choice = $this->input($request, 'prescripteur_select');
        if ($user->isAdmin() && $choice !== '' && $choice !== '0') {
            if (! ctype_digit($choice)) {
                return $this->errors(0);
            }
            $sender = (int) $choice;
        }
        if (! DB::table('user')->where('id', $sender)->exists()) {
            return $this->errors(0);
        }
        if (in_array('', [$firstName, $address, $message, $idlot, $programId], true)) {
            return $this->errors(0);
        }
        if (! ctype_digit($idlot) || ! ctype_digit($programId) || ! Access::canSeeLot($user, (int) $idlot)) {
            return $this->errors(2);
        }

        $ok = DB::transaction(function () use ($pendingRequest, $salutation, $name, $firstName, $address, $cp, $message, $idlot, $programId, $sender) {
            if (DB::table($pendingRequest['table'])->where('iddlot', (int) $idlot)->lockForUpdate()->exists()) {
                return false;
            }
            $updated = DB::table('appartements')->where('id', (int) $idlot)->where('lot', $programId)
                ->where(fn ($q) => $q->where('statut', '')->orWhereNull('statut'))
                ->update(['statut' => $pendingRequest['status']]);
            if ($updated !== 1) {
                return false;
            }
            $now = now()->format('Y-m-d H:i:s');
            DB::table($pendingRequest['table'])->insert([
                'nom' => self::normalize($salutation.' '.$name.' '.$firstName),
                'iduser' => $sender,
                'adresse' => self::normalize($address.' '.$cp),
                'message' => self::normalize($message),
                'iddlot' => (int) $idlot,
                'idprojet' => (int) $programId,
                'date' => $now,
            ]);
            DB::table('mp')->insert([
                'idemetteur' => $sender, 'idreceveur' => 1, 'sujet' => self::normalize($pendingRequest['subject']),
                'message' => self::normalize($message), 'date' => $now, 'lu' => 0,
                'cle' => bin2hex(random_bytes(20)), 'parent' => 0,
            ]);

            return true;
        });
        if (! $ok) {
            return $this->errors(2);
        }
        Mail::to((string) config('mail.corim.address'))->send(WorkflowMail::corim($pendingRequest['subject'], $pendingRequest['phrase']));

        return $this->errors(1);
    }

    private function approve(Request $request, string $prefix, string $from, string $to, string $subject, int $emptyCode): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors($emptyCode);
        }
        $sms = $this->fill($sms, $user, $lot, DB::table($from)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->value('nom'));

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

        return $this->conclude($ok, $user, $lot, $sms, $subject);
    }

    private function reject(Request $request, string $prefix, string $table, string $rejection, string $subject): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->fill($sms, $user, $lot, DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->value('nom'));

        $ok = DB::transaction(function () use ($table, $rejection, $lot, $user, $sms) {
            $row = DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->lockForUpdate()->first();
            if ($row === null) {
                return false;
            }
            DB::table($rejection)->insert([
                'iduser' => $row->iduser, 'nom' => $row->nom, 'adresse' => $row->adresse,
                'message' => $row->message.self::SEPARATOR.self::normalize($sms),
                'idlot' => $row->iddlot, 'idprojet' => $row->idprojet,
                'numero' => (string) DB::table('appartements')->where('id', $row->iddlot)->value('numero'),
                'date' => $row->date, 'date_refus' => now()->format('Y-m-d H:i:s'),
            ]);

            return DB::table($table)->where('iduser', (int) $user)->where('iddlot', (int) $lot)->delete() >= 1
                && $this->setStatus($lot, '');
        });

        return $this->conclude($ok, $user, $lot, $sms, $subject);
    }

    private function cancel(Request $request, string $prefix, string $table, string $subject): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$lot, $user, $sms] = $this->triplet($request, $prefix);
        if ($lot === '' || $user === '') {
            return $this->errors(0);
        }
        $sms = $this->fill($sms, $user, $lot, DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->value('nom'));

        $ok = DB::transaction(fn () => DB::table($table)->where('idlot', (int) $lot)->where('statut', 1)->update(['statut' => 0]) >= 1
            && $this->setStatus($lot, ''));

        return $this->conclude($ok, $user, $lot, $sms, $subject);
    }

    private function referral(Request $request, string $prefix, string $to, string $subject, int $emptyCode): Response
    {
        if (! $this->posted($request)) {
            return response('');
        }
        [$id, $user, $sms] = $this->triplet($request, $prefix);
        if ($id === '' || $user === '') {
            return $this->errors($emptyCode);
        }
        $rejection = $to === 'r_prospects';
        $pendingRequest = DB::table('d_prospects')->where('idprospect', (int) $id)->first(['nomprospect', 'idprojet', 'iduser']);
        $sms = $this->fill($sms, (string) ($pendingRequest->iduser ?? $user), null, $pendingRequest->nomprospect ?? null, (int) ($pendingRequest->idprojet ?? 0));

        $prospect = DB::transaction(function () use ($id, $user, $sms, $to, $rejection) {
            $row = DB::table('d_prospects')->join('projet', 'd_prospects.idprojet', '=', 'projet.projet_id')
                ->where('d_prospects.idprospect', (int) $id)->when(! $rejection, fn ($q) => $q->where('d_prospects.iduser', (int) $user))
                ->lockForUpdate()->first(['d_prospects.*', 'projet.projet_titre']);
            if ($row === null) {
                return null;
            }
            $copy = [
                'nomprospect' => $row->nomprospect, 'cpprospect' => $row->cpprospect, 'villeprospect' => $row->villeprospect,
                'smspromoteur' => $row->smspromoteur.self::SEPARATOR.self::normalize($sms),
                'idprojet' => $row->idprojet, 'iduser' => $row->iduser, 'date' => $row->date,
            ];
            if ($rejection) {
                DB::table('r_prospects')->where('idprospect', $row->idprospect)->delete();
                $copy = ['idprospect' => $row->idprospect] + $copy + ['date_refus' => now()->format('Y-m-d H:i:s')];
            }
            DB::table($to)->insert($copy);
            DB::table('d_prospects')->where('idprospect', $row->idprospect)->delete();

            return $row;
        });
        if ($prospect === null) {
            return $this->errors(0);
        }
        $this->send($user, WorkflowMail::prescriber($subject, $sms, (string) $prospect->projet_titre, 'Client', (string) $prospect->nomprospect));

        return $this->errors(1);
    }

    private function conclude(bool $ok, string $user, string $lot, string $sms, string $subject): Response
    {
        if (! $ok) {
            return $this->errors(0);
        }
        $infos = DB::table('appartements')->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->where('appartements.id', (int) $lot)->first(['projet.projet_titre', 'appartements.numero']);
        $this->send($user, WorkflowMail::prescriber($subject, $sms, (string) ($infos->projet_titre ?? ''), 'Référence lot', (string) ($infos->numero ?? '')));

        return $this->errors(1);
    }

    private function send(string $user, WorkflowMail $mail): void
    {
        $email = (string) DB::table('user')->where('id', (int) $user)->value('email');
        if ($email !== '') {
            Mail::to($email)->send($mail);
        }
    }

    private function fill(string $sms, string $user, ?string $lot, mixed $buyer, ?int $program = null): string
    {
        if (! str_contains($sms, '{')) {
            return $sms;
        }
        $apartment = $lot !== null ? DB::table('appartements')->where('id', (int) $lot)->first(['numero', 'lot']) : null;
        $program ??= (int) ($apartment->lot ?? 0);
        $text = fn (mixed $value) => html_entity_decode((string) $value, ENT_QUOTES);

        return strtr($sms, [
            '{prescripteur}' => $text(DB::table('user')->where('id', (int) $user)->value('name')),
            '{programme}' => $text(DB::table('projet')->where('projet_id', $program)->value('projet_titre')),
            '{lot}' => $text($apartment->numero ?? ''),
            '{acquereur}' => $text($buyer),
        ]);
    }

    private function setStatus(string $lot, string $status): bool
    {
        return DB::table('appartements')->where('id', (int) $lot)->update(['statut' => $status]) === 1;
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
