<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const REMOVED_URLS = [
    '/gestion_clients',
    '/gestion_clients/index',
    '/gestion_clients/ajouter_client',
    '/gestion_clients/ajouter_clients',
    '/gestion_clients/uploadify',
    '/gestion_clients/edition_client/1',
    '/gestion_clients/supprimer_client/1',
    '/gestion_clients/telecharger_clients',
    '/json/SearchClients',
    '/agenda',
    '/agenda/show/2026-01-01',
    '/emailing',
    '/emailing/index',
    '/json/SearchClientEmailing',
];

it('L5 les URL de la gestion des clients, de l\'agenda et de l\'emailing répondent 404 en GET et en POST pour un admin', function () {
    $admin = makeAdmin();

    foreach (REMOVED_URLS as $url) {
        foreach (['get', 'post'] as $method) {
            app('auth')->forgetGuards();
            expect($this->actingAs($admin)->$method($url)->getStatusCode())->toBe(404, "$method $url");
        }
    }
});

it('L5 le menu admin ne propose plus Clients, Emailing ni Prospects', function () {
    $response = $this->actingAs(makeAdmin())->get('/gestion_programmes')->assertOk();

    $response->assertSee('emails">Emails', false)
        ->assertDontSee('gestion_prospects', false)
        ->assertDontSee('gestion_clients', false)
        ->assertDontSee('>Clients<', false)
        ->assertDontSee('/emailing', false)
        ->assertDontSee('>Emailing<', false);
});

it('L5 /medias/clients n\'est plus servi', function () {
    $this->get('/medias/clients/import.csv')->assertNotFound();
    $this->actingAs(makeAdmin())->get('/medias/clients/import.csv')->assertNotFound();
});

it('L5 les tables clients, agenda et emailing restent en base', function () {
    foreach (['clients', 'agenda', 'emailing'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});

it('l\'envoi de message ne propose plus les clients et n\'envoie rien à la table clients', function () {
    legacyMailBase();
    $admin = corimAdmin();
    DB::table('clients')->insert(['date' => '01/01/2012', 'source' => '', 'projets' => '', 'civilite' => '', 'nom' => 'ARCHIVE', 'adresse' => '', 'tel' => '', 'email' => 'archive@exemple.test', 'type_achat' => '', 'type_logement' => '', 'budget' => '', 'observation' => '', 'reponse' => '']);

    $form = (string) $this->actingAs($admin)->get('/message/envoyer')->assertOk()->getContent();
    expect($form)->not->toContain('value="client"')->not->toContain('archive@exemple.test');

    $this->actingAs($admin)->post('/message/envoyer', ['objet' => 'X', 'message' => 'Y', 'method' => 'client', 'client_select' => 'all'])->assertOk();
    expect(sentMails())->toBeEmpty()
        ->and(DB::table('jobs')->count())->toBe(0);
});
