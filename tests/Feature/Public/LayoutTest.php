<?php

use Illuminate\Support\Facades\DB;

const ADMIN_MENU = ['gestion_programmes">Programmes', 'gestion_promoteurs">Prescripteurs', 'gestion_privilege">Privilèges', 'emails">Emails', 'flash_info">Flash info'];

const COMMON_MENU = ['denonciations">Dénonciations', 'messagerie">Messagerie', 'contact">Contacter Corim', 'logout">Déconnexion'];

function headOf($response): string
{
    $html = (string) $response->getContent();

    return substr($html, 0, (int) strpos($html, '</head>'));
}

it('C10 menu admin : 5 entrées admin dans l\'ordre puis Dénonciations, Messagerie, Contacter Corim, Déconnexion', function () {
    foreach (['/', '/cgu'] as $url) {
        $this->actingAs(makeAdmin())->get($url)->assertOk()->assertSeeInOrder([...ADMIN_MENU, ...COMMON_MENU], false);
    }
});

it('C11 menu prescripteur : Dénonciations, Messagerie, Contacter Corim, Déconnexion seulement', function () {
    $user = makeCommercial();
    foreach (['/', '/cgu'] as $url) {
        $response = $this->actingAs($user)->get($url);
        $response->assertOk()->assertSeeInOrder(COMMON_MENU, false);
        foreach (ADMIN_MENU as $entry) {
            $response->assertDontSee($entry, false);
        }
    }
});

it('C12 badge Messagerie : non-lus reçus, sujets automatiques exclus pour l\'admin, absent à 0', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $idle = makeCommercial();
    $mp = fn ($from, $to, $subject, $lu = 0) => DB::table('mp')->insert(['idemetteur' => $from, 'idreceveur' => $to, 'sujet' => $subject, 'message' => 'x', 'date' => '2026-01-01 10:00:00', 'lu' => $lu, 'cle' => sha1(uniqid('', true)), 'parent' => 0]);
    $mp($prescriber->id, $admin->id, 'Question');
    $mp($prescriber->id, $admin->id, 'Question 2');
    $mp($prescriber->id, $admin->id, 'Vous avez une demande de réservation en attente');
    $mp($prescriber->id, $admin->id, 'Lu', 1);
    $mp(999999, $admin->id, 'Émetteur supprimé');
    $mp($admin->id, $prescriber->id, 'Votre dossier a été acté');

    expect(pageText($this->actingAs($admin)->get('/')))->toContain('messagerie">Messagerie <span class="label label-info">2</span></a>');
    app('auth')->forgetGuards();
    expect(pageText($this->actingAs($prescriber)->get('/')))->toContain('messagerie">Messagerie <span class="label label-info">1</span></a>');
    app('auth')->forgetGuards();
    expect(pageText($this->actingAs($idle)->get('/')->assertOk()))->not->toContain('label-info');
});

it('C13 Verrouiller / Déverrouiller les outils seulement si SessionToolbar = 1', function () {
    $user = makeAdmin();

    $without = pageText($this->actingAs($user)->get('/')->assertOk());
    expect($without)->not->toContain('Verrouiller les outils')->not->toContain('Déverrouiller les outils');

    $with = $this->actingAs($user)->withSession(['SessionToolbar' => '1'])->get('/cgu');
    $with->assertOk()->assertSeeInOrder(['onclick="HideToolbar();" class="item-menu-right">Déverrouiller les outils', 'onclick="ShowToolbar();" class="item-menu-right">Verrouiller les outils'], false);
});

it('C14 pied de page : footer.no-print sur les pages internes, footer sans classe sur l\'accueil', function () {
    $user = makeCommercial();

    $home = pageText($this->actingAs($user)->get('/')->assertOk());
    $inner = pageText($this->actingAs($user)->get('/cgu')->assertOk());

    expect($home)->toMatch('#<footer>\s*<div class="col-xs-12 col-sm-12 col-md-12 form-group text-center">\s*<small>Copyright© Corim Promotion - Tous droits réservés</small>#u')
        ->and($inner)->toMatch('#<footer class="no-print">\s*<div class="col-xs-12 col-sm-12 col-md-12 form-group text-center">\s*<small>Copyright© Corim Promotion - Tous droits réservés</small>#u');
});

it('C15 le <head> charge les CSS/JS dans l\'ordre legacy, CSS conditionnelle selon le segment 1', function () {
    $admin = corimAdmin();
    $program = makeProgram();
    $assets = ['bootstrap.min.css', 'bootstrap-select.min.css', 'uestrial', 'print.css', 'print-preview.css', 'jquery-ui-1.9.0.custom.min.css', 'jquery-3.7.1', 'jquery.browser.js', 'bootstrap.min.js', 'jquery-ui.min.js', 'bootstrap-select.min.js', 'defaults-fr_FR.min.js', 'ckeditor.js', 'jquery.tablesorter.combined.js', 'theme.green.css', 'colorbox.css', 'jquery.colorbox-min.js'];
    $tail = ['jquery.confirm.css', 'jquery.confirm.js', 'global.css', 'jquery.print-preview.js', 'global.js'];
    $conditional = ['/messagerie' => ['messagerie.css'], '/contact' => ['contact.css'], "/programme/show/$program->projet_titre_key" => ['flexigrid.pack.css', 'flexigrid.pack.js'], '/gestion_programmes' => ['jquery.jscrollpane.css', 'jquery.mousewheel.js', 'jquery.jscrollpane.min.js']];

    foreach ($conditional as $url => $own) {
        $head = headOf($this->actingAs($admin)->get($url)->assertOk());
        expectInOrder($head, [...$assets, ...$own, ...$tail]);
        foreach ($conditional as $otherUrl => $otherAssets) {
            if ($otherUrl !== $url) {
                expect($head)->not->toContain($otherAssets[0], "$url / $otherUrl");
            }
        }
    }
});

it('C19 les URL préfixées /index.php/ répondent comme sans préfixe', function () {
    $user = makeCommercial();

    foreach (['/cgu', '/mentions_legales', '/welcome', '/'] as $url) {
        $strip = fn ($response) => preg_replace('#<meta name="csrf-token" content="[^"]*">#', '', pageText($response->assertOk()));
        expect($strip($this->actingAs($user)->get('/index.php'.rtrim($url, '/'))))->toBe($strip($this->actingAs($user)->get($url)), $url);
    }
    auth()->logout();
    app('auth')->forgetGuards();
    expect($this->get('/index.php/cgu')->isRedirect(url('/connexion')))->toBeTrue();
});
