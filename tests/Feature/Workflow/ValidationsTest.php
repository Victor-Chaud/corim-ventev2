<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function onlyMailTo(string $email, string $subject): void
{
    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe($subject)
        ->and($mails[0]->getTo()[0]->getAddress())->toBe($email);
}

it('C13 AcceptAddOption déplace la demande dans options, suffixe le message et envoie E4', function () {
    $w = workflowWorld();
    $lot = $w->lots['askopt'];

    expect(jsonAction($w->corim, 'AcceptAddOption', ['f_confirm_ask_option_lot' => $lot->id, 'f_confirm_ask_option_user' => $w->prescriber->id, 'f_confirm_ask_option_sms' => 'Option validée'])->getContent())
        ->toBe('{"errors":1}');

    $option = DB::table('options')->where('idlot', $lot->id)->first();
    expect($option)->not->toBeNull()
        ->and((int) $option->statut)->toBe(1)
        ->and((int) $option->iduser)->toBe($w->prescriber->id)
        ->and(decoded($option->nom))->toBe('Mme ACQUEREUR Anne')
        ->and(decoded($option->adresse))->toBe('MONTPELLIER 34000')
        ->and((string) $option->date)->toBe('2026-01-10 10:00:00')
        ->and(decoded($option->message))->toBe('Message du prescripteur'.SEPARATOR.'Option validée')
        ->and(DB::table('d_options')->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('opt');
    onlyMailTo('prescripteur@exemple.test', "[Corim promotion] - Votre demande d'option a été validée");
});

it('C14 RejetAddOption supprime la demande, libère le lot, envoie E5 et ne stocke pas le message', function () {
    $w = workflowWorld();
    $lot = $w->lots['askopt'];

    expect(jsonAction($w->corim, 'RejetAddOption', ['f_refuser_ask_option_lot' => $lot->id, 'f_refuser_ask_option_user' => $w->prescriber->id, 'f_refuser_ask_option_sms' => 'Refus MOTIF-UNIQUE'])->getContent())
        ->toBe('{"errors":1}')
        ->and(DB::table('d_options')->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('');
    foreach (['options' => 'message', 'd_options' => 'message', 'mp' => 'message'] as $table => $column) {
        expect(DB::table($table)->where($column, 'like', '%MOTIF-UNIQUE%')->count())->toBe(0, $table);
    }
    onlyMailTo('prescripteur@exemple.test', "[Corim promotion] - Votre demande d'option a été refusée");
});

it('C15 AcceptAddResa et RejetAddResa sont les équivalents pour les réservations, paramètre vide → 3', function () {
    $w = workflowWorld();
    $lot = $w->lots['askres'];

    expect(jsonAction($w->corim, 'AcceptAddResa', ['f_confirm_ask_resa_lot' => '', 'f_confirm_ask_resa_user' => $w->prescriber->id, 'f_confirm_ask_resa_sms' => 'x'])->getContent())->toBe('{"errors":3}')
        ->and(jsonAction($w->corim, 'AcceptAddResa', ['f_confirm_ask_resa_lot' => $lot->id, 'f_confirm_ask_resa_user' => '', 'f_confirm_ask_resa_sms' => 'x'])->getContent())->toBe('{"errors":3}');

    expect(jsonAction($w->corim, 'AcceptAddResa', ['f_confirm_ask_resa_lot' => $lot->id, 'f_confirm_ask_resa_user' => $w->prescriber->id, 'f_confirm_ask_resa_sms' => 'Résa validée'])->getContent())->toBe('{"errors":1}');
    $reservation = DB::table('reservations')->where('idlot', $lot->id)->first();
    expect((int) $reservation->statut)->toBe(1)
        ->and((int) $reservation->iduser)->toBe($w->prescriber->id)
        ->and((string) $reservation->date)->toBe('2026-01-10 10:00:00')
        ->and(decoded($reservation->message))->toBe('Message du prescripteur'.SEPARATOR.'Résa validée')
        ->and(DB::table('d_reservations')->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('res');
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre demande de réservation a été validée');

    $other = makeLot($w->program->projet_id, ['statut' => 'res', 'numero' => 'ASKRES-2']);
    DB::table('d_reservations')->insert(requestRow($other, $w->prescriber->id) + ['iddlot' => $other->id]);
    expect(jsonAction($w->corim, 'RejetAddResa', ['f_refuser_ask_resa_lot' => $other->id, 'f_refuser_ask_resa_user' => $w->prescriber->id, 'f_refuser_ask_resa_sms' => 'Non'])->getContent())->toBe('{"errors":1}')
        ->and(DB::table('d_reservations')->where('iddlot', $other->id)->count())->toBe(0)
        ->and(lotStatus($other->id))->toBe('')
        ->and(sentMails()[1]->getSubject())->toBe('[Corim promotion] - Votre demande de réservation a été refusée');
});

it('C16 AcceptAddDenonciation déplace la dénonciation dans prospects et envoie E8, paramètre vide → 3', function () {
    $w = workflowWorld();

    expect(jsonAction($w->corim, 'AcceptAddDenonciation', ['f_confirm_ask_denonciation_lot' => '', 'f_confirm_ask_denonciation_user' => $w->prescriber->id, 'f_confirm_ask_denonciation_sms' => 'x'])->getContent())->toBe('{"errors":3}');

    $before = DB::table('prospects')->count();
    expect(jsonAction($w->corim, 'AcceptAddDenonciation', ['f_confirm_ask_denonciation_lot' => $w->askProspect, 'f_confirm_ask_denonciation_user' => $w->prescriber->id, 'f_confirm_ask_denonciation_sms' => 'Dénonciation validée'])->getContent())->toBe('{"errors":1}');

    $row = DB::table('prospects')->where('nomprospect', 'M PROSPECT Paul')->first();
    expect(DB::table('prospects')->count())->toBe($before + 1)
        ->and($row)->not->toBeNull()
        ->and((int) $row->iduser)->toBe($w->prescriber->id)
        ->and((string) $row->date)->toBe('2026-01-20 10:00:00')
        ->and(decoded($row->smspromoteur))->toBe('Message de dénonciation'.SEPARATOR.'Dénonciation validée')
        ->and(DB::table('d_prospects')->where('idprospect', $w->askProspect)->count())->toBe(0);
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre demande de dénonciation a été validée');
    expect((string) sentMails()[0]->getHtmlBody())->toContain('<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:14px">Client : M PROSPECT Paul</td></tr>');
});

it('C17 RejetAddDenonciation copie dans r_prospects avec le même id, supprime la demande dès le premier appel et envoie E9', function () {
    $w = workflowWorld();

    expect(jsonAction($w->corim, 'RejetAddDenonciation', ['f_refuser_ask_denonciation_lot' => $w->askProspect, 'f_refuser_ask_denonciation_user' => $w->prescriber->id, 'f_refuser_ask_denonciation_sms' => 'Déjà connu'])->getContent())
        ->toBe('{"errors":1}');

    $row = DB::table('r_prospects')->where('idprospect', $w->askProspect)->first();
    expect($row)->not->toBeNull()
        ->and(decoded($row->nomprospect))->toBe('M PROSPECT Paul')
        ->and(decoded($row->smspromoteur))->toBe('Message de dénonciation'.SEPARATOR.'Déjà connu')
        ->and(DB::table('d_prospects')->where('idprospect', $w->askProspect)->count())->toBe(0);
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre demande de dénonciation a été refusée');
});

it('C18 PasserEnResa crée la réservation à la date de l\'option, supprime l\'option, lot res, E10 ; sans option active → 0', function () {
    $w = workflowWorld();
    $lot = $w->lots['opt'];

    expect(jsonAction($w->corim, 'PasserEnResa', ['f_passer_en_resa_lot' => $lot->id, 'f_passer_en_resa_user' => $w->prescriber->id, 'f_passer_en_resa_sms' => 'Go'])->getContent())->toBe('{"errors":1}');
    $reservation = DB::table('reservations')->where('idlot', $lot->id)->first();
    expect((int) $reservation->statut)->toBe(1)
        ->and((int) $reservation->iduser)->toBe($w->prescriber->id)
        ->and((string) $reservation->date)->toBe('2026-01-11 10:00:00')
        ->and(DB::table('options')->where('idlot', $lot->id)->where('statut', 1)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('res');
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre option a été passée en réservation');

    expect(jsonAction($w->corim, 'PasserEnResa', ['f_passer_en_resa_lot' => $w->lots['libre']->id, 'f_passer_en_resa_user' => $w->prescriber->id, 'f_passer_en_resa_sms' => 'Go'])->getContent())->toBe('{"errors":0}')
        ->and(lotStatus($w->lots['libre']->id))->toBe('');
});

it('C19 AnnulerOption passe l\'option à 0, libère le lot, envoie E11 et la ligne apparaît dans /options/refused', function () {
    $w = workflowWorld();
    $lot = $w->lots['opt'];

    expect(jsonAction($w->corim, 'AnnulerOption', ['f_annuler_option_lot' => $lot->id, 'f_annuler_option_user' => $w->prescriber->id, 'f_annuler_option_sms' => 'Non'])->getContent())->toBe('{"errors":1}')
        ->and(DB::table('options')->where('idlot', $lot->id)->pluck('statut')->map(fn ($s) => (int) $s)->all())->toBe([0])
        ->and(lotStatus($lot->id))->toBe('');
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre option a été annulée');

    $this->actingAs($w->corim)->get('/options/refused')->assertOk()->assertSee('OPT-1');
    $this->actingAs($w->corim)->get('/options')->assertOk()->assertDontSee('OPT-1');
});

it('C20 AnnulerResa passe la réservation à 0, libère le lot, envoie E12 et la ligne apparaît dans /reservations/refused', function () {
    $w = workflowWorld();
    $lot = $w->lots['res'];

    expect(jsonAction($w->corim, 'AnnulerResa', ['f_annuler_resa_lot' => $lot->id, 'f_annuler_resa_user' => $w->prescriber->id, 'f_annuler_resa_sms' => 'Non'])->getContent())->toBe('{"errors":1}')
        ->and(DB::table('reservations')->where('idlot', $lot->id)->pluck('statut')->map(fn ($s) => (int) $s)->all())->toBe([0])
        ->and(lotStatus($lot->id))->toBe('');
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre réservation a été annulée');

    $this->actingAs($w->corim)->get('/reservations/refused')->assertOk()->assertSee('RES-1');
    $this->actingAs($w->corim)->get('/reservations')->assertOk()->assertDontSee('RES-1');
});

it('C21 PasserEnActer type resa acte le lot, crée la vente et envoie E13 ; sans résa ou type inconnu → 0', function () {
    $w = workflowWorld();
    $lot = $w->lots['res'];

    $response = jsonAction($w->corim, 'PasserEnActer', ['passer_en_act_type' => 'resa', 'passer_en_act_lot' => $lot->id, 'f_passer_en_act_sms' => 'Acte signé']);
    expect($response->getContent())->toBe('{"errors":1}');

    $acted = DB::table('appartements')->find($lot->id);
    $sale = DB::table('ventes')->where('idlot', $lot->id)->first();
    expect($acted->statut)->toBe('act')
        ->and(decoded($acted->client))->toBe('Mme ACQUEREUR Anne')
        ->and((int) $acted->vendeur)->toBe($w->prescriber->id)
        ->and(DB::table('reservations')->where('idlot', $lot->id)->where('statut', 1)->count())->toBe(0)
        ->and((int) $sale->iduser)->toBe($w->prescriber->id)
        ->and((int) $sale->statut)->toBe(1)
        ->and((string) $sale->date_resa)->toBe('2026-01-12 10:00:00')
        ->and(abs(strtotime((string) $sale->date) - time()))->toBeLessThan(120);
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - Votre dossier a été acté');

    $free = $w->lots['libre'];
    expect(jsonAction($w->corim, 'PasserEnActer', ['passer_en_act_type' => 'resa', 'passer_en_act_lot' => $free->id, 'f_passer_en_act_sms' => ''])->getContent())->toBe('{"errors":0}');
    $unknown = jsonAction($w->corim, 'PasserEnActer', ['passer_en_act_type' => 'inconnu', 'passer_en_act_lot' => $w->lots['opt']->id, 'f_passer_en_act_sms' => '']);
    expect($unknown->getStatusCode())->toBe(200)
        ->and($unknown->getContent())->toBe('{"errors":0}')
        ->and(lotStatus($w->lots['opt']->id))->toBe('opt')
        ->and(DB::table('ventes')->count())->toBe(2);
});

it('C22 AnnulerLaVente libère le lot, annule la vente et envoie E14 ; vendeur discordant → 0', function () {
    $w = workflowWorld();
    $lot = $w->lots['act'];
    $other = makeCommercial();

    expect(jsonAction($w->corim, 'AnnulerLaVente', ['f_annuler_la_vente_idlot' => $lot->id, 'f_annuler_la_vente_vendeur' => $other->id, 'f_annuler_la_vente_sms' => 'Non'])->getContent())->toBe('{"errors":0}')
        ->and(lotStatus($lot->id))->toBe('act')
        ->and(sentMails())->toBeEmpty();

    expect(jsonAction($w->corim, 'AnnulerLaVente', ['f_annuler_la_vente_idlot' => $lot->id, 'f_annuler_la_vente_vendeur' => $w->prescriber->id, 'f_annuler_la_vente_sms' => 'Annulée'])->getContent())->toBe('{"errors":1}');
    $row = DB::table('appartements')->find($lot->id);
    expect($row->statut)->toBe('')
        ->and($row->client)->toBe('')
        ->and((int) $row->vendeur)->toBe(0)
        ->and((int) DB::table('ventes')->where('idlot', $lot->id)->value('statut'))->toBe(0);
    onlyMailTo('prescripteur@exemple.test', '[Corim promotion] - La vente a été annulée');
});

it('C23 DeleteDenonciation supprime la dénonciation → 1, id inexistant → 0', function () {
    $w = workflowWorld();

    expect(jsonAction($w->corim, 'DeleteDenonciation', ['denonciation' => $w->prospect])->getContent())->toBe('{"errors":1}')
        ->and(DB::table('prospects')->where('idprospect', $w->prospect)->count())->toBe(0)
        ->and(jsonAction($w->corim, 'DeleteDenonciation', ['denonciation' => $w->prospect])->getContent())->toBe('{"errors":0}')
        ->and(sentMails())->toBeEmpty();
});

it('C24 UpdateDate ne modifie que la colonne prévue pour chacun des 8 types, type inconnu → 0', function () {
    $w = workflowWorld();
    $l = $w->lots;
    DB::table('options')->insert(requestRow($l['opt'], $w->prescriber->id, ['date' => '2025-06-01 10:00:00']) + ['idlot' => $l['opt']->id, 'statut' => 0]);
    DB::table('reservations')->insert(requestRow($l['res'], $w->prescriber->id, ['date' => '2025-06-01 10:00:00']) + ['idlot' => $l['res']->id, 'statut' => 0]);
    DB::table('ventes')->insert(['iduser' => $w->prescriber->id, 'idlot' => $l['act']->id, 'date' => '2025-06-01 10:00:00', 'statut' => 0, 'date_resa' => '2025-05-01 10:00:00']);
    $time = '2026-05-05 08:00:00';

    $cases = [
        'askoption' => [$l['askopt']->id, fn () => DB::table('d_options')->where('iddlot', $l['askopt']->id)->value('date')],
        'askresa' => [$l['askres']->id, fn () => DB::table('d_reservations')->where('iddlot', $l['askres']->id)->value('date')],
        'askden' => [$w->askProspect, fn () => DB::table('d_prospects')->where('idprospect', $w->askProspect)->value('date')],
        'option' => [$l['opt']->id, fn () => DB::table('options')->where('idlot', $l['opt']->id)->where('statut', 1)->value('date')],
        'resa' => [$l['res']->id, fn () => DB::table('reservations')->where('idlot', $l['res']->id)->where('statut', 1)->value('date')],
        'den' => [$w->prospect, fn () => DB::table('prospects')->where('idprospect', $w->prospect)->value('date')],
        'vente' => [$l['act']->id, fn () => DB::table('ventes')->where('idlot', $l['act']->id)->where('statut', 1)->value('date')],
        'venteresa' => [$l['act']->id, fn () => DB::table('ventes')->where('idlot', $l['act']->id)->where('statut', 1)->value('date_resa')],
    ];
    foreach ($cases as $type => [$id, $read]) {
        $state = tablesState(WORKFLOW_TABLES);
        expect(jsonAction($w->corim, 'UpdateDate', ['idlot' => $id, 'type' => $type, 'time' => $time])->getContent())->toBe('{"errors":1}', $type)
            ->and((string) $read())->toBe($time, $type)
            ->and(tablesState(WORKFLOW_TABLES))->not->toBe($state);
    }

    expect(DB::table('options')->where('statut', 0)->value('date'))->toBe('2025-06-01 10:00:00')
        ->and(DB::table('reservations')->where('statut', 0)->value('date'))->toBe('2025-06-01 10:00:00')
        ->and(DB::table('ventes')->where('statut', 0)->value('date'))->toBe('2025-06-01 10:00:00')
        ->and(DB::table('ventes')->where('statut', 0)->value('date_resa'))->toBe('2025-05-01 10:00:00')
        ->and(DB::table('ventes')->where('statut', 1)->value('date'))->toBe($time);

    $state = workflowState();
    $unknown = jsonAction($w->corim, 'UpdateDate', ['idlot' => $l['opt']->id, 'type' => 'inconnu', 'time' => '2026-07-07 08:00:00']);
    expect($unknown->getStatusCode())->toBe(200)
        ->and($unknown->getContent())->toBe('{"errors":0}')
        ->and(workflowState())->toBe($state);
});

it('C25 SearchProspect : vide → 0, types 1/2/3 → call aux clés prospects.*, prescripteur limité à ses lignes, rien → 2', function () {
    $w = workflowWorld();
    $other = makeCommercial(['name' => 'Zoe Concurrente']);
    DB::table('prospects')->insert(prospectRow($w->program->projet_id, $other->id, ['nomprospect' => 'Mme VALIDEE Autre']));

    expect(jsonAction($w->corim, 'SearchProspect', ['f_recherche_denonciation_search' => '', 'f_recherche_denonciation_type' => '1'])->getContent())->toBe('{"errors":0}');

    $call = fn ($user, $search, $type) => jsonAction($user, 'SearchProspect', ['f_recherche_denonciation_search' => $search, 'f_recherche_denonciation_type' => $type])->json();
    $names = fn ($data) => collect($data['call'] ?? [])->pluck('nomprospect')->sort()->values()->all();

    $data = $call($w->corim, 'VALIDEE', '1');
    expect($data['errors'])->toBe(1)
        ->and($names($data))->toBe(['Mme VALIDEE Autre', 'Mme VALIDEE Julie'])
        ->and(array_keys($data['call'][0]))->toBe(['idprospect', 'nomprospect', 'cpprospect', 'villeprospect', 'smspromoteur', 'idprojet', 'iduser', 'date', 'projet_titre', 'prescripteur'])
        ->and($data['call'][0]['date'])->toMatch('/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/');
    expect($names($call($w->corim, 'JARDINS', '2')))->toBe(['Mme VALIDEE Autre', 'Mme VALIDEE Julie'])
        ->and($names($call($w->corim, 'Concurrente', '3')))->toBe(['Mme VALIDEE Autre'])
        ->and($names($call($w->prescriber, 'VALIDEE', '1')))->toBe(['Mme VALIDEE Julie'])
        ->and($call($w->prescriber, 'Concurrente', '3'))->toBe(['errors' => 2])
        ->and($call($w->corim, 'INTROUVABLE', '1'))->toBe(['errors' => 2]);
});
