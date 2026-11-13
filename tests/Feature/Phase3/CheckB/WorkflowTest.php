<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('VB-W1 AddOption accepte un nom vide comme l\'ancien json.php (name vaut toujours « civilité »)', function () {
    $w = workflowWorld();

    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($w->lots['libre'], ['name' => '']))->getContent())->toBe('{"errors":1}')
        ->and(lotStatus($w->lots['libre']->id))->toBe('opt');
});

it('VB-W2 AddOption accepte un code postal vide comme l\'ancien json.php (cp jamais testé)', function () {
    $w = workflowWorld();

    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($w->lots['libre'], ['cp' => '']))->getContent())->toBe('{"errors":1}')
        ->and(decoded(DB::table('d_options')->where('iddlot', $w->lots['libre']->id)->value('adresse')))->toBe('MONTPELLIER ');
});

it('VB-W3 AddReservation accepte un nom ou un code postal vide comme l\'ancien json.php', function () {
    $w = workflowWorld();
    $lot = makeLot($w->program->projet_id, ['numero' => 'VB-RES']);
    DB::table('acces_lot')->insert(['iduser' => $w->prescriber->id, 'idlot' => $lot->id]);

    expect(jsonAction($w->prescriber, 'AddReservation', optionPayload($w->lots['libre'], ['name' => ''], 'id_reserver'))->getContent())->toBe('{"errors":1}')
        ->and(jsonAction($w->prescriber, 'AddReservation', optionPayload($lot, ['cp' => ''], 'id_reserver'))->getContent())->toBe('{"errors":1}');
});

it('VB-W4 cycle complet option → validation → réservation → acte → annulation de la vente, écritures identiques à model.php', function () {
    $w = workflowWorld();
    $lot = $w->lots['libre'];
    $u = $w->prescriber->id;

    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($lot))->getContent())->toBe('{"errors":1}');
    $pendingRequest = DB::table('d_options')->where('iddlot', $lot->id)->first();

    expect(jsonAction($w->corim, 'AcceptAddOption', ['f_confirm_ask_option_lot' => $lot->id, 'f_confirm_ask_option_user' => $u, 'f_confirm_ask_option_sms' => 'OK'])->getContent())->toBe('{"errors":1}');
    $option = DB::table('options')->where('idlot', $lot->id)->where('statut', 1)->first();
    expect($option->date)->toBe($pendingRequest->date)
        ->and($option->message)->toBe($pendingRequest->message.SEPARATOR.'OK')
        ->and(DB::table('d_options')->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('opt');

    expect(jsonAction($w->corim, 'PasserEnResa', ['f_passer_en_resa_lot' => $lot->id, 'f_passer_en_resa_user' => $u, 'f_passer_en_resa_sms' => 'R'])->getContent())->toBe('{"errors":1}');
    $reservation = DB::table('reservations')->where('idlot', $lot->id)->where('statut', 1)->first();
    expect($reservation->date)->toBe($pendingRequest->date)
        ->and($reservation->message)->toBe($option->message)
        ->and(DB::table('options')->where('idlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('res');

    expect(jsonAction($w->corim, 'PasserEnActer', ['passer_en_act_type' => 'resa', 'passer_en_act_lot' => $lot->id, 'f_passer_en_act_sms' => 'A'])->getContent())->toBe('{"errors":1}');
    $apartment = DB::table('appartements')->find($lot->id);
    $sale = DB::table('ventes')->where('idlot', $lot->id)->first();
    expect($apartment->statut)->toBe('act')
        ->and($apartment->client)->toBe($pendingRequest->nom)
        ->and((int) $apartment->vendeur)->toBe($u)
        ->and((int) $sale->iduser)->toBe($u)
        ->and((int) $sale->statut)->toBe(1)
        ->and($sale->date_resa)->toBe($pendingRequest->date)
        ->and(DB::table('reservations')->where('idlot', $lot->id)->count())->toBe(0);

    expect(jsonAction($w->corim, 'AnnulerLaVente', ['f_annuler_la_vente_idlot' => $lot->id, 'f_annuler_la_vente_vendeur' => $u, 'f_annuler_la_vente_sms' => 'X'])->getContent())->toBe('{"errors":1}');
    $apartment = DB::table('appartements')->find($lot->id);
    expect($apartment->statut)->toBe('')
        ->and($apartment->client)->toBe('')
        ->and((int) $apartment->vendeur)->toBe(0)
        ->and((int) DB::table('ventes')->where('idlot', $lot->id)->value('statut'))->toBe(0);

    expect(array_map(fn ($m) => $m->getSubject(), sentMails()))->toBe([
        "[Corim promotion] - Vous avez une demande d'option en attente",
        "[Corim promotion] - Votre demande d'option a été validée",
        '[Corim promotion] - Votre option a été passée en réservation',
        '[Corim promotion] - Votre dossier a été acté',
        '[Corim promotion] - La vente a été annulée',
    ]);
});

it('VB-W5 aucune transition hors machine à états : refus sans écriture ni e-mail', function () {
    $w = workflowWorld();
    $u = $w->prescriber->id;
    $l = $w->lots;
    $before = workflowState();

    $case = [
        'PasserEnResa sur une demande non validée' => ['PasserEnResa', ['f_passer_en_resa_lot' => $l['askopt']->id, 'f_passer_en_resa_user' => $u, 'f_passer_en_resa_sms' => 'x']],
        'PasserEnActer sur une demande de réservation' => ['PasserEnActer', ['passer_en_act_type' => 'resa', 'passer_en_act_lot' => $l['askres']->id, 'f_passer_en_act_sms' => 'x']],
        'AnnulerOption sur un lot libre' => ['AnnulerOption', ['f_annuler_option_lot' => $l['libre']->id, 'f_annuler_option_user' => $u, 'f_annuler_option_sms' => 'x']],
        'AnnulerResa sur une option' => ['AnnulerResa', ['f_annuler_resa_lot' => $l['opt']->id, 'f_annuler_resa_user' => $u, 'f_annuler_resa_sms' => 'x']],
        'AnnulerLaVente sur une réservation' => ['AnnulerLaVente', ['f_annuler_la_vente_idlot' => $l['res']->id, 'f_annuler_la_vente_vendeur' => $u, 'f_annuler_la_vente_sms' => 'x']],
        'AcceptAddOption sur une option déjà validée' => ['AcceptAddOption', ['f_confirm_ask_option_lot' => $l['opt']->id, 'f_confirm_ask_option_user' => $u, 'f_confirm_ask_option_sms' => 'x']],
        'AddReservation sur une option validée' => ['AddReservation', optionPayload($l['opt'], [], 'id_reserver')],
    ];
    foreach ($case as $label => [$action, $data]) {
        expect(jsonAction($action === 'AddReservation' ? $w->prescriber : $w->corim, $action, $data)->getContent())->toBeIn(['{"errors":0}', '{"errors":2}'], $label);
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

dataset('checkb_doubles', [
    'passage en réservation' => ['PasserEnResa'],
    'annulation d\'option' => ['AnnulerOption'],
    'annulation de réservation' => ['AnnulerResa'],
    'acte' => ['PasserEnActer'],
    'annulation de vente' => ['AnnulerLaVente'],
]);

it('VB-W6 un second appel identique (double clic) ne réécrit rien et n\'envoie pas de second mail', function (string $action) {
    $w = workflowWorld();
    $data = workflowActions($w)[$action];

    expect(jsonAction($w->corim, $action, $data)->getContent())->toBe('{"errors":1}');
    $after = workflowState();
    expect(jsonAction($w->corim, $action, $data)->getContent())->toBe('{"errors":0}')
        ->and(workflowState())->toBe($after)
        ->and(sentMails())->toHaveCount(1);
})->with('checkb_doubles');

it('VB-W7 un prescripteur ne peut pas poser d\'option ni de réservation sur un lot hors de ses droits (id deviné)', function () {
    $w = workflowWorld();
    $lot = makeLot($w->program->projet_id, ['numero' => 'VB-HORS']);
    $before = workflowState();

    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($lot))->getContent())->toBe('{"errors":2}')
        ->and(jsonAction($w->prescriber, 'AddReservation', optionPayload($lot, [], 'id_reserver'))->getContent())->toBe('{"errors":2}')
        ->and(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('VB-W8 un prescripteur ne peut pas dénoncer sur un programme hors de ses droits (id deviné)', function () {
    $w = workflowWorld();
    $other = makeProgram(['projet_titre' => 'VB AUTRE PROGRAMME']);
    $before = workflowState();

    expect(jsonAction($w->prescriber, 'AddProspect', prospectPayload($other->projet_id))->getContent())->toBe('{"errors":2}')
        ->and(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('VB-W9 la recherche de dénonciations d\'un prescripteur ne renvoie jamais celles d\'un autre, quel que soit le type', function () {
    $w = workflowWorld();
    $other = makeCommercial(['name' => 'VB Autre']);
    DB::table('prospects')->insert(prospectRow($w->program->projet_id, $other->id, ['nomprospect' => 'VBSECRET']));

    foreach (['1' => 'VBSECRET', '2' => 'JARDINS', '3' => 'VB Autre'] as $type => $word) {
        $body = (string) jsonAction($w->prescriber, 'SearchProspect', ['f_recherche_denonciation_search' => $word, 'f_recherche_denonciation_type' => $type])->getContent();
        expect($body)->not->toContain('VBSECRET', "type $type");
    }
});
