<?php

use Illuminate\Support\Facades\DB;

function seedListRow(string $page, object $user, object $projet, string $nom, string $date): void
{
    $lot = makeLot($projet->projet_id, ['numero' => 'REF-'.$nom]);
    $row = ['iduser' => $user->id, 'nom' => $nom, 'adresse' => 'VILLE 34000', 'message' => 'Message '.$nom, 'idprojet' => $projet->projet_id, 'date' => $date];
    $prospect = ['nomprospect' => $nom, 'cpprospect' => '34000', 'villeprospect' => 'VILLE', 'smspromoteur' => 'Message '.$nom, 'idprojet' => $projet->projet_id, 'iduser' => $user->id, 'date' => $date];
    match ($page) {
        '/ask_options' => DB::table('d_options')->insert($row + ['iddlot' => $lot->id]),
        '/ask_reservations' => DB::table('d_reservations')->insert($row + ['iddlot' => $lot->id]),
        '/ask_denonciations' => DB::table('d_prospects')->insert($prospect),
        '/options' => DB::table('options')->insert($row + ['idlot' => $lot->id, 'statut' => 1]),
        '/options/refused' => DB::table('options')->insert($row + ['idlot' => $lot->id, 'statut' => 0]),
        '/reservations' => DB::table('reservations')->insert($row + ['idlot' => $lot->id, 'statut' => 1]),
        '/reservations/refused' => DB::table('reservations')->insert($row + ['idlot' => $lot->id, 'statut' => 0]),
        '/ventes' => (function () use ($lot, $user, $nom, $date) {
            DB::table('appartements')->where('id', $lot->id)->update(['statut' => 'act', 'client' => $nom, 'vendeur' => $user->id]);
            DB::table('ventes')->insert(['iduser' => $user->id, 'idlot' => $lot->id, 'date' => $date, 'statut' => 1, 'date_resa' => $date]);
        })(),
        '/denonciations' => DB::table('prospects')->insert($prospect),
        '/refused_ask' => DB::table('r_prospects')->insert($prospect),
    };
}

dataset('listes', [
    'ask_options' => ['/ask_options', "Demandes d'options", '_askoption', "Aucune demande d'option en cours de validation", true],
    'ask_reservations' => ['/ask_reservations', 'Demandes de réservations', '_askresa', 'Aucune demande de réservation en cours de validation', true],
    'ask_denonciations' => ['/ask_denonciations', 'Demandes de dénonciations', '_askden', 'Aucune demande de dénonciation en cours de validation', true],
    'options' => ['/options', 'Options acceptées', '_option', null, true],
    'options/refused' => ['/options/refused', 'Options annulées', null, null, false],
    'reservations' => ['/reservations', 'Réservations acceptées', '_resa', null, true],
    'reservations/refused' => ['/reservations/refused', 'Réservations annulées', null, null, false],
    'ventes' => ['/ventes', 'Lots actés', '_vente', null, true],
    'denonciations' => ['/denonciations', 'Dénonciations', '_den', null, false],
    'refused_ask' => ['/refused_ask', 'Dénonciations refusées', null, null, false],
]);

it('C26 chaque liste filtre le prescripteur, masque actions et datepickers, trie et affiche le message vide', function (string $url, string $h1, ?string $picker, ?string $empty, bool $actions) {
    corimAdmin();
    $admin = makeAdmin();
    $alice = makeCommercial(['name' => 'Alice Test']);
    $bruno = makeCommercial(['name' => 'Bruno Test']);
    $nobody = makeCommercial();
    $projet = makeProjet(['projet_titre' => 'PROGRAMME LISTE']);
    seedListRow($url, $alice, $projet, 'ACQUEREURANCIEN', '2026-01-01 10:00:00');
    seedListRow($url, $bruno, $projet, 'ACQUEREURRECENT', '2026-03-01 10:00:00');

    $adminPage = $this->actingAs($admin)->get($url)->assertOk();
    $adminText = pageText($adminPage);
    expect($adminText)->toContain("<h1>$h1</h1>")->toContain('Corim promotion - ');
    $order = $url === '/ventes' ? ['ACQUEREURANCIEN', 'ACQUEREURRECENT'] : ['ACQUEREURRECENT', 'ACQUEREURANCIEN'];
    expectInOrder($adminText, $order);
    if ($picker) {
        expect($adminText)->toContain('class="datepicker"')->toContain($picker.'"');
    }
    if ($actions) {
        expect($adminText)->toContain('ACTIONS');
    }

    $alicePage = pageText($this->actingAs($alice)->get($url)->assertOk());
    expect($alicePage)->toContain('ACQUEREURANCIEN')->not->toContain('ACQUEREURRECENT')
        ->not->toContain('class="datepicker"')->not->toContain('<b>ACTIONS</b>')
        ->not->toContain('class="delete_denonciation')->not->toContain('class="valider_ask_')->not->toContain('class="annuler_')->not->toContain('class="passer_en_');

    $nobodyPage = pageText($this->actingAs($nobody)->get($url)->assertOk());
    expect($nobodyPage)->not->toContain('ACQUEREUR');
    if ($empty) {
        expect($nobodyPage)->toContain('<div class="alert alert-info">'.$empty.'</div>');
    }
})->with('listes');

it('C27 les messages contenant <script> sont échappés dans les listes et les e-mails, le séparateur est rendu', function () {
    legacyMailBase();
    $w = workflowWorld();
    DB::table('d_options')->update(['message' => 'Avant<script>alert("xss")</script>']);
    DB::table('options')->update(['message' => 'Avant'.SEPARATOR.'Après<script>alert("xss")</script>']);
    DB::table('prospects')->update(['smspromoteur' => 'Avant'.SEPARATOR.'<script>alert("xss")</script>']);

    foreach (['/ask_options', '/options', '/denonciations'] as $url) {
        $content = (string) $this->actingAs($w->corim)->get($url)->assertOk()->getContent();
        expect($content)->not->toContain('<script>alert("xss")</script>')
            ->toContain('&lt;script&gt;');
    }
    expect((string) $this->actingAs($w->corim)->get('/options')->getContent())->toContain('Avant'.SEPARATOR.'Après');

    jsonAction($w->corim, 'AcceptAddOption', ['f_confirm_ask_option_lot' => $w->lots['askopt']->id, 'f_confirm_ask_option_user' => $w->presc->id, 'f_confirm_ask_option_sms' => '<script>alert("mail")</script>']);
    $html = (string) sentMails()[0]->getHtmlBody();
    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('C28 /ventes n\'affiche qu\'une ligne par lot acté avec la date de la vente active et la date de réservation', function () {
    corimAdmin();
    $presc = makeCommercial();
    $projet = makeProjet();
    $lot = makeLot($projet->projet_id, ['numero' => 'REVENDU-9', 'statut' => 'act', 'client' => 'M ACHETEUR', 'vendeur' => $presc->id]);
    DB::table('ventes')->insert([
        ['iduser' => $presc->id, 'idlot' => $lot->id, 'date' => '2025-01-15 10:00:00', 'statut' => 0, 'date_resa' => '2024-12-01 10:00:00'],
        ['iduser' => $presc->id, 'idlot' => $lot->id, 'date' => '2026-03-04 10:00:00', 'statut' => 1, 'date_resa' => '2026-02-02 10:00:00'],
    ]);

    $content = pageText($this->actingAs($presc)->get('/ventes')->assertOk());

    expect(substr_count($content, 'REVENDU-9'))->toBe(1)
        ->and($content)->toContain('04.03.2026')->toContain('02.02.2026')
        ->not->toContain('15.01.2025')->not->toContain('01.12.2024');
    expectInOrder($content, ['04.03.2026', '02.02.2026', 'REVENDU-9']);
});

function reportExpected(object $projet): array
{
    $one = fn (string $select, string $where = '') => DB::selectOne("SELECT $select AS v FROM appartements WHERE lot = ? $where", [$projet->projet_id])->v;
    $sum = fn (string $where = '') => (float) $one("SUM(REPLACE(prix, ' ', ''))", $where);
    $count = fn (string $where = '') => (int) $one('COUNT(id)', $where);
    $total = $sum();
    $money = fn (float $x) => ($x > 0 ? number_format($x, 2, ',', ' ') : '0').'€';
    $pct = fn (float $x) => ($x > 0 && $total > 0 ? (string) round($x / $total * 100, 2) : '0').'%';
    $parts = ["AND statut = ''", "AND statut = 'opt'", "AND statut = 'res'", "AND statut = 'act'"];

    return [
        decoded($projet->projet_titre),
        '>'.$count().' LOT(S)', '>'.$count($parts[0]).' LIBRE(S)', '>'.$count($parts[1]).' OPTION(S)', '>'.$count($parts[2]).' RÉSA(S)', '>'.$count($parts[3]).' ACTE(S)',
        '>'.$money($total), ...array_map(fn ($p) => '>'.$money($sum($p)), $parts),
        '>'.($total > 0 ? '100' : '0').'%', ...array_map(fn ($p) => '>'.$pct($sum($p)), $parts),
    ];
}

it('C29 /report : refusé hors admin, comptes et sommes par programme identiques, programme sans prix → 0%', function () {
    $projet = makeProjet(['projet_titre' => 'RAPPORT TEST']);
    foreach (['' => '100 000', 'opt' => '50 000', 'res' => '60 000', 'act' => '40 000'] as $statut => $prix) {
        makeLot($projet->projet_id, ['statut' => $statut, 'prix' => $prix]);
    }
    $empty = makeProjet(['projet_titre' => 'RAPPORT VIDE']);
    makeProjet(['projet_titre' => 'RAPPORT ARCHIVE', 'archive' => 1]);

    $refused = $this->actingAs(makeCommercial())->get('/report');
    expect($refused->getStatusCode())->toBeIn([302, 403])
        ->and($refused->getContent())->not->toContain('RAPPORT TEST')->not->toContain('LOT(S)');

    $response = $this->actingAs(makeAdmin())->get('/report');
    $response->assertOk();
    $content = pageText($response);
    expect($content)->toContain('<h1>Report <small><a href="javascript:window.print()">IMPRIMER</a></small></h1>')
        ->not->toContain('RAPPORT ARCHIVE');
    expectInOrder($content, [...reportExpected($empty), ...reportExpected($projet)]);
    expectInOrder($content, ['RAPPORT VIDE', '>0 LOT(S)', '>0€', '>0%', 'RAPPORT TEST', '>4 LOT(S)', '>1 LIBRE(S)', '>1 OPTION(S)', '>1 RÉSA(S)', '>1 ACTE(S)',
        '>250 000,00€', '>100 000,00€', '>50 000,00€', '>60 000,00€', '>40 000,00€', '>100%', '>40%', '>20%', '>24%', '>16%']);
    expect($content)->toContain('Aucune image');
});

it('C29 /report sur le snapshot : mêmes comptes et sommes que les requêtes legacy', function () {
    loadLegacy('projet', 'appartements');

    $content = pageText($this->actingAs(makeAdmin())->get('/report')->assertOk());

    $projets = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get();
    expect($projets)->not->toBeEmpty();
    $expected = [];
    foreach ($projets as $projet) {
        array_push($expected, ...reportExpected($projet));
    }
    expectInOrder($content, $expected);
});

it('C30 le tableau de bord affiche les 7 compteurs, liens inactifs à 0, RAPPORT pour l\'admin seul', function () {
    $w = workflowWorld();
    $admin = makeAdmin();
    $nobody = makeCommercial();

    $page = $this->actingAs($admin)->get('/');
    $page->assertOk();
    $counters = dashboardCounters(pageText($page));
    expect(array_map(fn ($c) => $c[0] ?? null, $counters))->toBe([
        'LOTS ACTÉ' => DB::table('appartements')->where('statut', 'act')->count(),
        "DEMANDES D'OPTIONS" => DB::table('d_options')->count(),
        'OPTIONS' => 1,
        'DEMANDES DE RÉSERVATIONS' => DB::table('d_reservations')->count(),
        'RÉSERVATIONS' => 1,
        'DEMANDES DE DÉNONCIATIONS' => DB::table('d_prospects')->count(),
        'DÉNONCIATIONS' => DB::table('prospects')->count(),
    ]);
    expect($counters["DEMANDES D'OPTIONS"][1])->toEndWith('ask_options')
        ->and($counters['LOTS ACTÉ'][1])->toEndWith('ventes')
        ->and(pageText($page))->toContain('list-group-item-warning')->toContain('glyphicon-exclamation-sign')->toMatch('#href="[^"]*report"#');

    $presc = $this->actingAs($w->presc)->get('/');
    $mine = dashboardCounters(pageText($presc));
    expect(array_map(fn ($c) => $c[0] ?? null, $mine))->toBe(array_fill_keys(array_keys($mine), 1))
        ->and(pageText($presc))->not->toMatch('#href="[^"]*report"#');

    $none = dashboardCounters(pageText($this->actingAs($nobody)->get('/')));
    foreach ($none as $label => $counter) {
        expect($counter)->toBe([0, 'javascript:void(0)'], $label);
    }
});
