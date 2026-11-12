<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C15 gestion_prospects affiche la vue Dénonciations vide avec les radios 1/2/3', function () {
    $w = workflowWorld();

    $response = $this->actingAs($w->corim)->get('/gestion_prospects');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim promotion - Gestion des prospects</title>')
        ->toContain('<h2>Consultation de vos dénonciations <small>Rechercher une dénonciation</small></h2>')
        ->toContain('id="f_recherche_denonciation_result"')
        ->not->toContain('Mme VALIDEE Julie');
    foreach (['1' => 'NOM ACQUÉREUR', '2' => 'RÉSIDENCE', '3' => 'PRESCRIPTEUR'] as $value => $label) {
        expect($text)->toMatch('#name="f_recherche_denonciation_type"[^>]*value="'.$value.'"[^>]*>\s*'.$label.'#u');
    }
    expectInOrder($text, ['Date', 'Nom', 'Code Postal', 'Ville', 'Programme', 'Prescripteurs', 'Action']);
});

it('C16 SearchProspect filtre nom, programme, prescripteur ; prescripteur limité à ses prospects ; date dd.mm.YYYY HH:ii', function () {
    $w = workflowWorld();
    $other = makeCommercial(['name' => 'Zoe Concurrente']);
    $projet2 = makeProjet(['projet_titre' => 'AUTRE RESIDENCE']);
    DB::table('prospects')->insert(prospectRow($projet2->projet_id, $other->id, ['nomprospect' => 'M AUTRE Pierre', 'date' => '2026-04-05 14:30:00']));
    $search = fn ($user, $q, $type) => jsonAction($user, 'SearchProspect', ['f_recherche_denonciation_search' => $q, 'f_recherche_denonciation_type' => $type])->json();
    $names = fn ($data) => collect($data['call'] ?? [])->pluck('nomprospect')->sort()->values()->all();

    expect($names($search($w->corim, 'AUTRE', '1')))->toBe(['M AUTRE Pierre'])
        ->and($names($search($w->corim, 'JARDINS', '2')))->toBe(['Mme VALIDEE Julie'])
        ->and($names($search($w->corim, 'Concurrente', '3')))->toBe(['M AUTRE Pierre'])
        ->and($names($search($w->presc, 'M', '1')))->toBe(['Mme VALIDEE Julie'])
        ->and($search($w->corim, 'AUTRE', '1')['call'][0]['date'])->toBe('05.04.2026 14:30')
        ->and($search($w->corim, 'AUTRE', '1')['call'][0]['prescripteur'])->toBe('Zoe Concurrente')
        ->and($search($w->corim, 'AUTRE', '1')['call'][0]['projet_titre'])->toBe('AUTRE RESIDENCE');
});

it('C17 SearchProspect avec un type hors 1/2/3 → 422 sans erreur SQL', function () {
    $w = workflowWorld();

    foreach (['4', '0', 'x', "1' OR 1=1 --"] as $type) {
        $response = jsonAction($w->corim, 'SearchProspect', ['f_recherche_denonciation_search' => 'VALIDEE', 'f_recherche_denonciation_type' => $type]);
        expect($response->getStatusCode())->toBe(422, $type)
            ->and($response->getContent())->not->toContain('SQLSTATE')->not->toContain('VALIDEE');
    }
});

it('C18 AddProspect connecté : champ manquant → 0, succès → 1 avec d_prospects, mp et e-mail', function () {
    $w = workflowWorld();
    DB::table('d_prospects')->delete();

    expect(jsonAction($w->presc, 'AddProspect', prospectPayload($w->projet->projet_id, ['f_new_prospect_prenom' => '']))->getContent())->toBe('{"errors":0}')
        ->and(DB::table('d_prospects')->count())->toBe(0);

    expect(jsonAction($w->presc, 'AddProspect', prospectPayload($w->projet->projet_id, ['f_new_prospect_civilite' => 'M']))->getContent())->toBe('{"errors":1}');
    $row = DB::table('d_prospects')->first();
    expect(decoded($row->nomprospect))->toBe('M MARTIN Louis')
        ->and((int) $row->iduser)->toBe($w->presc->id);
    $mp = DB::table('mp')->latest('id')->first();
    expect((int) $mp->idreceveur)->toBe(1)
        ->and((int) $mp->idemetteur)->toBe($w->presc->id)
        ->and(decoded($mp->sujet))->toBe('Vous avez une dénonciation en attente')
        ->and(decoded($mp->message))->toBe('Clients intéressés par un T3');
    expectLegacyMail(sentMails()[0], CORIM_TO, '[Corim promotion] - Vous avez une demande de dénonciation en attente',
        mailNotification('Vous avez reçu un nouveau message concernant une demande de dénonciation'));
});

it('C19 AddProspect par un prescripteur avec prescripteur_select d\'un autre utilisateur reste à son nom', function () {
    $w = workflowWorld();
    DB::table('d_prospects')->delete();
    $other = makeCommercial();

    expect(jsonAction($w->presc, 'AddProspect', prospectPayload($w->projet->projet_id, ['prescripteur_select' => $other->id]))->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('d_prospects')->value('iduser'))->toBe($w->presc->id);
});

it('C20 AddProspect non connecté est refusé sans écriture', function () {
    $w = workflowWorld();
    $before = workflowState();
    expectRouteExists(jsonAction($w->presc, 'AddProspect', []), 'AddProspect');

    $response = jsonAction(null, 'AddProspect', prospectPayload($w->projet->projet_id, ['prescripteur_select' => $w->presc->id]));

    expect($response->getStatusCode())->toBeIn([302, 401])
        ->and(workflowState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C21 <script>, \' et \\ sont stockés tels quels et réaffichés échappés', function () {
    $w = workflowWorld();
    $admin = $w->corim;
    $evil = "<script>alert('x')</script> O'Hara \\";

    $this->actingAs($admin)->post('/gestion_clients/ajouter_client', clientValues(['nom' => $evil, 'observation' => $evil]));
    $client = DB::table('clients')->first();
    expect(decoded($client->nom))->toBe($evil)
        ->and(decoded($client->observation))->toBe($evil);
    $form = $this->actingAs($admin)->get("/gestion_clients/edition_client/$client->idclient");
    expect((string) $form->getContent())->not->toContain("<script>alert('x')</script>")
        ->and(pageText($form))->toContain($evil);
    expect(decoded(searchClientsName($admin)))->toBe($evil);

    DB::table('d_prospects')->delete();
    jsonAction($w->presc, 'AddProspect', prospectPayload($w->projet->projet_id, ['f_new_prospect_name' => $evil, 'f_new_prospect_sms' => $evil]));
    $prospect = DB::table('d_prospects')->first();
    expect(decoded($prospect->nomprospect))->toBe("Mme & M. $evil Louis")
        ->and(decoded($prospect->smspromoteur))->toBe($evil);
    $page = $this->actingAs($admin)->get('/ask_denonciations');
    expect((string) $page->getContent())->not->toContain("<script>alert('x')</script>")
        ->and(pageText($page))->toContain($evil);
});

function searchClientsName(object $admin): ?string
{
    return jsonAction($admin, 'SearchClients', ['f_recherche_client_search' => 'Hara', 'f_recherche_client_type' => '1'])->json('call.0.nom');
}
