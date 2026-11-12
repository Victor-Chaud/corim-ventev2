<?php

beforeEach(function () {
    loadLegacy('projet', 'appartements', 'news');
});

it('C16 la recherche par défaut avec livraison renvoie les lots 2028 de COCOON', function () {
    $response = $this->get('/index.php/firstsearch?programmes=41&livraison=2028');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    $data = json_decode(jsonpUnwrap($response->getContent(), 'MyFirstCallback'), true);
    expect($data['errors'])->toBe(1)
        ->and($data['program'])->toHaveCount(1)
        ->and($data['program'][0]['titre'])->toBe('COCOON')
        ->and($data['program'][0]['lieu'])->toBe('CASTELNAU LE LEZ');

    $result = $data['program'][0]['result'];
    $expected = DB::table('appartements')->where('lot', '41')->where('statut', '')
        ->whereIn('date', ['1T2028', '2T2028', '3T2028', '4T2028'])->count();
    expect($result)->toHaveCount($expected);
    foreach ($result as $lot) {
        expect(array_keys($lot))->toHaveCount(24)->not->toContain('projet_titre_key')
            ->and($lot['statut'])->toBe('')
            ->and($lot['date'])->toMatch('/^[1-4]T2028$/');
    }
});

it('C17 la recherche par défaut sans programme trouvé renvoie M1 (http)', function () {
    $response = $this->get('/index.php/firstsearch?programmes=999999');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe(jsonp('MyFirstCallback', ['errors' => M1_TEXT]));
});

it('C20 le paramètre callback n\'est jamais reflété', function () {
    $response = $this->get('/index.php/search?action=light&callback=foo');

    $response->assertStatus(200);
    expect($response->getContent())->toStartWith('MyCallback2(');

    foreach (['/index.php/firstsearch?action=search&callback=foo', '/index.php/search?action=allnews&callback=foo', '/index.php/firstsearch?action=withref&ref=ZZZ&callback=foo'] as $url) {
        expect($this->get($url)->getContent())->not->toStartWith('foo');
    }
});

it('C29 firstsearch?action=email valide insère une ligne docs', function () {
    $before = DB::table('docs')->count();
    $response = $this->get('/index.php/firstsearch?action=email&email=contact%40exemple.test&url=https://exemple.test/doc.pdf');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe('MyFirstCallback3({"errors":1,"data":"https:\/\/exemple.test\/doc.pdf"});')
        ->and(DB::table('docs')->count())->toBe($before + 1);
    $row = DB::table('docs')->orderByDesc('iddocs')->first();
    expect($row->email)->toBe('contact@exemple.test')
        ->and($row->url)->toBe('https://exemple.test/doc.pdf')
        ->and($row->time)->toMatch('/^\d{10}$/');
});

it('C30 firstsearch?action=email invalide n\'insère rien', function () {
    $before = DB::table('docs')->count();

    expect($this->get('/index.php/firstsearch?action=email&email=invalide&url=https://exemple.test/doc.pdf')->getContent())
        ->toBe('MyFirstCallback3({"errors":"Votre adresse email n\'est pas valide","data":"https:\/\/exemple.test\/doc.pdf"});')
        ->and($this->get('/index.php/firstsearch?action=email&email=invalide')->getContent())
        ->toBe('MyFirstCallback3({"errors":"Votre adresse email n\'est pas valide","data":null});')
        ->and(DB::table('docs')->count())->toBe($before);
});

it('C31 search?action=email reprend A6 avec MyCallback3 en text/html', function () {
    $before = DB::table('docs')->count();

    $ok = $this->get('/index.php/search?action=email&email=contact%40exemple.test&url=https://exemple.test/doc.pdf');
    $ok->assertStatus(200)->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($ok->getContent())->toBe('MyCallback3({"errors":1,"data":"https:\/\/exemple.test\/doc.pdf"});');

    $ko = $this->get('/index.php/search?action=email&email=invalide');
    expect($ko->getContent())->toBe('MyCallback3({"errors":"Votre adresse email n\'est pas valide","data":null});')
        ->and(DB::table('docs')->count())->toBe($before + 1);
});

it('C32 search/news?action=allnews renvoie les flash infos en chaînes, date DESC', function () {
    $response = $this->get('/index.php/search/news?action=allnews');

    $response->assertStatus(200);
    $data = json_decode(jsonpUnwrap($response->getContent(), 'MyFirstCallbackNews'), true);
    $expectedIds = DB::table('news')->orderByDesc('date')->pluck('id')->map(fn ($id) => (string) $id)->all();

    expect($data['errors'])->toBe(1)
        ->and($data['data'])->toHaveCount(3)
        ->and(array_column($data['data'], 'id'))->toBe($expectedIds);
    foreach ($data['data'] as $news) {
        expect(array_keys($news))->toBe(['id', 'string1', 'string2', 'string3', 'string4', 'url', 'thumb'])
            ->and($news)->each->toBeString();
    }

    DB::table('news')->delete();
    expect($this->get('/index.php/search/news?action=allnews')->getContent())
        ->toBe('MyFirstCallbackNews({"errors":"<b>Aucun flash info actuellement.<\/b>"});');
});

it('C33 firstsearch?action=app renvoie les lots du projet à 25 clés', function () {
    $response = $this->get('/index.php/firstsearch?action=app&projet=41&budget=0');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/json');
    $data = json_decode(jsonpUnwrap($response->getContent(), 'MyFirstCallback'), true);
    expect($data['errors'])->toBe(1)
        ->and($data['program'])->toHaveCount(DB::table('appartements')->where('lot', '41')->where('statut', '')->count());
    foreach ($data['program'] as $lot) {
        expect(array_keys($lot))->toHaveCount(25)->toContain('projet_titre_key', 'numero');
    }

    expect($this->get('/index.php/firstsearch?action=app&budget=0')->getContent())
        ->toBe(jsonp('MyFirstCallback', ['errors' => '<b>Aucun résultat pour cette recherche.</b>']));
});

it('C34 les plans PDF sont servis à l\'identique aux mêmes chemins', function () {
    $sample = OLD_MEDIAS.'/programmes/baa692ce3ec9e7dffe643f89936729cb/plans/plan-B202.pdf';
    $response = $this->get('/medias/programmes/baa692ce3ec9e7dffe643f89936729cb/plans/plan-B202.pdf');

    $response->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
    expect(responseBody($response))->toBe(file_get_contents($sample));

    $rows = json_decode($this->get('/index.php/firstsearch?action=nextjscorim')->getContent(), true);
    expect($rows)->toBeArray();
    foreach ($rows as $row) {
        $path = 'programmes/'.$row['projet_titre_key'].'/plans/plan-'.str_replace(' ', '', $row['numero']).'.pdf';
        if (is_file(OLD_MEDIAS.'/'.$path)) {
            expect($this->get('/medias/'.$path)->getStatusCode())->toBe(200, $path);
        }
    }
});

it('C38 l\'API est limitée en débit, sans cookie de session ni changement de réponse', function () {
    $urls = ['/index.php/firstsearch?action=nextjscorim&city=AGDE', '/index.php/firstsearch?action=search', '/index.php/search?action=allnews'];

    foreach ($urls as $url) {
        $first = $this->get($url);
        $first->assertHeader('X-RateLimit-Limit');
        expect($first->headers->getCookies())->toBeEmpty();
        for ($i = 0; $i < 5; $i++) {
            expect($this->get($url)->getContent())->toBe($first->getContent());
        }
    }

    $limited = false;
    for ($i = 0; $i < 1000 && ! $limited; $i++) {
        $limited = $this->get('/index.php/firstsearch?action=nextjscorim&city=AGDE')->getStatusCode() === 429;
    }
    expect($limited)->toBeTrue();
});
