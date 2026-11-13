<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('O1 AddOption et AddReservation admin pour un prescripteur inexistant renvoient 0 sans écriture ni e-mail', function () {
    $w = workflowWorld();
    $before = workflowState();

    foreach (requestCases() as [$action, $idKey]) {
        $payload = optionPayload($w->lots['libre'], ['prescripteur_select' => '999999'], $idKey);
        expect(jsonAction($w->corim, $action, $payload)->getContent())->toBe('{"errors":0}', $action);
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('O2 AddProspect admin pour un prescripteur inexistant renvoie 3 sans écriture ni e-mail', function () {
    $w = workflowWorld();
    $before = workflowState();

    expect(jsonAction($w->corim, 'AddProspect', prospectPayload($w->program->projet_id, ['prescripteur_select' => '999999']))->getContent())->toBe('{"errors":3}')
        ->and(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('O3 les compteurs du tableau de bord ignorent les lignes orphelines invisibles dans les listes', function () {
    $w = workflowWorld();
    $expected = dashboardCounters(pageText($this->actingAs($w->corim)->get('/')->assertOk()));

    $lot = makeLot($w->program->projet_id);
    DB::table('d_options')->insert(requestRow($lot, 0) + ['iddlot' => $lot->id]);
    $lot2 = makeLot($w->program->projet_id);
    DB::table('d_reservations')->insert(requestRow($lot2, 32000) + ['iddlot' => $lot2->id]);
    DB::table('d_prospects')->insert(['nomprospect' => 'M FANTOME', 'cpprospect' => '34000', 'villeprospect' => 'MONTPELLIER', 'smspromoteur' => '', 'idprojet' => $w->program->projet_id, 'iduser' => 0, 'date' => now()]);
    DB::table('prospects')->insert(['nomprospect' => 'M FANTOME', 'cpprospect' => '34000', 'villeprospect' => 'MONTPELLIER', 'smspromoteur' => '', 'idprojet' => 999999, 'iduser' => $w->prescriber->id, 'date' => now()]);

    expect(dashboardCounters(pageText($this->actingAs($w->corim)->get('/')->assertOk())))->toBe($expected);
});

it('O4 un lot acté sans vente active n\'est pas compté dans LOTS ACTÉ, comme dans la liste /ventes', function () {
    $w = workflowWorld();
    $expected = dashboardCounters(pageText($this->actingAs($w->corim)->get('/')->assertOk()));

    makeLot($w->program->projet_id, ['statut' => 'act', 'vendeur' => $w->prescriber->id]);

    expect(dashboardCounters(pageText($this->actingAs($w->corim)->get('/')->assertOk())))->toBe($expected);
});
