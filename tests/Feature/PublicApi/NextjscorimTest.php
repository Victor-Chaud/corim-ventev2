<?php

beforeEach(function () {
    loadLegacy('projet', 'appartements');
});

function nextjscorim(string $query = ''): array
{
    $response = test()->get('/index.php/firstsearch?action=nextjscorim'.$query);
    $response->assertStatus(200);

    return json_decode($response->getContent(), true);
}

function sameRows(array $a, array $b): void
{
    expect(json_encode(sortTies($a)))->toBe(json_encode(sortTies($b)));
}

const A1_KEYS = ['id', 'lot', 'date', 'dept', 'ville', 'type', 'categorie', 'prix', 'prix_m2', 'etage', 'RDC', 'jardin', 'terrasse', 'superficie', 'stationnement', 'exposition', 'description', 'statut', 'client', 'vendeur', 'numero', 'cellier', 'accession', 'projet_titre', 'projet_titre_key'];

it('C7 chaque objet A1 a les 25 clés dans l\'ordre et uniquement des chaînes (vendeur chaîne ou null)', function () {
    foreach (['', '&residence=cocoon', '&type=T2', '&city=AGDE'] as $query) {
        foreach (nextjscorim($query) as $row) {
            expect(array_keys($row))->toBe(A1_KEYS)
                ->and($row['ville'])->toBeString();
            foreach ($row as $key => $value) {
                $key === 'vendeur'
                    ? expect(is_string($value) || $value === null)->toBeTrue()
                    : expect($value)->toBeString();
            }
        }
    }
});

it('C8 les filtres LIKE sont insensibles à la casse et aux accents', function () {
    $cocoon = nextjscorim('&residence=cocoon');
    expect($cocoon)->not->toBeEmpty();
    sameRows(nextjscorim('&residence=COCOON'), $cocoon);
    sameRows(nextjscorim('&residence=Cocoon'), $cocoon);

    $castelnau = nextjscorim('&city=CASTELNAU%20LE%20LEZ');
    expect($castelnau)->not->toBeEmpty();
    sameRows(nextjscorim('&city=castelnau%20le%20lez'), $castelnau);

    expect(array_unique(array_column(nextjscorim('&residence=carre%20vias'), 'projet_titre')))->toBe(['CARRÉ VIAS']);
});

it('C9 l\'appel corim-promotion sans recherche renvoie []', function () {
    $response = $this->get('/index.php/firstsearch?action=nextjscorim&residence=undefined&type=&city=&minBudget=&maxBudget=');

    $response->assertStatus(200);
    expect($response->getContent())->toBe('[]');
});

it('C10 un paramètre valant "0" est ignoré', function () {
    $all = nextjscorim();
    foreach (['type', 'city', 'residence', 'minBudget', 'maxBudget'] as $param) {
        sameRows(nextjscorim("&$param=0"), $all);
    }
});

it('C11 les jokers LIKE envoyés par le client sont conservés', function () {
    sameRows(nextjscorim('&residence=%25coc%25'), nextjscorim('&residence=cocoon'));
});

it('C12 une injection SQL est neutralisée', function () {
    $response = $this->get('/index.php/firstsearch?action=nextjscorim&residence='.rawurlencode("cocoon' OR '1'='1"));

    $response->assertStatus(200);
    expect($response->getContent())->toBe('[]');
});

it('C13 aucun lot archivé ni non libre n\'apparaît', function () {
    $rows = nextjscorim();
    $allowed = DB::table('appartements')->join('projet', DB::raw('appartements.lot'), '=', 'projet.projet_id')
        ->where('projet.archive', 0)->where('appartements.statut', '')->pluck('appartements.id')
        ->map(fn ($id) => (string) $id)->all();

    expect(array_column($rows, 'id'))->each->toBeIn($allowed)
        ->and(array_column($rows, 'statut'))->each->toBe('');
});

it('C35 les variantes d\'URL renvoient la même réponse', function () {
    $reference = $this->get('/index.php/firstsearch?action=nextjscorim&residence=cocoon')->getContent();

    foreach (['/firstsearch?action=nextjscorim&residence=cocoon', '/index.php/firstsearch/index?action=nextjscorim&residence=cocoon', '/firstsearch/index?action=nextjscorim&residence=cocoon'] as $url) {
        $response = $this->get($url);
        $response->assertStatus(200)->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        assertSameModuloTies($reference, $response->getContent());
    }
});

it('C36 l\'encodage est json_encode sans option (\/ et É, pas d\'échappement hex)', function () {
    $body = $this->get('/index.php/firstsearch?action=nextjscorim')->getContent();
    expect($body)->toContain(json_encode('CARRÉ VIAS'))->toContain('\\u00c9')->not->toContain('É')->not->toContain('\\u003C');

    $news = $this->get('/index.php/search?action=allnews')->getContent();
    expect($news)->toContain('<\\/b>')->toContain('https:\\/\\/')->not->toContain('\\u003C')->not->toContain('\\u003E');
});

it('C37 aucun entier JSON dans les lignes de lots (PDO neutralisé)', function () {
    $body = $this->get('/index.php/firstsearch?action=nextjscorim')->getContent();

    expect($body)->not->toMatch('/"(id|lot|dept)":\d/');
    expect(json_decode($body, true))->toBeArray()->not->toBeEmpty();
    foreach (json_decode($body, true) as $row) {
        expect($row['id'])->toBeString()->and($row['lot'])->toBeString()->and($row['dept'])->toBeString();
    }
});
