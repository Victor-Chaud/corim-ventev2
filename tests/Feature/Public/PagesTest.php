<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function legacyMentionsText(): string
{
    $source = file_get_contents(__DIR__.'/../../../../OLD/application/views/V_Mentions.php');
    $source = preg_replace('#^<\?php defined.*?\?>\s*#', '', $source);
    $source = preg_replace('#<\?php\s*\$nom.*?\?>#s', '', $source);
    $source = strtr($source, [
        '<?php echo $nom; ?>' => 'Corim promotion',
        '<?php echo $adr; ?>' => 'Immeuble "Le Cristal" 1475 av. Albert Einstein  ',
        "<?php echo \$cp.' '.\$ville; ?>" => '34000 MONTPELLIER',
        "<?php echo \$capital.' - RCS: '.\$rcs; ?>" => 'XXXXXX - RCS: Marmande 316 599 042',
        '<?php echo $tel; ?>' => '04 99 58 86 86',
        '<?php echo $dir; ?>' => 'DE LECUBARRI Rémy',
    ]);
    expect($source)->not->toContain('<?php');

    return $source;
}

function squash(string $html): string
{
    return trim(preg_replace(['/\s+/u', '/>\s</'], [' ', '><'], $html));
}

it('C16 /cgu connecté : titre « Conditions générales de vente » et contenu legacy', function () {
    $response = $this->actingAs(makeCommercial())->get('/cgu');

    $response->assertOk();
    expect(pageText($response))->toContain('<title>Conditions générales de vente</title>')
        ->toContain("<h3>.Conditions générales d'utilisation</h3><br />");
});

it('C17 /mentions_legales connecté : titre vide et texte identique à V_Mentions.php', function () {
    $response = $this->actingAs(makeCommercial())->get('/mentions_legales');

    $response->assertOk();
    expect(pageText($response))->toContain('<title></title>')
        ->and(squash(pageText($response)))->toContain(squash(legacyMentionsText()));
});

it('C18 /cgu et /mentions_legales sans session redirigent vers /connexion', function () {
    foreach (['/cgu', '/mentions_legales'] as $url) {
        expect($this->get($url)->isRedirect(url('/connexion')))->toBeTrue($url);
    }
});

it('C20 /app sans session : page DISPONIBILITES, cases T1-T6, select budget à 6 options, tableau #result caché', function () {
    $response = $this->get('/app');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>DISPONIBILITES</title>')
        ->toContain('<form id="form-special"')
        ->toContain('<input type="hidden" name="action" value="app" />')
        ->toContain('<input type="hidden" name="projet" id="projet" value="" />')
        ->toMatch('#<div class="col-xs-12 col-md-12 col-lg-12 form-group hide" id="result-box">\s*<table class="table table-bordered" id="result">#');
    foreach (range(1, 6) as $n) {
        expect($text)->toContain('<input type="checkbox" class="styled" id="checkbox-t'.$n.'" value="T'.$n.'" name="typologie[]">');
    }
    expectInOrder($text, ['<option value="0">Tous critères</option>', '<option value="1">- de 150 000 €</option>', '<option value="2">150 000 à 200 000 €</option>', '<option value="3">200 000 à 250 000 €</option>', '<option value="4">250 000 à 300 000 €</option>', '<option value="5">+ 300 000 €</option>']);
    expectInOrder($text, ['<th>TYPE</th>', '<th>REF</th>', '<th>SUPERFICIE</th>', '<th>PRIX</th>', '<th>EXPOSITION</th>', '<th>PLAN</th>']);
    expect($text)->not->toContain('navbar');
});

it('C21 /app?project= soumet firstsearch?action=app qui renvoie MyFirstCallback({...}) sans var_dump', function () {
    $program = makeProgram(['projet_titre' => 'APP TEST']);
    makeLot($program->projet_id, ['type' => 'T3', 'numero' => 'A 12']);
    $page = (string) $this->get('/app?project='.$program->projet_id)->assertOk()->getContent();
    expect($page)->toMatch('#<script src="[^"]*/medias/js/app\.js"></script>#');

    $js = file_get_contents(public_path('medias/js/app.js'));
    expect($js)->toContain('getUrlVars()["project"]')->toContain('index.php/firstsearch')->toContain("jsonpCallback: 'MyFirstCallback'");

    $response = $this->get('/index.php/firstsearch?action=app&projet='.$program->projet_id.'&budget=0&typologie[]=T3');
    $body = (string) $response->getContent();
    expect($body)->not->toContain('array(')->not->toContain('string(');
    $data = json_decode(jsonpUnwrap($body, 'MyFirstCallback'), true);
    expect($data['errors'])->toBe(1)
        ->and(array_column($data['program'], 'numero'))->toBe(['A 12']);
});

it('C22 /app ne charge aucune ressource en http://', function () {
    $html = (string) $this->get('/app')->assertOk()->getContent();

    preg_match_all('#(?:src|href)="([^"]+)"#', $html, $m);
    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $url) {
        expect($url)->not->toStartWith('http://');
    }
    expect(file_get_contents(public_path('medias/js/app.js')))->not->toContain('http://');
});

it('C23 une URL inconnue renvoie 404 avec le rendu de 404.html, sans trace technique', function () {
    $user = makeCommercial();
    foreach ([null, $user] as $who) {
        app('auth')->forgetGuards();
        $response = ($who ? $this->actingAs($who) : $this)->get('/page-qui-n-existe-pas');
        $response->assertNotFound();
        expect(pageText($response))->toContain('<title>404</title>')
            ->toContain('<h3>404, the file you requested does not exist!</h3>')
            ->toContain('background-color:#444')
            ->not->toContain('Kohana')->not->toContain('vendor/')->not->toContain('Symfony');
    }
});

it('C24 une erreur serveur affiche une page générique sans SQL ni chemin', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test_erreur_500', fn () => DB::select('SELECT * FROM table_inexistante_corim'));

    $response = $this->get('/_test_erreur_500');

    expect($response->getStatusCode())->toBe(500)
        ->and((string) $response->getContent())->not->toContain('table_inexistante_corim')->not->toContain('SQLSTATE')
        ->not->toContain(base_path())->not->toContain('Stack trace');
});

it('C25 le mode maintenance affiche la page de maintenance.html', function () {
    Route::get('/_test_maintenance', fn () => abort(503));

    $response = $this->get('/_test_maintenance');

    expect($response->getStatusCode())->toBe(503);
    $text = pageText($response);
    expect($text)->toContain('<title>Maintenance en cours</title>')
        ->toContain('<h1>Maintenance en cours</h1>')
        ->toContain('<p>Nous sommes désolé pour le désagrément, nous effectuons actuellement des travaux de maintenance.</p>')
        ->toContain('<p>— Corim Promotion</p>')
        ->toContain('a { color: #dc8100; text-decoration: none; }');
});

it('C26 /administration et Ajouter_Appartement → 404, aucune écriture', function () {
    $before = tablesState(['appartements', 'villes']);

    foreach ([null, makeAdmin()] as $who) {
        app('auth')->forgetGuards();
        $client = $who ? $this->actingAs($who) : $this;
        $client->get('/administration')->assertNotFound();
        $client->get('/administration/Ajouter_Appartement')->assertNotFound();
        $client->post('/administration/Ajouter_Appartement', ['ville' => 'PIRATE', 'type' => 'T2'])->assertNotFound();
    }

    expect(tablesState(['appartements', 'villes']))->toBe($before);
});

it('C27 /welcome/shutdown et les méthodes internes → 404, la session reste valide', function () {
    $user = makeCommercial();
    $login = $this->post('/connexion', ['action' => 'connexion', 'identifiant' => $user->pseudo, 'password' => 'secret']);
    expectRouteExists($login, 'connexion');

    foreach (['/welcome/shutdown', '/cgu/shutdown', '/messagerie/shutdown', '/welcome/isConnected', '/welcome/isAdmin', '/welcome/KillThisFuckinApostrophe', '/welcome/Get_NumberOfMessage/1', '/welcome/Get_NumberOfMessageAdmin/1', '/welcome/ToolbarActif'] as $url) {
        withSessionOf($login)->get($url)->assertNotFound();
    }
    withSessionOf($login)->get('/')->assertOk();
});

it('C28 /rechercher, /rechercher/par_residence, /rechercher/par_lot → 404 pour un connecté', function () {
    $user = makeCommercial();

    foreach (['/rechercher', '/rechercher/par_residence', '/rechercher/par_lot', '/x/rechercher-par-residence', '/x/rechercher-par-lot'] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound();
    }
});

it('C29 /application, /system, /modules et les *.csv ne sont pas servis', function () {
    expectRouteExists($this->get('/medias/programmes/baa692ce3ec9e7dffe643f89936729cb/plans/plan-B202.pdf'), 'medias');

    foreach (['/application/config/config.php', '/application/config/database.php', '/system/core/Kohana.php', '/modules/auth/init.php', '/medias/clients/import.csv', '/medias/export/mydata.csv', '/medias/csv/import.csv', '/medias/programmes/import.csv', '/.env', '/storage/logs/laravel.log'] as $url) {
        expect($this->get($url)->getStatusCode())->toBeIn([403, 404], $url);
    }
});
