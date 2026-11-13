<?php

use Illuminate\Support\Facades\DB;

const WORKFLOW_PAGES = ['/ask_options', '/ask_reservations', '/ask_denonciations', '/options', '/options/refused', '/reservations', '/reservations/refused', '/ventes', '/denonciations', '/refused_ask', '/report'];

const WORKFLOW_ADMIN_ACTIONS = ['AcceptAddOption', 'RejetAddOption', 'AcceptAddResa', 'RejetAddResa', 'AcceptAddDenonciation', 'RejetAddDenonciation', 'PasserEnResa', 'AnnulerOption', 'AnnulerResa', 'PasserEnActer', 'AnnulerLaVente', 'DeleteDenonciation', 'UpdateDate'];

it('C1 les pages du workflow sans session redirigent vers /connexion', function () {
    foreach (WORKFLOW_PAGES as $url) {
        $response = $this->get($url);
        expect($response->isRedirect(url('/connexion')))->toBeTrue($url)
            ->and($response->getContent())->not->toContain('Corim promotion -');
    }
});

it('C2 les actions json du workflow sans session sont refusées sans effet ni e-mail', function () {
    $w = workflowWorld();
    foreach (array_diff(WORKFLOW_ACTIONS, ['Acter']) as $action) {
        expectRouteExists(jsonAction($w->corim, $action, []), $action);
    }
    $before = workflowState();
    $mails = count(sentMails());

    foreach (workflowActions($w) as $action => $data) {
        $response = jsonAction(null, $action, $data);
        expect($response->getStatusCode())->toBeIn($action === 'Acter' ? [302, 401, 403, 404] : [302, 401, 403], $action)
            ->and($response->getContent())->not->toContain('errors');
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toHaveCount($mails);
});

it('C3 un prescripteur qui appelle A4 à A17 est refusé sans effet en base', function () {
    $w = workflowWorld();
    foreach (WORKFLOW_ADMIN_ACTIONS as $action) {
        expectRouteExists(jsonAction($w->corim, $action, []), $action);
    }
    $before = workflowState();
    $actions = workflowActions($w);

    foreach ([...WORKFLOW_ADMIN_ACTIONS, 'Acter'] as $action) {
        $response = jsonAction($w->prescriber, $action, $actions[$action]);
        expect($response->getStatusCode())->toBeIn([302, 403, 404], $action)
            ->and($response->getContent())->not->toContain('"errors":1');
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C4 un POST sans jeton CSRF valide est rejeté en 419 sans effet', function () {
    $w = workflowWorld();
    foreach (array_diff(WORKFLOW_ACTIONS, ['Acter']) as $action) {
        expectRouteExists(jsonAction($w->corim, $action, []), $action);
    }
    $before = workflowState();
    withoutTestingCsrfBypass();

    foreach (workflowActions($w) as $action => $data) {
        if ($action === 'Acter') {
            continue;
        }
        expect(jsonAction($w->corim, $action, $data)->getStatusCode())->toBe(419, $action);
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C5 /index.php/json/<Action>/ et /json/<Action> répondent le même JSON brut sans saut de ligne', function () {
    $w = workflowWorld();
    $admin = $w->corim;

    $first = $this->actingAs($admin)->post('/index.php/json/DeleteDenonciation/', ['denonciation' => 999999]);
    $second = $this->actingAs($admin)->post('/json/DeleteDenonciation', ['denonciation' => 999999]);
    expect($first->getContent())->toBe('{"errors":0}')
        ->and($second->getContent())->toBe('{"errors":0}');

    $cases = [
        ['AddOption', optionPayload($w->lots['opt']), '{"errors":2}'],
        ['AddOption', optionPayload($w->lots['libre'], ['prenom' => '']), '{"errors":0}'],
        ['AddProspect', prospectPayload($w->program->projet_id, ['f_new_prospect_ville' => '']), '{"errors":0}'],
        ['AcceptAddOption', ['f_confirm_ask_option_lot' => 999999, 'f_confirm_ask_option_user' => $w->prescriber->id, 'f_confirm_ask_option_sms' => ''], '{"errors":0}'],
        ['AcceptAddResa', ['f_confirm_ask_resa_lot' => '', 'f_confirm_ask_resa_user' => '', 'f_confirm_ask_resa_sms' => ''], '{"errors":3}'],
        ['AcceptAddDenonciation', ['f_confirm_ask_denonciation_lot' => '', 'f_confirm_ask_denonciation_user' => '', 'f_confirm_ask_denonciation_sms' => ''], '{"errors":3}'],
        ['RejetAddOption', ['f_refuser_ask_option_lot' => 999999, 'f_refuser_ask_option_user' => $w->prescriber->id, 'f_refuser_ask_option_sms' => ''], '{"errors":0}'],
        ['PasserEnResa', ['f_passer_en_resa_lot' => $w->lots['libre']->id, 'f_passer_en_resa_user' => $w->prescriber->id, 'f_passer_en_resa_sms' => ''], '{"errors":0}'],
        ['AnnulerLaVente', ['f_annuler_la_vente_idlot' => '', 'f_annuler_la_vente_vendeur' => '', 'f_annuler_la_vente_sms' => ''], '{"errors":0}'],
        ['UpdateDate', ['idlot' => 999999, 'type' => 'option', 'time' => '2026-05-05 08:00:00'], '{"errors":0}'],
        ['SearchProspect', ['f_recherche_denonciation_search' => '', 'f_recherche_denonciation_type' => '1'], '{"errors":0}'],
        ['SearchProspect', ['f_recherche_denonciation_search' => 'INTROUVABLE', 'f_recherche_denonciation_type' => '1'], '{"errors":2}'],
    ];
    foreach ($cases as [$action, $data, $expected]) {
        expect(jsonAction($admin, $action, $data)->getContent())->toBe($expected, $action);
    }
});

it('C33 ?callback=x sur une action JSON ne change pas la réponse', function () {
    $admin = corimAdmin();

    $response = $this->actingAs($admin)->post('/index.php/json/DeleteDenonciation/?callback=x', ['denonciation' => 999999]);

    expectRouteExists($response, 'DeleteDenonciation');
    expect($response->getContent())->toBe('{"errors":0}');
});

it('C34 /optionner, /reserver et json/Acter n\'exécutent aucune écriture', function () {
    $w = workflowWorld();
    $before = workflowState();

    foreach (['/optionner', '/optionner/demande/'.$w->lots['libre']->id, '/reserver', '/reserver/demande/'.$w->lots['libre']->id] as $url) {
        expect($this->actingAs($w->prescriber)->get($url)->getStatusCode())->not->toBe(500, $url);
    }
    foreach (['/optionner/validation', '/reserver/validation'] as $url) {
        $response = $this->actingAs($w->prescriber)->post($url, ['name' => 'X', 'adr' => 'Y', 'message' => 'Z', 'id' => $w->lots['libre']->id]);
        expect($response->getStatusCode())->not->toBe(500, $url);
    }
    $deedResponse = jsonAction($w->corim, 'Acter', ['type' => 'resa', 'idlot' => $w->lots['res']->id]);
    expect($deedResponse->getStatusCode())->not->toBe(500)
        ->and($deedResponse->getContent())->not->toContain('"errors":1');

    expect(workflowState())->toBe($before)
        ->and(lotStatus($w->lots['libre']->id))->toBe('')
        ->and(DB::table('ventes')->count())->toBe(1);
});
