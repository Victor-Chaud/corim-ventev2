<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('programmes'));

function programItem(object $program, string $thumb = 'vignette.jpg'): string
{
    $key = $program->projet_titre_key;

    return '#<div class="item_programme">\s*<a href="[^"]*/programme/show/'.$key.'" class="vignette">\s*<img src="[^"]*medias/programmes/'.$key.'/thumb/'.preg_quote($thumb, '#').'" alt="vignette" width="120" class="img-thumbnail" />\s*</a>\s*<a class="btn btn-info btn-xs btn-block" href="[^"]*/programme/show/'.$key.'">'.preg_quote(decoded($program->projet_titre), '#').'<span>'.preg_quote(decoded($program->projet_lieu), '#').'</span></a>\s*</div>#u';
}

function withThumb(object $program): object
{
    Storage::disk('programmes')->put("$program->projet_titre_key/thumb/vignette.jpg", 'jpg');

    return $program;
}

it('C1 GET / sans session redirige vers /connexion', function () {
    expect($this->get('/')->isRedirect(url('/connexion')))->toBeTrue();
});

it('C2 /welcome et /welcome/index répondent comme /', function () {
    $admin = makeAdmin();
    $strip = fn ($response) => preg_replace('#<meta name="csrf-token" content="[^"]*">#', '', pageText($response->assertOk()));
    $home = $strip($this->actingAs($admin)->get('/'));

    foreach (['/welcome', '/welcome/index'] as $url) {
        expect($strip($this->actingAs($admin)->get($url)))->toBe($home, $url);
    }
    auth()->logout();
    app('auth')->forgetGuards();
    expect($this->get('/welcome')->isRedirect(url('/connexion')))->toBeTrue();
});

it('C3 GET / admin : 200, titre Tableau de bord, gabarit accueil à 3 colonnes', function () {
    $response = $this->actingAs(makeAdmin())->get('/');

    $response->assertOk();
    expect(pageText($response))->toContain('<title>Corim promotion - Tableau de bord</title>')
        ->toContain('<div class="col-xs-6 col-sm-5 col-md-3">')
        ->toContain('<div class="col-xs-6 col-sm-7 col-md-9">')
        ->toContain('<div class="col-xs-12 col-sm-12 col-md-12">')
        ->toContain('<div class="list-group">');
});

it('C4 accueil admin : une vignette par programme non archivé, projet_id décroissant, lien et image 120 px', function () {
    $a = withThumb(makeProgram(['projet_titre' => 'ALPHA', 'projet_lieu' => 'SETE']));
    $b = withThumb(makeProgram(['projet_titre' => 'BETA', 'projet_lieu' => 'NIMES']));
    $archived = withThumb(makeProgram(['projet_titre' => 'ARCHIVE', 'archive' => 1]));

    $text = pageText($this->actingAs(makeAdmin())->get('/')->assertOk());

    expect($text)->toMatch(programItem($a))->toMatch(programItem($b))
        ->not->toContain("programme/show/$archived->projet_titre_key")
        ->and(substr_count($text, 'class="item_programme"'))->toBe(2);
    expectInOrder($text, ['BETA<span>', 'ALPHA<span>']);
});

it('C5 accueil prescripteur : une vignette par ligne acces, projet_id décroissant, archivés compris comme l\'ancien code', function () {
    $prescriber = makeCommercial();
    $a = withThumb(makeProgram(['projet_titre' => 'ALPHA']));
    $b = withThumb(makeProgram(['projet_titre' => 'BETA', 'archive' => 1]));
    $other = withThumb(makeProgram(['projet_titre' => 'GAMMA']));
    DB::table('acces')->insert([['iduser' => $prescriber->id, 'idprojet' => $a->projet_id], ['iduser' => $prescriber->id, 'idprojet' => $b->projet_id]]);

    $text = pageText($this->actingAs($prescriber)->get('/')->assertOk());

    expect($text)->toMatch(programItem($a))->toMatch(programItem($b))
        ->not->toContain("programme/show/$other->projet_titre_key")
        ->and(substr_count($text, 'class="item_programme"'))->toBe(2);
    expectInOrder($text, ['BETA<span>', 'ALPHA<span>']);
});

it('C6 les 7 compteurs du tableau de bord comptent les lignes des listes (globaux admin, filtrés prescripteur)', function () {
    $w = workflowWorld();
    $other = makeCommercial();
    $lot = makeLot($w->program->projet_id, ['statut' => 'act', 'vendeur' => $other->id]);
    DB::table('d_options')->insert(requestRow($lot, $other->id) + ['iddlot' => $lot->id]);
    DB::table('ventes')->insert(['iduser' => $other->id, 'idlot' => $lot->id, 'date' => '2026-02-01 10:00:00', 'statut' => 1, 'date_resa' => '2026-01-05 10:00:00']);

    $admin = dashboardCounters(pageText($this->actingAs($w->corim)->get('/')->assertOk()));
    expect(array_map(fn ($c) => $c[0] ?? null, $admin))->toBe([
        'LOTS ACTÉ' => 2,
        "DEMANDES D'OPTIONS" => 2,
        'OPTIONS' => 1,
        'DEMANDES DE RÉSERVATIONS' => 1,
        'RÉSERVATIONS' => 1,
        'DEMANDES DE DÉNONCIATIONS' => 1,
        'DÉNONCIATIONS' => 1,
    ]);

    $mine = dashboardCounters(pageText($this->actingAs($w->prescriber)->get('/')->assertOk()));
    expect(array_map(fn ($c) => $c[0] ?? null, $mine))->toBe(array_fill_keys(array_keys($mine), 1));
});

it('C7 compteur à 0 → javascript:void(0), compteur > 0 → lien vers la page cible', function () {
    $w = workflowWorld();
    $targets = ['LOTS ACTÉ' => 'ventes', "DEMANDES D'OPTIONS" => 'ask_options', 'OPTIONS' => 'options', 'DEMANDES DE RÉSERVATIONS' => 'ask_reservations', 'RÉSERVATIONS' => 'reservations', 'DEMANDES DE DÉNONCIATIONS' => 'ask_denonciations', 'DÉNONCIATIONS' => 'denonciations'];

    $counters = dashboardCounters(pageText($this->actingAs($w->prescriber)->get('/')));
    foreach ($targets as $label => $target) {
        expect($counters[$label][1])->toMatch('#^(https?://[^/]+)?/'.$target.'$#', $label);
    }

    $none = dashboardCounters(pageText($this->actingAs(makeCommercial())->get('/')));
    foreach ($none as $label => $counter) {
        expect($counter)->toBe([0, 'javascript:void(0)'], $label);
    }
});

it('C8 demandes > 0 : classe list-group-item-warning et icône glyphicon-exclamation-sign, rien à 0', function () {
    $w = workflowWorld();

    $text = pageText($this->actingAs($w->prescriber)->get('/'));
    foreach (["DEMANDES D'OPTIONS", 'DEMANDES DE RÉSERVATIONS', 'DEMANDES DE DÉNONCIATIONS'] as $label) {
        expect($text)->toMatch('#class="list-group-item list-group-item-warning">\s*<span class="badge">1</span>\s*<span aria-hidden="true" class="glyphicon glyphicon-exclamation-sign"></span> '.preg_quote($label, '#').'#u', $label);
    }

    $empty = pageText($this->actingAs(makeCommercial())->get('/'));
    expect($empty)->not->toContain('list-group-item-warning')->not->toContain('glyphicon-exclamation-sign');
});

it('C9 le lien RAPPORT vers /report n\'est présent que pour l\'admin', function () {
    expect(pageText($this->actingAs(makeAdmin())->get('/')))->toMatch('#<a href="[^"]*/report" class="list-group-item">\s*RAPPORT\s*</a>#');

    app('auth')->forgetGuards();
    expect(pageText($this->actingAs(makeCommercial())->get('/')->assertOk()))->not->toContain('RAPPORT')->not->toMatch('#href="[^"]*/report"#');
});

it('C30 un projet_titre contenant <script> est affiché échappé sur l\'accueil', function () {
    $program = withThumb(makeProgram(['projet_titre' => '<script>alert(1)</script>', 'projet_titre_key' => md5('xss')]));

    $raw = (string) $this->actingAs(makeAdmin())->get('/')->assertOk()->getContent();

    expect($raw)->not->toContain('<script>alert(1)</script>')
        ->toContain("programme/show/$program->projet_titre_key");
});
