<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

const VERIFA_PUBLIC = [
    '#^(index\.php/)?(firstsearch|search)(/|$)#',
    '#^api/v1/#',
    '#^medias/#',
    '#^connexion(/deconnection|/reinitialisation/.*)?$#',
    '#^logout$#',
    '#^app/#',
    '#^json/(CheckIfExist|CheckQuestion|CheckAnswer|NewAccountSetting|GenerateNewAccountSetting)(/|$)#',
];

const VERIFA_COMMERCIAL = [
    '#^/$#',
    '#^welcome/#',
    '#^agenda/#',
    '#^(ajouter_lot|ajouter_lots|ajouter_plan|programme)$#',
    '#^programme/show/\{key\}$#',
    '#^(ask_denonciations|ask_options|ask_reservations|denonciations|ventes|refused_ask|cgu|contact|mentions_legales|rechercher)/#',
    '#^(options|reservations)/(\{index\?\}|refused|refusees)$#',
    '#^message/(\{index\?\}|repondre/\{cle\})$#',
    '#^messagerie/#',
    '#^json/(AddOption|AddReservation|AddProspect|SearchProspect|JsonDatagrid/\{idprojet\}/\{iduser\?\}|ShowToolbar|HideToolbar)$#',
];

const VERIFA_IGNORED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

function verifAClass(string $uri): string
{
    foreach (VERIFA_PUBLIC as $pattern) {
        if (preg_match($pattern, $uri)) {
            return 'public';
        }
    }
    foreach (VERIFA_COMMERCIAL as $pattern) {
        if (preg_match($pattern, $uri)) {
            return 'commercial';
        }
    }

    return 'admin';
}

function verifARoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'index.php/'))
        ->map(fn (RouteDefinition $route) => ['uri' => $route->uri(), 'methods' => $route->methods(), 'class' => verifAClass($route->uri())])
        ->values()->all();
}

function verifAUrl(string $uri, object $w): string
{
    $url = '/'.ltrim(strtr($uri, [
        '{key}-{idlot}' => $w->projet->projet_titre_key.'-'.$w->lots['libre']->id,
        '{key}' => $w->projet->projet_titre_key,
        '{idprojet}' => (string) $w->projet->projet_id,
        '{iduser?}' => (string) $w->presc->id,
        '{id?}' => (string) $w->presc->id,
        '{id}' => (string) $w->target,
        '{cle}' => $w->cle,
        '{index?}' => 'index',
        '{action?}' => 'index',
        '{any?}' => 'calendar',
    ]), '/');

    return $url === '/' ? '/' : $url;
}

function verifAState(): string
{
    $tables = array_diff(array_column(array_filter(Schema::getTables(), fn ($t) => $t['schema'] === DB::getDatabaseName()), 'name'), VERIFA_IGNORED_TABLES);
    sort($tables);

    return md5(json_encode(array_map(fn ($table) => DB::table($table)->get()->all(), $tables)));
}

function verifAPayload(object $w): array
{
    return [
        'key' => $w->projet->projet_titre_key, 'user' => $w->presc->id, 'projet' => [$w->projet->projet_id], 'lot' => [$w->lots['libre']->id],
        'idlot' => $w->lots['libre']->id, 'idprojet' => $w->projet->projet_id, 'project' => $w->projet->projet_id, 'url' => '/medias/programmes/'.$w->projet->projet_titre_key.'/plans/plan-LIBRE-1.pdf',
        'action' => 'project', 'titre' => 'PIRATE', 'livraison' => '1T2030', 'ville' => 'X', 'dept' => '34', 'description' => 'x', 'nom' => 'PIRATE', 'name' => 'PIRATE',
        'f_add_promoteur_pseudo' => 'pirate', 'f_add_promoteur_email' => 'pirate@exemple.test', 'denonciation' => $w->prospect,
        'objet' => 'x', 'message' => 'x', 'destinataire' => [$w->presc->id], 'string1' => 'x',
    ];
}

function verifAWorld(): object
{
    $w = workflowWorld();
    $w->target = makeClient()->idclient;
    $w->cle = makeMessage($w->corim->id, $w->presc->id)->cle;
    Storage::disk('programmes')->put($w->projet->projet_titre_key.'/plans/plan-LIBRE-1.pdf', '%PDF-1.4');

    return $w;
}

beforeEach(function () {
    Storage::fake('programmes');
    Mail::fake();
    Queue::fake();
});

it('chaque route de l\'application est classée public, commercial ou admin', function () {
    $routes = verifARoutes();
    $byClass = collect($routes)->groupBy('class')->map->count();

    expect(count($routes))->toBeGreaterThan(100)
        ->and($byClass['public'])->toBeGreaterThan(10)
        ->and($byClass['commercial'])->toBeGreaterThan(25)
        ->and($byClass['admin'])->toBeGreaterThan(50);
});

it('un invité ne passe sur aucune route non publique, en GET comme en POST, sans aucun effet en base', function () {
    $w = verifAWorld();
    $before = verifAState();

    foreach (verifARoutes() as $route) {
        if ($route['class'] === 'public') {
            continue;
        }
        $url = verifAUrl($route['uri'], $w);
        foreach (array_intersect(['GET', 'POST'], $route['methods']) as $method) {
            app('auth')->forgetGuards();
            $response = $method === 'GET' ? $this->get($url) : $this->post($url, verifAPayload($w));
            expect($response->isRedirect(url('/connexion')) || $response->getStatusCode() === 401)->toBeTrue("invité $method $url : ".$response->getStatusCode())
                ->and($response->getContent())->not->toContain('navbar')->not->toContain('@exemple.test');
        }
    }

    expect(verifAState())->toBe($before);
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('un commercial est renvoyé vers / sans contenu ni effet sur chaque route admin, en GET comme en POST', function () {
    $w = verifAWorld();
    $before = verifAState();

    foreach (verifARoutes() as $route) {
        if ($route['class'] !== 'admin') {
            continue;
        }
        $url = verifAUrl($route['uri'], $w);
        foreach (array_intersect(['GET', 'POST'], $route['methods']) as $method) {
            app('auth')->forgetGuards();
            $response = $method === 'GET' ? $this->actingAs($w->presc)->get($url) : $this->actingAs($w->presc)->post($url, verifAPayload($w));
            expect($response->isRedirect(url('/')))->toBeTrue("commercial $method $url : ".$response->getStatusCode())
                ->and($response->getContent())->not->toContain('navbar')->not->toContain('@exemple.test')->not->toContain('errors');
        }
    }

    expect(verifAState())->toBe($before);
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('un commercial atteint chaque route qui lui est ouverte sans erreur serveur', function () {
    $w = verifAWorld();

    foreach (verifARoutes() as $route) {
        if ($route['class'] !== 'commercial' || ! in_array('GET', $route['methods'], true)) {
            continue;
        }
        $url = verifAUrl($route['uri'], $w);
        app('auth')->forgetGuards();
        $response = $this->actingAs($w->presc)->get($url);
        expect($response->getStatusCode())->toBeLessThan(500, "commercial GET $url")
            ->and($response->isRedirect(url('/connexion')))->toBeFalse("commercial GET $url");
    }
});

it('un admin ouvre chaque route GET non publique sans erreur serveur', function () {
    $w = verifAWorld();

    foreach (verifARoutes() as $route) {
        if ($route['class'] === 'public' || ! in_array('GET', $route['methods'], true)) {
            continue;
        }
        $url = verifAUrl($route['uri'], $w);
        app('auth')->forgetGuards();
        $response = $this->actingAs($w->corim)->get($url);
        expect($response->getStatusCode())->toBeLessThan(500, "admin GET $url")
            ->and($response->isRedirect(url('/connexion')))->toBeFalse("admin GET $url");
    }
});

it('un commercial ne lit ni ne modifie rien hors de ses droits avec des identifiants devinés', function () {
    $w = verifAWorld();
    $other = makeCommercial(['name' => 'Autre Prescripteur', 'email' => 'autre@exemple.test']);
    $foreign = makeProjet(['projet_titre' => 'PROGRAMME INTERDIT']);
    $foreignLot = makeLot($foreign->projet_id, ['numero' => 'INTERDIT-1']);
    DB::table('acces')->insert(['iduser' => $other->id, 'idprojet' => $foreign->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $other->id, 'idlot' => $foreignLot->id]);
    $sameProgrammeLot = makeLot($w->projet->projet_id, ['numero' => 'NONATTRIBUE-1']);
    DB::table('prospects')->insert(prospectRow($foreign->projet_id, $other->id, ['nomprospect' => 'M SECRET Prospect']));
    DB::table('d_options')->insert(demandeRow($foreignLot, $other->id, ['nom' => 'M SECRET Option']) + ['iddlot' => $foreignLot->id]);
    $foreignMessage = makeMessage($w->corim->id, $other->id, ['sujet' => 'Sujet secret']);
    $before = verifAState();
    $asPresc = function () use ($w) {
        app('auth')->forgetGuards();

        return test()->actingAs($w->presc);
    };

    expect($asPresc()->get("/programme/show/$foreign->projet_titre_key")->isRedirect(url('/')))->toBeTrue();

    foreach (["$foreign->projet_id/$other->id", "$foreign->projet_id", $w->projet->projet_id."/$other->id"] as $path) {
        $rows = $asPresc()->post("/index.php/json/JsonDatagrid/$path", ['page' => '1', 'rp' => '100', 'sortname' => 'id', 'sortorder' => 'asc', 'qtype' => 'numero', 'query' => '-'])->json('rows');
        expect(array_column($rows, 'id'))->not->toContain((string) $foreignLot->id)->not->toContain((string) $sameProgrammeLot->id);
    }

    foreach ([[$foreignLot, 'id_option', 'AddOption'], [$foreignLot, 'id_reserver', 'AddReservation'], [$sameProgrammeLot, 'id_option', 'AddOption'], [$sameProgrammeLot, 'id_reserver', 'AddReservation']] as [$lot, $field, $action]) {
        expect($asPresc()->post("/index.php/json/$action", optionPayload($lot, [], $field))->json('errors'))->not->toBe(1, "$action lot $lot->numero");
    }
    expect($asPresc()->post('/index.php/json/AddProspect', prospectPayload($foreign->projet_id))->json('errors'))->not->toBe(1);

    expect($asPresc()->post('/index.php/json/SearchProspect', ['f_recherche_denonciation_search' => 'SECRET', 'f_recherche_denonciation_type' => '1'])->getContent())->not->toContain('SECRET');
    expect($asPresc()->get('/ask_options')->getContent())->not->toContain('SECRET');
    expect($asPresc()->get('/denonciations')->getContent())->not->toContain('SECRET');

    expect($asPresc()->get("/messagerie/consulter/$foreignMessage->cle")->getStatusCode())->toBe(404)
        ->and($asPresc()->post("/message/repondre/$foreignMessage->cle", ['objet' => 'x', 'message' => 'x', 'destinataire' => '1'])->getStatusCode())->toBe(404);

    expect(verifAState())->toBe($before);
    Mail::assertNothingOutgoing();
});
