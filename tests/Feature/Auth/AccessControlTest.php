<?php

const CONNECTED_PAGES = ['/', '/welcome', '/ask_denonciations', '/ask_options', '/ask_reservations', '/denonciations', '/options', '/reservations', '/ventes', '/messagerie', '/rechercher', '/agenda', '/contact', '/cgu', '/mentions_legales', '/message', '/refused_ask'];

const ADMIN_PAGES = ['/gestion_programmes', '/ajouter_programme', '/gestion_privilege', '/emails', '/flash_info', '/gestion_prospects', '/report', '/emailing', '/gestion_clients', '/gestion_promoteurs'];

const ADMIN_KEY_PAGES = ['/modifier_programme/show/%s', '/gerer_lots/show/%s', '/ajouter_lot/show/%s', '/ajouter_lots/show/%s', '/ajouter_plan/show/%s', '/gestion_programmes/diaporama_projet/%s'];

const JSON_ACTIONS = ['UpdateUserPrivilege', 'GetUserPrivilege', 'GetUserPrivilegeLot', 'SupprimerLot', 'DeleteAllInProject', 'ArchivageProjet', 'DesarchivageProjet', 'DeleteFile', 'DeleteDiapo', 'JsonDatagrid/1/1', 'ShowToolbar', 'HideToolbar'];

it('C12 toute page connectée sans session redirige vers /connexion sans contenu applicatif', function () {
    $key = makeProjet()->projet_titre_key;

    foreach ([...CONNECTED_PAGES, "/programme/show/$key", ...ADMIN_PAGES] as $url) {
        $response = $this->get($url);
        expect($response->isRedirect(url('/connexion')))->toBeTrue($url)
            ->and($response->getContent())->not->toContain('Corim promotion -')->not->toContain('navbar');
    }
});

it('C13 toute page admin avec un commercial redirige vers / sans effet ni contenu', function () {
    $projet = makeProjet();
    $commercial = makeCommercial();
    $before = json_encode([DB::table('projet')->get(), DB::table('acces')->get(), DB::table('acces_lot')->get()]);

    $urls = [...ADMIN_PAGES, ...array_map(fn ($u) => sprintf($u, $projet->projet_titre_key), ADMIN_KEY_PAGES)];
    foreach ($urls as $url) {
        foreach (['get', 'post'] as $method) {
            $response = $this->actingAs($commercial)->$method($url, $method === 'post' ? ['titre' => 'X', 'action' => 'project'] : []);
            expect($response->isRedirect(url('/')))->toBeTrue("$method $url")
                ->and($response->getContent())->not->toContain('Corim promotion -')->not->toContain('navbar')->not->toContain('@exemple.test');
        }
    }

    expect(json_encode([DB::table('projet')->get(), DB::table('acces')->get(), DB::table('acces_lot')->get()]))->toBe($before);
});

it('C14 toute action json/* exige une session et un jeton CSRF', function () {
    $projet = makeProjet();
    $user = makeCommercial();
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $projet->projet_id]);

    foreach (JSON_ACTIONS as $action) {
        $response = $this->post("/index.php/json/$action", ['key' => $projet->projet_titre_key, 'user' => $user->id]);
        expect($response->getStatusCode())->toBeIn([302, 401], $action);
    }
    expect((int) DB::table('projet')->value('archive'))->toBe(0)
        ->and(DB::table('acces')->count())->toBe(1);

    withoutTestingCsrfBypass();
    $response = $this->actingAs(makeAdmin())->post('/index.php/json/ArchivageProjet', ['key' => $projet->projet_titre_key]);
    expect($response->getStatusCode())->toBe(419)
        ->and((int) DB::table('projet')->value('archive'))->toBe(0);
});

it('C24 un commercial ne voit que ses programmes acces et ses lots acces_lot, grille vide sans lot', function () {
    $mine = makeProjet(['projet_titre' => 'MON PROGRAMME']);
    $notMine = makeProjet(['projet_titre' => 'AUTRE PROGRAMME']);
    $lotMine = makeLot($mine->projet_id, ['numero' => 'L1']);
    makeLot($mine->projet_id, ['numero' => 'L2']);
    $user = makeCommercial();
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $mine->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $user->id, 'idlot' => $lotMine->id]);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee("programme/show/$mine->projet_titre_key", false)
        ->assertDontSee("programme/show/$notMine->projet_titre_key", false);

    foreach (['', 'L'] as $query) {
        $rows = $this->actingAs($user)->post("/index.php/json/JsonDatagrid/$mine->projet_id/$user->id", ['page' => '1', 'rp' => '100', 'sortname' => 'numero', 'sortorder' => 'asc', 'query' => $query, 'qtype' => 'numero'])->json('rows');
        expect(array_column($rows, 'id'))->toBe([(string) $lotMine->id]);
    }

    $empty = makeCommercial();
    DB::table('acces')->insert(['iduser' => $empty->id, 'idprojet' => $mine->projet_id]);
    $this->actingAs($empty)->post("/index.php/json/JsonDatagrid/$mine->projet_id/$empty->id", ['page' => '1', 'rp' => '100', 'sortname' => 'numero', 'sortorder' => 'asc'])
        ->assertOk()->assertJsonPath('rows', []);
});

it('C25 un commercial sur un programme absent de acces reçoit 403 ou une redirection vers /', function () {
    $projet = makeProjet();
    $response = $this->actingAs(makeCommercial())->get("/programme/show/$projet->projet_titre_key");

    expect($response->getStatusCode() === 403 || $response->isRedirect(url('/')))->toBeTrue();
});

it('C29 les menus du gabarit sont rendus selon le rôle (8 entrées admin en plus)', function () {
    $adminMenu = ['gestion_programmes">Programmes', 'gestion_clients">Clients', 'gestion_prospects">Prospects', 'gestion_promoteurs">Prescripteurs', 'gestion_privilege">Privilèges', 'emailing">Emailing', 'emails">Emails', 'flash_info">Flash info'];
    $common = ['denonciations">Dénonciations', 'messagerie">Messagerie', 'contact">Contacter Corim', 'logout">Déconnexion'];

    $admin = $this->actingAs(makeAdmin())->get('/contact');
    $admin->assertOk()->assertSeeInOrder([...$adminMenu, ...$common], false);

    $commercial = $this->actingAs(makeCommercial())->get('/contact');
    $commercial->assertOk()->assertSeeInOrder($common, false);
    foreach ($adminMenu as $entry) {
        $commercial->assertDontSee($entry, false);
    }
});
