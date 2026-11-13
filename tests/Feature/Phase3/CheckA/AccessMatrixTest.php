<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

const CHECKA_PUBLIC = [
    '#^(index\.php/)?(firstsearch|search)(/|$)#',
    '#^api/v1/#',
    '#^medias/#',
    '#^connexion(/deconnection|/reinitialisation/.*)?$#',
    '#^logout$#',
    '#^app/#',
    '#^json/(CheckIfExist|CheckQuestion|CheckAnswer|NewAccountSetting|GenerateNewAccountSetting)(/|$)#',
];

const CHECKA_COMMERCIAL = [
    '#^/$#',
    '#^welcome/#',
    '#^(ajouter_lot|ajouter_lots|ajouter_plan|programme)$#',
    '#^programme/show/\{key\}$#',
    '#^(ask_denonciations|ask_options|ask_reservations|denonciations|ventes|refused_ask|cgu|contact|mentions_legales|rechercher)/#',
    '#^(options|reservations)/(\{index\?\}|refused|refusees)$#',
    '#^message/(\{index\?\}|repondre/\{cle\})$#',
    '#^messagerie/#',
    '#^json/(AddOption|AddReservation|AddProspect|SearchProspect|JsonDatagrid/\{idprojet\}/\{iduser\?\}|ShowToolbar|HideToolbar)$#',
];

const CHECKA_IGNORED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

function checkAClass(string $uri): string
{
    foreach (CHECKA_PUBLIC as $pattern) {
        if (preg_match($pattern, $uri)) {
            return 'public';
        }
    }
    foreach (CHECKA_COMMERCIAL as $pattern) {
        if (preg_match($pattern, $uri)) {
            return 'commercial';
        }
    }

    return 'admin';
}

function checkARoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'index.php/'))
        ->map(fn (RouteDefinition $route) => ['uri' => $route->uri(), 'methods' => $route->methods(), 'class' => checkAClass($route->uri())])
        ->values()->all();
}

function checkAUrl(string $uri, object $w): string
{
    $url = '/'.ltrim(strtr($uri, [
        '{key}-{idlot}' => $w->program->projet_titre_key.'-'.$w->lots['libre']->id,
        '{key}' => $w->program->projet_titre_key,
        '{idprojet}' => (string) $w->program->projet_id,
        '{iduser?}' => (string) $w->prescriber->id,
        '{id?}' => (string) $w->prescriber->id,
        '{id}' => (string) $w->target,
        '{cle}' => $w->cle,
        '{index?}' => 'index',
        '{action?}' => 'index',
        '{any?}' => 'calendar',
    ]), '/');

    return $url === '/' ? '/' : $url;
}

function checkAState(): string
{
    $tables = array_diff(array_column(array_filter(Schema::getTables(), fn ($t) => $t['schema'] === DB::getDatabaseName()), 'name'), CHECKA_IGNORED_TABLES);
    sort($tables);

    return md5(json_encode(array_map(fn ($table) => DB::table($table)->get()->all(), $tables)));
}

function checkAPayload(object $w): array
{
    return [
        'key' => $w->program->projet_titre_key, 'user' => $w->prescriber->id, 'projet' => [$w->program->projet_id], 'lot' => [$w->lots['libre']->id],
        'idlot' => $w->lots['libre']->id, 'idprojet' => $w->program->projet_id, 'project' => $w->program->projet_id, 'url' => '/medias/programmes/'.$w->program->projet_titre_key.'/plans/plan-LIBRE-1.pdf',
        'action' => 'project', 'titre' => 'PIRATE', 'livraison' => '1T2030', 'ville' => 'X', 'dept' => '34', 'description' => 'x', 'nom' => 'PIRATE', 'name' => 'PIRATE',
        'f_add_promoteur_pseudo' => 'pirate', 'f_add_promoteur_email' => 'pirate@exemple.test', 'denonciation' => $w->prospect,
        'objet' => 'x', 'message' => 'x', 'destinataire' => [$w->prescriber->id], 'string1' => 'x',
    ];
}

function checkAWorld(): object
{
    $w = workflowWorld();
    $w->target = $w->prescriber->id;
    $w->cle = makeMessage($w->corim->id, $w->prescriber->id)->cle;
    Storage::disk('programmes')->put($w->program->projet_titre_key.'/plans/plan-LIBRE-1.pdf', '%PDF-1.4');

    return $w;
}

beforeEach(function () {
    Storage::fake('programmes');
    Mail::fake();
    Queue::fake();
});

it('chaque route de l\'application est classée public, commercial ou admin', function () {
    $routes = checkARoutes();
    $byClass = collect($routes)->groupBy('class')->map->count();

    expect(count($routes))->toBeGreaterThan(100)
        ->and($byClass['public'])->toBeGreaterThan(10)
        ->and($byClass['commercial'])->toBeGreaterThan(25)
        ->and($byClass['admin'])->toBeGreaterThan(50);
});

it('un invité ne passe sur aucune route non publique, en GET comme en POST, sans aucun effet en base', function () {
    $w = checkAWorld();
    $before = checkAState();

    foreach (checkARoutes() as $route) {
        if ($route['class'] === 'public') {
            continue;
        }
        $url = checkAUrl($route['uri'], $w);
        foreach (array_intersect(['GET', 'POST'], $route['methods']) as $method) {
            app('auth')->forgetGuards();
            $response = $method === 'GET' ? $this->get($url) : $this->post($url, checkAPayload($w));
            expect($response->isRedirect(url('/connexion')) || $response->getStatusCode() === 401)->toBeTrue("invité $method $url : ".$response->getStatusCode())
                ->and($response->getContent())->not->toContain('navbar')->not->toContain('@exemple.test');
        }
    }

    expect(checkAState())->toBe($before);
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('un commercial est renvoyé vers / sans contenu ni effet sur chaque route admin, en GET comme en POST', function () {
    $w = checkAWorld();
    $before = checkAState();

    foreach (checkARoutes() as $route) {
        if ($route['class'] !== 'admin') {
            continue;
        }
        $url = checkAUrl($route['uri'], $w);
        foreach (array_intersect(['GET', 'POST'], $route['methods']) as $method) {
            app('auth')->forgetGuards();
            $response = $method === 'GET' ? $this->actingAs($w->prescriber)->get($url) : $this->actingAs($w->prescriber)->post($url, checkAPayload($w));
            expect($response->isRedirect(url('/')))->toBeTrue("commercial $method $url : ".$response->getStatusCode())
                ->and($response->getContent())->not->toContain('navbar')->not->toContain('@exemple.test')->not->toContain('errors');
        }
    }

    expect(checkAState())->toBe($before);
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('un commercial atteint chaque route qui lui est ouverte sans erreur serveur', function () {
    $w = checkAWorld();

    foreach (checkARoutes() as $route) {
        if ($route['class'] !== 'commercial' || ! in_array('GET', $route['methods'], true)) {
            continue;
        }
        $url = checkAUrl($route['uri'], $w);
        app('auth')->forgetGuards();
        $response = $this->actingAs($w->prescriber)->get($url);
        expect($response->getStatusCode())->toBeLessThan(500, "commercial GET $url")
            ->and($response->isRedirect(url('/connexion')))->toBeFalse("commercial GET $url");
    }
});

it('un admin ouvre chaque route GET non publique sans erreur serveur', function () {
    $w = checkAWorld();

    foreach (checkARoutes() as $route) {
        if ($route['class'] === 'public' || ! in_array('GET', $route['methods'], true)) {
            continue;
        }
        $url = checkAUrl($route['uri'], $w);
        app('auth')->forgetGuards();
        $response = $this->actingAs($w->corim)->get($url);
        expect($response->getStatusCode())->toBeLessThan(500, "admin GET $url")
            ->and($response->isRedirect(url('/connexion')))->toBeFalse("admin GET $url");
    }
});

it('un commercial ne lit ni ne modifie rien hors de ses droits avec des identifiants devinés', function () {
    $w = checkAWorld();
    $other = makeCommercial(['name' => 'Autre Prescripteur', 'email' => 'autre@exemple.test']);
    $foreign = makeProgram(['projet_titre' => 'PROGRAMME INTERDIT']);
    $foreignLot = makeLot($foreign->projet_id, ['numero' => 'INTERDIT-1']);
    DB::table('acces')->insert(['iduser' => $other->id, 'idprojet' => $foreign->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $other->id, 'idlot' => $foreignLot->id]);
    $sameProgramLot = makeLot($w->program->projet_id, ['numero' => 'NONATTRIBUE-1']);
    DB::table('prospects')->insert(prospectRow($foreign->projet_id, $other->id, ['nomprospect' => 'M SECRET Prospect']));
    DB::table('d_options')->insert(requestRow($foreignLot, $other->id, ['nom' => 'M SECRET Option']) + ['iddlot' => $foreignLot->id]);
    $foreignMessage = makeMessage($w->corim->id, $other->id, ['sujet' => 'Sujet secret']);
    $before = checkAState();
    $asPrescriber = function () use ($w) {
        app('auth')->forgetGuards();

        return test()->actingAs($w->prescriber);
    };

    expect($asPrescriber()->get("/programme/show/$foreign->projet_titre_key")->isRedirect(url('/')))->toBeTrue();

    foreach (["$foreign->projet_id/$other->id", "$foreign->projet_id", $w->program->projet_id."/$other->id"] as $path) {
        $rows = $asPrescriber()->post("/index.php/json/JsonDatagrid/$path", ['page' => '1', 'rp' => '100', 'sortname' => 'id', 'sortorder' => 'asc', 'qtype' => 'numero', 'query' => '-'])->json('rows');
        expect(array_column($rows, 'id'))->not->toContain((string) $foreignLot->id)->not->toContain((string) $sameProgramLot->id);
    }

    foreach ([[$foreignLot, 'id_option', 'AddOption'], [$foreignLot, 'id_reserver', 'AddReservation'], [$sameProgramLot, 'id_option', 'AddOption'], [$sameProgramLot, 'id_reserver', 'AddReservation']] as [$lot, $field, $action]) {
        expect($asPrescriber()->post("/index.php/json/$action", optionPayload($lot, [], $field))->json('errors'))->not->toBe(1, "$action lot $lot->numero");
    }
    expect($asPrescriber()->post('/index.php/json/AddProspect', prospectPayload($foreign->projet_id))->json('errors'))->not->toBe(1);

    expect($asPrescriber()->post('/index.php/json/SearchProspect', ['f_recherche_denonciation_search' => 'SECRET', 'f_recherche_denonciation_type' => '1'])->getContent())->not->toContain('SECRET');
    expect($asPrescriber()->get('/ask_options')->getContent())->not->toContain('SECRET');
    expect($asPrescriber()->get('/denonciations')->getContent())->not->toContain('SECRET');

    expect($asPrescriber()->get("/messagerie/consulter/$foreignMessage->cle")->getStatusCode())->toBe(404)
        ->and($asPrescriber()->post("/message/repondre/$foreignMessage->cle", ['objet' => 'x', 'message' => 'x', 'destinataire' => '1'])->getStatusCode())->toBe(404);

    expect(checkAState())->toBe($before);
    Mail::assertNothingOutgoing();
});
