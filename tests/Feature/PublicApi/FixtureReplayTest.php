<?php

beforeEach(function () {
    loadLegacy('projet', 'appartements', 'news');
});

function replayStrictModuloTies(string $name, int $count): void
{
    $fixture = legacyFixture($name);
    $response = test()->get('/index.php/'.$fixture['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', $fixture['type']);
    expect(json_decode($response->getContent(), true))->toHaveCount($count);
    assertSameModuloTies($fixture['body'], $response->getContent());
}

function replayStrict(string $name): void
{
    $fixture = legacyFixture($name);
    $response = test()->get('/index.php/'.$fixture['path']);

    $response->assertStatus($fixture['status'])->assertHeader('Content-Type', $fixture['type']);
    expect($response->getContent())->toBe($fixture['body']);
}

it('C1 rejoue nextjscorim_cocoon (strict modulo ex aequo, 47 objets)', function () {
    replayStrictModuloTies('nextjscorim_cocoon', 47);
});

it('C2 rejoue nextjscorim_all (strict modulo ex aequo, 69 objets)', function () {
    replayStrictModuloTies('nextjscorim_all', 69);
});

it('C3 rejoue nextjscorim_type_t2 (strict modulo ex aequo, 12 objets)', function () {
    replayStrictModuloTies('nextjscorim_type_t2', 12);
});

it('C4 rejoue nextjscorim_city octet pour octet', function () {
    replayStrict('nextjscorim_city');
});

it('C5 rejoue nextjscorim_budget avec comparaison de chaînes sur le prix', function () {
    replayStrictModuloTies('nextjscorim_budget', 46);
    $prix = array_column(json_decode(test()->get('/index.php/'.legacyFixture('nextjscorim_budget')['path'])->getContent(), true), 'prix');
    expect($prix)->toContain('203 900', '219900')->not->toContain('199 900');
});

it('C6 rejoue nextjscorim_combo (strict modulo ex aequo, 24 objets)', function () {
    replayStrictModuloTies('nextjscorim_combo', 24);
});

it('C14 rejoue first_default_empty octet pour octet', function () {
    replayStrict('first_default_empty');
    expect(legacyFixture('first_default_empty')['body'])->toBe('MyFirstCallback({"errors":1});');
});

it('C15 rejoue first_default_prog octet pour octet', function () {
    replayStrict('first_default_prog');
});

it('C18 rejoue search_allnews octet pour octet (271 octets)', function () {
    replayStrict('search_allnews');
    expect(strlen(legacyFixture('search_allnews')['body']))->toBe(271);
});

it('C19 rejoue search_light_prog (strict modulo ex aequo, 149 objets à 24 clés)', function () {
    $fixture = legacyFixture('search_light_prog');
    $response = $this->get('/index.php/'.$fixture['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', $fixture['type']);
    $expected = json_decode(jsonpUnwrap($fixture['body'], 'MyCallback2'), true);
    $actual = json_decode(jsonpUnwrap($response->getContent(), 'MyCallback2'), true);

    expect(array_keys($actual))->toBe(['errors', 'data'])
        ->and($actual['errors'])->toBe(1)
        ->and($actual['data'])->toHaveCount(149)
        ->and(array_keys($actual['data'][0]))->toHaveCount(24);
    assertSameModuloTies(json_encode($expected['data']), json_encode($actual['data']));
});

it('C21 firstsearch sans paramètre renvoie le message M4 en 200', function () {
    $response = $this->get('/index.php/'.legacyFixture('first_noparams')['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe(jsonp('MyFirstCallback', ['errors' => 'Aucunes données envoyées']));
});

it('C22 search et search/news sans paramètre renvoient le message M4 avec MyCallback', function () {
    $expected = jsonp('MyCallback', ['errors' => 'Aucunes données envoyées']);

    foreach (['/index.php/search', '/index.php/search/news'] as $url) {
        $response = $this->get($url);
        $response->assertStatus(200)->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        expect($response->getContent())->toBe($expected);
    }
});

function assertProgramShape(string $body, string $callback, int $lotKeys = 24): array
{
    $data = json_decode(jsonpUnwrap($body, $callback), true);
    expect($data['errors'])->toBe(1)->and($data['program'])->toBeArray()->not->toBeEmpty();
    foreach ($data['program'] as $program) {
        expect(array_keys($program))->toBe(['titre', 'lieu', 'result'])
            ->and($program['titre'])->toBeString()
            ->and($program['lieu'])->toBeString();
        foreach ($program['result'] as $lot) {
            expect(array_keys($lot))->toHaveCount($lotKeys)->and($lot['prix'])->toBeString();
        }
    }

    return $data;
}

it('C23 first_light_all renvoie un programme par programme ayant des lots libres', function () {
    $response = $this->get('/index.php/'.legacyFixture('first_light_all')['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    $data = assertProgramShape($response->getContent(), 'MyFirstCallback2');
    $expected = DB::table('appartements')->join('projet', DB::raw('appartements.lot'), '=', 'projet.projet_id')
        ->where('statut', '')->distinct()->count('projet.projet_id');
    expect($data['program'])->toHaveCount($expected);
});

it('C24 first_light_prog renvoie la même forme JSONP', function () {
    $response = $this->get('/index.php/'.legacyFixture('first_light_prog')['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    assertProgramShape($response->getContent(), 'MyFirstCallback2');
});

it('C25 first_callback est identique à first_light_prog (callback ignoré)', function () {
    $withCallback = $this->get('/index.php/'.legacyFixture('first_callback')['path']);
    $without = $this->get('/index.php/'.legacyFixture('first_light_prog')['path']);

    $withCallback->assertStatus(200);
    expect($withCallback->getContent())->toBe($without->getContent())->not->toStartWith('foo(');
});

it('C26 first_withref contient le lot B31 de COCOON', function () {
    $response = $this->get('/index.php/'.legacyFixture('first_withref')['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    $data = assertProgramShape($response->getContent(), 'MyFirstCallback4');
    $found = collect($data['program'])->contains(fn ($p) => $p['titre'] === 'COCOON'
        && collect($p['result'])->contains(fn ($lot) => $lot['numero'] === 'B31'));
    expect($found)->toBeTrue();
});

it('C27 first_withref_none renvoie le message M1 octet pour octet', function () {
    $response = $this->get('/index.php/'.legacyFixture('first_withref_none')['path']);

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe(jsonp('MyFirstCallback4', ['errors' => M1_TEXT]));
});

it('C28 aucune réponse ne divulgue SQL, base, chemins ou trace', function () {
    $paths = array_map(fn ($row) => $row[2], legacyManifest());
    array_push($paths,
        'firstsearch?action=nextjscorim&type=%27',
        'firstsearch?action=nextjscorim&minBudget=%27%22%5C',
        'firstsearch?action=light&programmes=%27',
        'firstsearch?action=withref&programme=%27&ref=%27',
        'search?action=light&programmes=%27',
        'firstsearch?typologie[]=%27&programmes=1',
        'firstsearch?action=app&projet=%27&typologie[]=%27',
    );

    $this->get('/index.php/firstsearch?action=nextjscorim')->assertOk();

    foreach ($paths as $path) {
        $body = $this->get('/index.php/'.$path)->getContent();
        expect($body)->not->toContain('SELECT')
            ->not->toContain('sql_corim_vente_')
            ->not->toContain('/var/www')
            ->not->toContain('Stack trace')
            ->not->toContain('vendor/laravel');
    }
});
