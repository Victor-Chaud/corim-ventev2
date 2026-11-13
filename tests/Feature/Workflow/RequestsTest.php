<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function requestCases(): array
{
    return [
        'option' => ['AddOption', 'id_option', 'd_options', 'opt', "Vous avez une demande d'option en attente", "[Corim promotion] - Vous avez une demande d'option en attente"],
        'reservation' => ['AddReservation', 'id_reserver', 'd_reservations', 'res', 'Vous avez une demande de réservation en attente', '[Corim promotion] - Vous avez une demande de réservation en attente'],
    ];
}

function expectRequestCreated(object $w, array $case, ?int $expectedUser = null): void
{
    [$action, $idKey, $table, $status, $mpSubject, $subject] = $case;
    $lot = $w->lots['libre'];

    expect(jsonAction($w->prescriber, $action, optionPayload($lot, [], $idKey))->getContent())->toBe('{"errors":1}');

    $row = DB::table($table)->where('iddlot', $lot->id)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->iduser)->toBe($expectedUser ?? $w->prescriber->id)
        ->and(decoded($row->nom))->toBe('Mme DUPONT Claire')
        ->and(decoded($row->adresse))->toBe('MONTPELLIER 34000')
        ->and(decoded($row->message))->toBe('Merci de bloquer ce lot')
        ->and((int) $row->idprojet)->toBe($w->program->projet_id)
        ->and(lotStatus($lot->id))->toBe($status);

    $mp = DB::table('mp')->latest('id')->first();
    expect($mp)->not->toBeNull()
        ->and((int) $mp->idemetteur)->toBe($w->prescriber->id)
        ->and((int) $mp->idreceveur)->toBe(1)
        ->and(decoded($mp->sujet))->toBe($mpSubject)
        ->and(decoded($mp->message))->toBe('Merci de bloquer ce lot');

    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe($subject)
        ->and($mails[0]->getTo()[0]->getAddress())->toBe(CORIM_TO);
}

it('C6 AddOption sur un lot libre crée la demande, le message interne et l\'e-mail E1', function () {
    expectRequestCreated(workflowWorld(), requestCases()['option']);
});

it('C7 AddOption sur un lot opt, res, act ou inexistant renvoie 2 sans écriture ni e-mail', function () {
    $w = workflowWorld();
    $before = workflowState();

    foreach (['askopt', 'askres', 'act'] as $name) {
        expect(jsonAction($w->prescriber, 'AddOption', optionPayload($w->lots[$name]))->getContent())->toBe('{"errors":2}', $name);
    }
    $ghost = (object) ['id' => 999999, 'lot' => $w->program->projet_id];
    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($ghost))->getContent())->toBe('{"errors":2}');

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C8 AddOption avec un champ obligatoire vide renvoie 0 sans écriture', function () {
    $w = workflowWorld();
    $before = workflowState();

    foreach (['prenom', 'adr', 'message', 'id_option', 'id_projet'] as $field) {
        expect(jsonAction($w->prescriber, 'AddOption', optionPayload($w->lots['libre'], [$field => '']))->getContent())->toBe('{"errors":0}', $field);
    }

    expect(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C9 prescripteur_select est pris en compte pour l\'admin seulement', function () {
    $w = workflowWorld();
    $other = makeCommercial(['name' => 'Autre Prescripteur']);
    DB::table('acces')->insert(['iduser' => $other->id, 'idprojet' => $w->program->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $other->id, 'idlot' => $w->lots['libre']->id]);

    expect(jsonAction($w->corim, 'AddOption', optionPayload($w->lots['libre'], ['prescripteur_select' => $other->id]))->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('d_options')->where('iddlot', $w->lots['libre']->id)->value('iduser'))->toBe($other->id);

    $lot = makeLot($w->program->projet_id, ['numero' => 'LIBRE-2']);
    DB::table('acces_lot')->insert(['iduser' => $w->prescriber->id, 'idlot' => $lot->id]);
    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($lot, ['prescripteur_select' => $other->id]))->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('d_options')->where('iddlot', $lot->id)->value('iduser'))->toBe($w->prescriber->id);
});

it('C10 AddReservation reproduit les cas 6 à 9 sur d_reservations avec statut res et e-mail E2', function () {
    $w = workflowWorld();
    expectRequestCreated($w, requestCases()['reservation']);

    foreach (['askopt', 'opt', 'act'] as $name) {
        expect(jsonAction($w->prescriber, 'AddReservation', optionPayload($w->lots[$name], [], 'id_reserver'))->getContent())->toBe('{"errors":2}', $name);
    }
    $lot = makeLot($w->program->projet_id, ['numero' => 'LIBRE-3']);
    DB::table('acces_lot')->insert(['iduser' => $w->prescriber->id, 'idlot' => $lot->id]);
    $before = workflowState();
    foreach (['prenom', 'adr', 'message', 'id_reserver', 'id_projet'] as $field) {
        expect(jsonAction($w->prescriber, 'AddReservation', optionPayload($lot, [$field => ''], 'id_reserver'))->getContent())->toBe('{"errors":0}', $field);
    }
    expect(workflowState())->toBe($before);

    $other = makeCommercial();
    expect(jsonAction($w->corim, 'AddReservation', optionPayload($lot, ['prescripteur_select' => $other->id], 'id_reserver'))->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('d_reservations')->where('iddlot', $lot->id)->value('iduser'))->toBe($other->id);
});

it('C11 une option puis une réservation sur le même lot : seule la première réussit', function () {
    $w = workflowWorld();
    $lot = $w->lots['libre'];

    expect(jsonAction($w->prescriber, 'AddOption', optionPayload($lot))->getContent())->toBe('{"errors":1}')
        ->and(jsonAction($w->prescriber, 'AddReservation', optionPayload($lot, [], 'id_reserver'))->getContent())->toBe('{"errors":2}')
        ->and(jsonAction($w->prescriber, 'AddOption', optionPayload($lot))->getContent())->toBe('{"errors":2}')
        ->and(DB::table('d_options')->where('iddlot', $lot->id)->count())->toBe(1)
        ->and(DB::table('d_reservations')->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatus($lot->id))->toBe('opt');
});

it('C12 AddProspect enregistre la dénonciation, le message interne et l\'e-mail E3, sans erreur SQL sur \\ et \'', function () {
    $w = workflowWorld();
    DB::table('d_prospects')->delete();

    expect(jsonAction($w->prescriber, 'AddProspect', prospectPayload($w->program->projet_id))->getContent())->toBe('{"errors":1}');
    $row = DB::table('d_prospects')->first();
    expect(decoded($row->nomprospect))->toBe('Mme & M. MARTIN Louis')
        ->and($row->cpprospect)->toBe('34070')
        ->and(decoded($row->villeprospect))->toBe('MONTPELLIER')
        ->and(decoded($row->smspromoteur))->toBe('Clients intéressés par un T3')
        ->and((int) $row->idprojet)->toBe($w->program->projet_id)
        ->and((int) $row->iduser)->toBe($w->prescriber->id);
    $mp = DB::table('mp')->latest('id')->first();
    expect((int) $mp->idreceveur)->toBe(1)
        ->and(decoded($mp->sujet))->toBe('Vous avez une dénonciation en attente');
    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe('[Corim promotion] - Vous avez une demande de dénonciation en attente')
        ->and($mails[0]->getTo()[0]->getAddress())->toBe(CORIM_TO);

    $count = DB::table('d_prospects')->count();
    foreach (['f_new_prospect_name', 'f_new_prospect_prenom', 'f_new_prospect_cp', 'f_new_prospect_ville', 'f_new_prospect_projet'] as $field) {
        expect(jsonAction($w->prescriber, 'AddProspect', prospectPayload($w->program->projet_id, [$field => '']))->getContent())->toBe('{"errors":0}', $field);
    }
    expect(DB::table('d_prospects')->count())->toBe($count);

    $response = jsonAction($w->prescriber, 'AddProspect', prospectPayload($w->program->projet_id, ['f_new_prospect_name' => "O'BRIEN\\", 'f_new_prospect_prenom' => "Jean\\'", 'f_new_prospect_sms' => "C'est \\ ok"]));
    expect($response->getContent())->toBe('{"errors":1}')
        ->and($response->getContent())->not->toContain('SQLSTATE');
    $last = DB::table('d_prospects')->orderByDesc('idprospect')->first();
    expect(decoded($last->nomprospect))->toBe("Mme & M. O'BRIEN\\ Jean\\'")
        ->and(decoded($last->smspromoteur))->toBe("C'est \\ ok");
});
