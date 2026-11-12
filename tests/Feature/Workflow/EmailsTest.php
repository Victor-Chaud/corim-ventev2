<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(fn () => legacyMailBase());

dataset('emails', [
    'E1' => ['AddOption', null, CORIM_TO, "Vous avez une demande d'option en attente", "Vous avez reçu un nouveau message concernant une demande d'option", null],
    'E2' => ['AddReservation', null, CORIM_TO, 'Vous avez une demande de réservation en attente', 'Vous avez reçu un nouveau message concernant une demande de réservation', null],
    'E3' => ['AddProspect', null, CORIM_TO, 'Vous avez une demande de dénonciation en attente', 'Vous avez reçu un nouveau message concernant une demande de dénonciation', null],
    'E4' => ['AcceptAddOption', 'f_confirm_ask_option_sms', 'prescripteur@exemple.test', "Votre demande d'option a été validée", "Votre demande d'option a été validée.", 'askopt'],
    'E5' => ['RejetAddOption', 'f_refuser_ask_option_sms', 'prescripteur@exemple.test', "Votre demande d'option a été refusée", "Votre demande d'option a été refusée.", 'askopt'],
    'E6' => ['AcceptAddResa', 'f_confirm_ask_resa_sms', 'prescripteur@exemple.test', 'Votre demande de réservation a été validée', 'Votre demande de réservation a été validée.', 'askres'],
    'E7' => ['RejetAddResa', 'f_refuser_ask_resa_sms', 'prescripteur@exemple.test', 'Votre demande de réservation a été refusée', 'Votre demande de réservation a été refusée.', 'askres'],
    'E8' => ['AcceptAddDenonciation', 'f_confirm_ask_denonciation_sms', 'prescripteur@exemple.test', 'Votre demande de dénonciation a été validée', 'Votre demande de dénonciation a été validée.', 'Client : M PROSPECT Paul'],
    'E9' => ['RejetAddDenonciation', 'f_refuser_ask_denonciation_sms', 'prescripteur@exemple.test', 'Votre demande de dénonciation a été refusée', 'Votre demande de dénonciation a été refusée.', 'Client : M PROSPECT Paul'],
    'E10' => ['PasserEnResa', 'f_passer_en_resa_sms', 'prescripteur@exemple.test', 'Votre option a été passée en réservation', 'Votre option a été passée en réservation.', 'opt'],
    'E11' => ['AnnulerOption', 'f_annuler_option_sms', 'prescripteur@exemple.test', 'Votre option a été annulée', 'Votre option a été annulée.', 'opt'],
    'E12' => ['AnnulerResa', 'f_annuler_resa_sms', 'prescripteur@exemple.test', 'Votre réservation a été annulée', 'Votre réservation a été annulée.', 'res'],
    'E13' => ['PasserEnActer', 'f_passer_en_act_sms', 'prescripteur@exemple.test', 'Votre dossier a été acté', 'Votre dossier a été acté.', 'res'],
    'E14' => ['AnnulerLaVente', 'f_annuler_la_vente_sms', 'prescripteur@exemple.test', 'La vente a été annulée', 'La vente a été annulée.', 'act'],
]);

it('C31 chaque e-mail E1-E14 a l\'expéditeur, le sujet, le corps HTML et la partie texte du legacy', function (string $action, ?string $smsKey, string $to, string $subject, string $phrase, ?string $ligne) {
    $w = workflowWorld();
    $data = workflowActions($w)[$action];
    if ($smsKey) {
        $data[$smsKey] = 'Merci pour votre confiance';
    }

    expect(jsonAction($w->corim, $action, $data)->getContent())->toBe('{"errors":1}');

    $mails = sentMails();
    expect($mails)->toHaveCount(1);
    $body = $smsKey === null
        ? mailNotification($phrase)
        : mailPrescripteur($phrase, 'Merci pour votre confiance', 'LES JARDINS TEST', str_starts_with((string) $ligne, 'Client') ? $ligne : 'Référence lot : '.$w->lots[$ligne]->numero);
    expectLegacyMail($mails[0], $to, '[Corim promotion] - '.$subject, $body);
})->with('emails');

it('C32 une erreur d\'envoi d\'e-mail ne laisse aucune écriture partielle', function () {
    Mail::extend('failing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('SMTP indisponible');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });
    config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
    $w = workflowWorld();
    $actions = workflowActions($w);

    $this->actingAs($w->corim)->post('/index.php/json/AddOption/', $actions['AddOption']);
    $lot = $w->lots['libre']->id;
    $demande = DB::table('d_options')->where('iddlot', $lot)->count();
    expect([$demande, lotStatut($lot)])->toBeIn([[0, ''], [1, 'opt']]);

    $this->actingAs($w->corim)->post('/index.php/json/AcceptAddOption/', $actions['AcceptAddOption']);
    $askopt = $w->lots['askopt']->id;
    expect([DB::table('d_options')->where('iddlot', $askopt)->count(), DB::table('options')->where('idlot', $askopt)->count()])->toBeIn([[1, 0], [0, 1]]);

    $this->actingAs($w->corim)->post('/index.php/json/PasserEnActer/', $actions['PasserEnActer']);
    $res = $w->lots['res']->id;
    $state = [lotStatut($res), DB::table('reservations')->where('idlot', $res)->where('statut', 1)->count(), DB::table('ventes')->where('idlot', $res)->count()];
    expect($state)->toBeIn([['res', 1, 0], ['act', 0, 1]]);

    $messages = DB::table('mp')->count();
    $this->actingAs($w->corim)->post('/index.php/json/AddProspect/', $actions['AddProspect']);
    expect([DB::table('d_prospects')->count(), DB::table('mp')->count() - $messages])->toBeIn([[1, 0], [2, 1]]);
});
