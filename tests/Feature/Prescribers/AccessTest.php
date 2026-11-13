<?php

use Illuminate\Support\Facades\DB;

const PRESCRIBER_TABLES = ['user', 'privileges', 'acces', 'acces_lot'];

it('C1 les pages promoteurs sans session redirigent vers /connexion', function () {
    $user = makeCommercial();

    foreach (PRESCRIBER_PAGES as $url) {
        $url = sprintf($url, $user->id);
        $response = $this->get($url);
        expect($response->isRedirect(url('/connexion')))->toBeTrue($url)
            ->and($response->getContent())->not->toContain('@exemple.test');
    }
});

it('C2 un commercial est redirigé vers / sans donnée de compte et son POST modifier n\'a aucun effet', function () {
    $admin = makeAdmin(['email' => 'admin@exemple.test']);
    $commercial = makeCommercial();
    $before = tablesState(PRESCRIBER_TABLES);

    foreach (PRESCRIBER_PAGES as $url) {
        $url = sprintf($url, $admin->id);
        $response = $this->actingAs($commercial)->get($url);
        expect($response->isRedirect(url('/')))->toBeTrue($url)
            ->and($response->getContent())->not->toContain('admin@exemple.test')->not->toContain('Gestion des prescripteurs');
    }
    $response = $this->actingAs($commercial)->post("/gestion_promoteurs/modifier/$admin->id", editPayload($admin, ['f_edit_promoteur_email' => 'pirate@exemple.test', 'f_edit_promoteur_pwd' => 'pirate']));
    expect($response->isRedirect(url('/')))->toBeTrue()
        ->and(tablesState(PRESCRIBER_TABLES))->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C3 anonyme ou commercial sur AddPromoteur, DeletePromoteur, TrashPromoteur, ActivPromoteur : refus sans écriture', function () {
    $admin = makeAdmin();
    $target = makeCommercial(['statut' => 0]);
    $active = makeCommercial();
    $commercial = makeCommercial();
    foreach (PRESCRIBER_ACTIONS as $action) {
        expectRouteExists(jsonAction($admin, $action, []), $action);
    }
    $before = tablesState(PRESCRIBER_TABLES);
    $payloads = [
        'AddPromoteur' => prescriberPayload(['f_add_promoteur_statut' => '1']),
        'DeletePromoteur' => ['key' => $active->id],
        'TrashPromoteur' => ['key' => $active->id],
        'ActivPromoteur' => ['key' => $target->id],
    ];

    foreach ([null, $commercial] as $user) {
        foreach ($payloads as $action => $data) {
            $response = jsonAction($user, $action, $data);
            expect($response->getStatusCode())->toBeIn([302, 401, 403], $action)
                ->and($response->getContent())->not->toContain('errors');
        }
    }

    expect(DB::table('user')->where('pseudo', 'nouveau')->exists())->toBeFalse()
        ->and(sentMails())->toBeEmpty();
    expect(tablesState(PRESCRIBER_TABLES))->toBe($before);
});

it('C29 ?callback=x sur une action JSON ne produit pas de JSONP', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->post('/index.php/json/DeletePromoteur/?callback=x', ['key' => 999999]);

    expectRouteExists($response, 'DeletePromoteur');
    expect($response->getContent())->toBe('{"errors":0}')
        ->and((string) $response->headers->get('Content-Type'))->toMatch('#^(application/json|text/html)#');
});

it('C30 tout POST du module sans jeton CSRF valide est rejeté en 419', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    foreach (PRESCRIBER_ACTIONS as $action) {
        expectRouteExists(jsonAction($admin, $action, []), $action);
    }
    $before = tablesState(PRESCRIBER_TABLES);
    withoutTestingCsrfBypass();

    $posts = [
        '/index.php/json/AddPromoteur/' => prescriberPayload(),
        '/index.php/json/DeletePromoteur/' => ['key' => $user->id],
        '/index.php/json/TrashPromoteur/' => ['key' => $user->id],
        '/index.php/json/ActivPromoteur/' => ['key' => $user->id],
        "/gestion_promoteurs/modifier/$user->id" => editPayload($user, ['f_edit_promoteur_name' => 'CHANGE']),
        '/gestion_promoteurs' => ['type' => '0', 'projects' => [1]],
    ];
    foreach ($posts as $url => $data) {
        expect($this->actingAs($admin)->post($url, $data)->getStatusCode())->toBe(419, $url);
    }
    expect(tablesState(PRESCRIBER_TABLES))->toBe($before);
});
