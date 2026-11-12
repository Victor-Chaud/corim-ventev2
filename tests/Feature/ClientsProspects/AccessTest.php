<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('clients'));

it('C1 les pages clients et prospects sans session redirigent vers /connexion', function () {
    $client = makeClient();

    foreach (clientPages($client->idclient) as $url) {
        $response = $this->get($url);
        expect($response->isRedirect(url('/connexion')))->toBeTrue($url)
            ->and($response->getContent())->not->toContain('claire@exemple.test');
    }
});

it('C2 un prescripteur est redirigé vers l\'accueil sans écriture ni export', function () {
    $client = makeClient();
    $commercial = makeCommercial();
    $before = tablesState(['clients']);

    foreach (clientPages($client->idclient) as $url) {
        foreach (['get', 'post'] as $method) {
            $response = $this->actingAs($commercial)->$method($url, $method === 'post' ? clientValues(['nom' => 'PIRATE']) : []);
            expect($response->isRedirect(url('/')))->toBeTrue("$method $url")
                ->and($response->getContent())->not->toContain('claire@exemple.test')->not->toContain('date;source')
                ->and($response->headers->get('Content-Disposition'))->toBeNull();
        }
    }

    expect(tablesState(['clients']))->toBe($before)
        ->and(DB::table('clients')->where('nom', 'PIRATE')->count())->toBe(0);
});

it('C3 json/SearchClients non connecté ou non admin est refusé sans donnée client', function () {
    makeClient();
    $data = ['f_recherche_client_search' => 'a', 'f_recherche_client_type' => '1'];
    expectRouteExists(jsonAction(makeAdmin(), 'SearchClients', $data), 'SearchClients');

    foreach ([null, makeCommercial()] as $user) {
        $response = jsonAction($user, 'SearchClients', $data);
        expect($response->getStatusCode())->toBeIn([302, 401, 403])
            ->and($response->getContent())->not->toContain('claire@exemple.test')->not->toContain('DUPONT');
    }
});
