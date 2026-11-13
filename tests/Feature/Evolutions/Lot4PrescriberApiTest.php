<?php

use App\Http\Controllers\Evolutions\PrescriberApiController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('programmes');
    Cache::flush();
});

function EVO_KEY_GENERATE(int $id): string
{
    return "/gestion_promoteurs/cle/$id";
}

function EVO_KEY_REVOKE(int $id): string
{
    return "/gestion_promoteurs/cle/$id/revoquer";
}

function evoGenerateKey(User $admin, User $prescriber): TestResponse
{
    app('auth')->forgetGuards();

    return test()->actingAs($admin)->followingRedirects()->post(EVO_KEY_GENERATE($prescriber->id));
}

function evoRevokeKey(User $admin, User $prescriber): TestResponse
{
    app('auth')->forgetGuards();

    return test()->actingAs($admin)->followingRedirects()->post(EVO_KEY_REVOKE($prescriber->id));
}

function evoKey(User $prescriber): string
{
    $page = (string) evoGenerateKey(makeAdmin(), $prescriber)->getContent();
    expect(preg_match('/[?&](?:amp;)?cle=([A-Za-z0-9_\-|.]{20,})/', $page, $m))->toBe(1, 'clé ou lien personnel absent de la réponse de génération');
    app('auth')->forgetGuards();

    return $m[1];
}

function evoApi(string $path, array $query = [], array $headers = []): TestResponse
{
    app('auth')->forgetGuards();
    test()->flushHeaders();

    return test()->withHeaders($headers)->get($path.($query ? '?'.http_build_query($query) : ''));
}

function evoApiJson(string $path, string $key, array $query = []): array
{
    $response = evoApi($path, $query + ['format' => 'json'], ['Authorization' => "Bearer $key", 'Accept' => 'application/json']);
    $response->assertOk();
    $json = $response->json();

    return array_is_list($json) ? $json : collect($json)->first(fn ($v) => is_array($v) && array_is_list($v)) ?? [];
}

function evoApiWorld(): object
{
    corimAdmin();
    $alice = makeCommercial(['name' => 'Alice Api', 'email' => 'alice@exemple.test']);
    $bruno = makeCommercial(['name' => 'Bruno Api', 'email' => 'bruno@exemple.test']);
    $p1 = makeProgram(['projet_titre' => 'RESIDENCE ALPHA', 'projet_lieu' => 'MONTPELLIER']);
    $p2 = makeProgram(['projet_titre' => 'RESIDENCE BETA', 'projet_lieu' => 'NIMES']);
    $p3 = makeProgram(['projet_titre' => 'RESIDENCE ARCHIVEE', 'projet_lieu' => 'SETE', 'archive' => 1]);
    $lots = [
        'A1' => makeLot($p1->projet_id, ['numero' => 'EVOA1', 'type' => 'T2', 'prix' => '185 000', 'ville' => 'MONTPELLIER']),
        'A2' => makeLot($p1->projet_id, ['numero' => 'EVOA2', 'type' => 'T3', 'prix' => '260 000', 'ville' => 'MONTPELLIER', 'statut' => 'opt']),
        'A3' => makeLot($p1->projet_id, ['numero' => 'EVOA3', 'type' => 'T3', 'prix' => '270 000', 'ville' => 'MONTPELLIER']),
        'B1' => makeLot($p2->projet_id, ['numero' => 'EVOB1', 'type' => 'T2', 'prix' => '150 000', 'ville' => 'NIMES']),
        'C1' => makeLot($p3->projet_id, ['numero' => 'EVOC1', 'type' => 'T2', 'prix' => '120 000', 'ville' => 'SETE']),
    ];
    foreach ([$p1, $p3] as $p) {
        DB::table('acces')->insert(['iduser' => $alice->id, 'idprojet' => $p->projet_id]);
    }
    foreach (['A1', 'A2', 'C1'] as $ref) {
        DB::table('acces_lot')->insert(['iduser' => $alice->id, 'idlot' => $lots[$ref]->id]);
    }
    DB::table('acces')->insert(['iduser' => $bruno->id, 'idprojet' => $p2->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $bruno->id, 'idlot' => $lots['B1']->id]);

    DB::table('d_prospects')->insert(prospectRow($p1->projet_id, $alice->id, ['nomprospect' => 'ALICEATTENTE']));
    DB::table('prospects')->insert(prospectRow($p1->projet_id, $alice->id, ['nomprospect' => 'ALICEVALIDEE']));
    DB::table('r_prospects')->insert(prospectRow($p1->projet_id, $alice->id, ['nomprospect' => 'ALICEREFUSEE']));
    DB::table('prospects')->insert(prospectRow($p2->projet_id, $bruno->id, ['nomprospect' => 'BRUNOVALIDEE']));

    return (object) compact('alice', 'bruno', 'p1', 'p2', 'p3', 'lots');
}

it('L4 sans clé, avec une clé inconnue ou révoquée, l\'API répond 401', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);

    foreach (['/api/v1/lots', '/api/v1/denonciations'] as $path) {
        expect(evoApi($path, ['format' => 'json'])->getStatusCode())->toBe(401, $path)
            ->and(evoApi($path, ['format' => 'json', 'cle' => 'inconnue'.str_repeat('x', 30)])->getStatusCode())->toBe(401, $path)
            ->and(evoApi($path, ['format' => 'json'], ['Authorization' => 'Bearer mauvaise'.str_repeat('y', 30)])->getStatusCode())->toBe(401, $path)
            ->and(evoApi($path, ['format' => 'json', 'cle' => $key])->getStatusCode())->toBe(200, $path);
    }

    evoRevokeKey(makeAdmin(), $w->alice);
    foreach (['/api/v1/lots', '/api/v1/denonciations'] as $path) {
        expect(evoApi($path, ['format' => 'json', 'cle' => $key])->getStatusCode())->toBe(401, $path)
            ->and(evoApi($path, ['format' => 'json'], ['Authorization' => "Bearer $key"])->getStatusCode())->toBe(401, $path);
    }
});

it('L4 générer une nouvelle clé coupe l\'ancien lien', function () {
    $w = evoApiWorld();
    $old = evoKey($w->alice);
    $new = evoKey($w->alice);

    expect($new)->not->toBe($old)
        ->and(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $old])->getStatusCode())->toBe(401)
        ->and(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $new])->getStatusCode())->toBe(200);
});

it('L4 la clé est acceptée par l\'en-tête Authorization: Bearer et par le paramètre ?cle=', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);

    $bearer = evoApi('/api/v1/lots', ['format' => 'json'], ['Authorization' => "Bearer $key"]);
    $param = evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $key]);

    $bearer->assertOk();
    $param->assertOk();
    expect($bearer->json())->toEqual($param->json());
});

it('L4 un prescripteur ne voit que les lots de programmes non archivés auxquels il a accès', function () {
    $w = evoApiWorld();

    $alice = json_encode(evoApiJson('/api/v1/lots', evoKey($w->alice)), JSON_UNESCAPED_UNICODE);
    expect($alice)->toContain('EVOA1')->toContain('EVOA2')->toContain('RESIDENCE ALPHA')
        ->not->toContain('EVOA3')
        ->not->toContain('EVOB1')->not->toContain('RESIDENCE BETA')
        ->not->toContain('EVOC1')->not->toContain('RESIDENCE ARCHIVEE');

    $bruno = json_encode(evoApiJson('/api/v1/lots', evoKey($w->bruno)), JSON_UNESCAPED_UNICODE);
    expect($bruno)->toContain('EVOB1')->not->toContain('EVOA1')->not->toContain('EVOA2');
});

it('L4 un lot sans programme autorisé n\'est pas visible même avec un droit acces_lot', function () {
    $w = evoApiWorld();
    DB::table('acces_lot')->insert(['iduser' => $w->alice->id, 'idlot' => $w->lots['B1']->id]);

    expect(json_encode(evoApiJson('/api/v1/lots', evoKey($w->alice))))->not->toContain('EVOB1');
});

it('L4 le prix est un nombre et chaque lot porte sa référence, son statut et l\'URL de son plan', function () {
    $w = evoApiWorld();
    Storage::disk('programmes')->put($w->p1->projet_titre_key.'/plans/plan-EVOA1.pdf', '%PDF-1.4');

    $rows = evoApiJson('/api/v1/lots', evoKey($w->alice));
    $a1 = collect($rows)->first(fn ($row) => str_contains(json_encode($row), 'EVOA1'));

    expect($rows)->toHaveCount(2)
        ->and($a1)->toHaveKey('prix')
        ->and($a1['prix'])->toBeIn([185000, 185000.0])
        ->and(json_encode($a1, JSON_UNESCAPED_SLASHES))->toContain('medias/programmes/'.$w->p1->projet_titre_key.'/plans/plan-EVOA1.pdf');
});

it('L4 les filtres programme, type, ville et budget restreignent la liste', function () {
    $w = evoApiWorld();
    DB::table('acces')->insert(['iduser' => $w->alice->id, 'idprojet' => $w->p2->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $w->alice->id, 'idlot' => $w->lots['B1']->id]);
    $key = evoKey($w->alice);
    $refs = fn (array $query) => collect(['EVOA1', 'EVOA2', 'EVOB1'])
        ->filter(fn ($ref) => str_contains(json_encode(evoApiJson('/api/v1/lots', $key, $query)), $ref))->values()->all();

    expect($refs([]))->toBe(['EVOA1', 'EVOA2', 'EVOB1'])
        ->and($refs(['programme' => 'ALPHA']))->toBe(['EVOA1', 'EVOA2'])
        ->and($refs(['type' => 'T3']))->toBe(['EVOA2'])
        ->and($refs(['ville' => 'nimes']))->toBe(['EVOB1'])
        ->and($refs(['minBudget' => 180000]))->toBe(['EVOA1', 'EVOA2'])
        ->and($refs(['maxBudget' => 200000]))->toBe(['EVOA1', 'EVOB1'])
        ->and($refs(['minBudget' => 160000, 'maxBudget' => 200000]))->toBe(['EVOA1']);
});

it('L4 /api/v1/denonciations ne renvoie que les dénonciations du prescripteur, en attente, validées et refusées', function () {
    $w = evoApiWorld();

    $alice = json_encode(evoApiJson('/api/v1/denonciations', evoKey($w->alice)), JSON_UNESCAPED_UNICODE);
    expect($alice)->toContain('ALICEATTENTE')->toContain('ALICEVALIDEE')->toContain('ALICEREFUSEE')->toContain('RESIDENCE ALPHA')
        ->not->toContain('BRUNOVALIDEE');

    $bruno = json_encode(evoApiJson('/api/v1/denonciations', evoKey($w->bruno)), JSON_UNESCAPED_UNICODE);
    expect($bruno)->toContain('BRUNOVALIDEE')->not->toContain('ALICE');
});

it('L4 format=csv renvoie un CSV séparé par des points-virgules, format=json du JSON avec Cache-Control no-cache', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);

    $csv = evoApi('/api/v1/lots', ['cle' => $key, 'format' => 'csv'])->assertOk();
    $body = responseBody($csv);
    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and(explode("\n", ltrim($body, "\xEF\xBB\xBF"))[0])->toContain(';')
        ->and($body)->toContain('EVOA1')->not->toContain('EVOB1');

    $json = evoApi('/api/v1/lots', ['cle' => $key, 'format' => 'json'])->assertOk();
    expect($json->headers->get('Content-Type'))->toContain('application/json')
        ->and($json->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($json->json())->toBeArray();
});

it('L4 sans format, le lien personnel ouvert dans un navigateur affiche une page HTML en noindex, non mise en cache', function () {
    $w = evoApiWorld();

    $page = evoApi('/api/v1/lots', ['cle' => evoKey($w->alice)], ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'])->assertOk();
    $content = (string) $page->getContent();

    expect($page->headers->get('Content-Type'))->toContain('text/html')
        ->and(str_contains($content, 'noindex') || str_contains((string) $page->headers->get('X-Robots-Tag'), 'noindex'))->toBeTrue()
        ->and($page->headers->get('Cache-Control'))->toMatch('/no-store|no-cache|private/')
        ->and($content)->toContain('EVOA1')->toContain('ALICEVALIDEE')
        ->not->toContain('EVOB1')->not->toContain('BRUNOVALIDEE')
        ->not->toContain('href="/logout"');
});

it('L4 la limite de 60 appels par heure et par clé renvoie 429 ensuite, sans gêner une autre clé', function () {
    $w = evoApiWorld();
    $alice = evoKey($w->alice);
    $bruno = evoKey($w->bruno);

    for ($i = 1; $i <= 60; $i++) {
        expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $alice])->getStatusCode())->toBe(200, "appel $i");
    }
    expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $alice])->getStatusCode())->toBe(429)
        ->and(evoApi('/api/v1/denonciations', ['format' => 'json'], ['Authorization' => "Bearer $alice"])->getStatusCode())->toBe(429)
        ->and(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $bruno])->getStatusCode())->toBe(200);

    $this->travel(61)->minutes();
    expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $alice])->getStatusCode())->toBe(200);
});

it('L4 les réponses sont en cache, et tout changement de lot, de statut ou de droits s\'applique tout de suite', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);
    expect(json_encode(evoApiJson('/api/v1/lots', $key)))->not->toContain('EVOA3');

    DB::table('appartements')->where('id', $w->lots['A1']->id)->update(['prix' => '999 999']);
    expect(json_encode(evoApiJson('/api/v1/lots', $key)))->toContain('999999');

    DB::table('appartements')->where('id', $w->lots['A1']->id)->update(['statut' => 'res']);
    $a1 = collect(evoApiJson('/api/v1/lots', $key))->firstWhere('reference', 'EVOA1');
    expect($a1['statut'] ?? null)->toBe('réservé');

    DB::table('d_prospects')->insert(['nomprospect' => 'M DIRECT Test', 'cpprospect' => '34000', 'villeprospect' => 'MONTPELLIER', 'smspromoteur' => '', 'idprojet' => $w->p1->projet_id, 'iduser' => $w->alice->id, 'date' => now()]);
    expect(json_encode(evoApiJson('/api/v1/denonciations', $key)))->toContain('DIRECT');

    $this->actingAs(makeAdmin())->post('/index.php/json/UpdateUserPrivilege', [
        'user' => $w->alice->id, 'projet' => [$w->p1->projet_id], 'lot' => [$w->lots['A1']->id, $w->lots['A2']->id, $w->lots['A3']->id],
    ])->assertOk();

    expect(json_encode(evoApiJson('/api/v1/lots', $key)))->toContain('EVOA3');
});

it('L4 un compte admin ne peut pas recevoir de clé et seul un admin peut générer ou révoquer une clé', function () {
    $w = evoApiWorld();
    $other = makeAdmin();

    evoGenerateKey(makeAdmin(), $other);
    expect(DB::table('api_keys')->count())->toBe(0);

    app('auth')->forgetGuards();
    $refused = $this->actingAs($w->bruno)->post(EVO_KEY_GENERATE($w->alice->id));
    expect($refused->getStatusCode())->not->toBe(200)
        ->and(DB::table('api_keys')->count())->toBe(0);

    $key = evoKey($w->alice);
    app('auth')->forgetGuards();
    $this->actingAs($w->bruno)->post(EVO_KEY_REVOKE($w->alice->id));
    expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $key])->getStatusCode())->toBe(200);

    app('auth')->forgetGuards();
    $this->post(EVO_KEY_GENERATE($w->alice->id));
    expect(DB::table('api_keys')->count())->toBe(1);
});

it('L4 la clé est stockée hachée, jamais en clair, et last_used_at est mis à jour à l\'usage', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);

    $row = DB::table('api_keys')->first();
    expect($row)->not->toBeNull()
        ->and(json_encode($row))->not->toContain($key)
        ->and($row->last_used_at)->toBeNull();

    $this->travelTo(now()->setTime(10, 0));
    evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $key])->assertOk();

    expect(DB::table('api_keys')->first()->last_used_at)->not->toBeNull();
});

it('L4 un compte prescripteur supprimé rend son lien inutilisable', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);

    $this->actingAs(makeAdmin())->post('/index.php/json/TrashPromoteur', ['key' => $w->alice->id]);
    expect(DB::table('user')->where('id', $w->alice->id)->exists())->toBeFalse();

    expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $key])->getStatusCode())->toBe(401);
});

it('L4 l\'API prescripteur est en lecture seule', function () {
    $w = evoApiWorld();
    $key = evoKey($w->alice);
    $state = workflowState();

    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        app('auth')->forgetGuards();
        $response = $this->withHeaders(['Authorization' => "Bearer $key"])->$method('/api/v1/lots', ['numero' => 'X']);
        expect($response->getStatusCode())->toBeIn([401, 403, 404, 405, 419], $method);
    }
    expect(workflowState())->toBe($state);
});

it('L4 l\'API publique firstsearch et search ne change pas', function () {
    loadLegacy('projet', 'appartements', 'news');

    foreach (['nextjscorim_all', 'nextjscorim_cocoon'] as $name) {
        $fixture = legacyFixture($name);
        $response = $this->get('/index.php/'.$fixture['path']);
        $response->assertStatus(200)->assertHeader('Content-Type', $fixture['type']);
        assertSameModuloTies($fixture['body'], (string) $response->getContent());
    }
    foreach (['first_default_prog', 'search_allnews'] as $name) {
        $fixture = legacyFixture($name);
        $response = $this->get('/index.php/'.$fixture['path']);
        $response->assertStatus($fixture['status'])->assertHeader('Content-Type', $fixture['type']);
        expect($response->getContent())->toBe($fixture['body']);
    }
});

it('L4 la génération redirige vers la fiche, qui montre la clé et les trois liens une seule fois', function () {
    $w = evoApiWorld();
    $admin = makeAdmin();

    $post = $this->actingAs($admin)->post(EVO_KEY_GENERATE($w->alice->id));
    $post->assertRedirect('/gestion_promoteurs/modifier/'.$w->alice->id);

    app('auth')->forgetGuards();
    $page = (string) $this->actingAs($admin)->get('/gestion_promoteurs/modifier/'.$w->alice->id)->assertOk()->getContent();
    expect(preg_match('/\\?cle=([A-Za-z0-9]{20,})/', $page, $m))->toBe(1);
    $key = $m[1];
    expect(substr_count($page, "/api/v1/lots?cle=$key\""))->toBe(1)
        ->and(substr_count($page, "/api/v1/lots?cle=$key&amp;format=csv"))->toBe(1)
        ->and(substr_count($page, "/api/v1/lots?cle=$key&amp;format=json"))->toBe(1);

    app('auth')->forgetGuards();
    $again = (string) $this->actingAs($admin)->get('/gestion_promoteurs/modifier/'.$w->alice->id)->assertOk()->getContent();
    expect($again)->not->toContain($key)->not->toContain('cle=')
        ->and(DB::table('api_keys')->count())->toBe(1)
        ->and(DB::table('api_keys')->whereNull('revoked_at')->count())->toBe(1)
        ->and(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => $key])->getStatusCode())->toBe(200);
});

it('L4 demander une clé pour un compte admin affiche une erreur sur la fiche', function () {
    $other = makeAdmin();
    $admin = makeAdmin();

    $this->actingAs($admin)->post(EVO_KEY_GENERATE($other->id))->assertRedirect('/gestion_promoteurs/modifier/'.$other->id);
    app('auth')->forgetGuards();
    $text = pageText($this->actingAs($admin)->get('/gestion_promoteurs/modifier/'.$other->id)->assertOk());

    expect($text)->toContain('Aucune clé API')->not->toContain('cle=')
        ->and(DB::table('api_keys')->count())->toBe(0);
});

dataset('csv_cells', [
    'formule' => ['=SUM(A1:A2)', "'=SUM(A1:A2)"],
    'espaces devant' => ['  =1+1', "'  =1+1"],
    'espace insécable' => ["\u{a0}=1+1", "'\u{a0}=1+1"],
    'pipe' => ['|cmd', "'|cmd"],
    'arobase' => ['@x', "'@x"],
    'plus' => ['+33 6', "'+33 6"],
    'tabulation' => ["\tx", "'\tx"],
    'retour chariot' => ["\rx", "'\rx"],
    'négatif' => ['-1', '-1'],
    'décimal négatif' => ['-12.5', '-12.5'],
    'nombre' => ['185000', '185000'],
    'texte' => ['T2 vue mer', 'T2 vue mer'],
]);

it('L4 les cellules CSV qui ressemblent à une formule sont neutralisées', function (string $value, string $expected) {
    expect(PrescriberApiController::cell($value))->toBe($expected);
})->with('csv_cells');

it('L4 le CSV servi neutralise une référence de lot en forme de formule', function () {
    $w = evoApiWorld();
    DB::table('appartements')->where('id', $w->lots['A1']->id)->update(['numero' => '=HYPERLINK("http://x")']);

    $body = responseBody(evoApi('/api/v1/lots', ['cle' => evoKey($w->alice), 'format' => 'csv'])->assertOk());

    expect($body)->toContain("'=HYPERLINK")
        ->not->toMatch('/(^|;)"?=HYPERLINK/m');
});

it('L4 l\'API est aussi limitée par adresse avant la vérification de la clé', function () {
    for ($i = 1; $i <= 600; $i++) {
        evoApi('/api/v1/lots', ['format' => 'json', 'cle' => 'inconnue'.$i.str_repeat('z', 30)]);
    }

    expect(evoApi('/api/v1/lots', ['format' => 'json', 'cle' => 'encore'.str_repeat('z', 30)])->getStatusCode())->toBe(429);
});
