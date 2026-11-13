<?php

use Illuminate\Support\Facades\DB;

function flashPayload(array $overrides = []): array
{
    return array_merge(['string1' => '', 'url' => '', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19'], $overrides);
}

it('C20 /emails liste les docs par time décroissant : date d/m/Y, email, programme par clé (sinon la clé), plan', function () {
    $admin = corimAdmin();
    $program = makeProgram(['projet_titre' => 'COCOON TEST']);
    $key = $program->projet_titre_key;
    DB::table('docs')->insert([
        ['email' => 'ancien@exemple.test', 'url' => "http://corim-vente.com/medias/programmes/$key/plans/plan-A1.pdf", 'time' => (string) mktime(12, 0, 0, 5, 22, 2015)],
        ['email' => 'recent@exemple.test', 'url' => "https://corim-vente.com/medias/programmes/$key/plans/plan-B31.pdf", 'time' => (string) mktime(12, 0, 0, 5, 8, 2022)],
        ['email' => 'inconnu@exemple.test', 'url' => 'https://corim-vente.com/medias/programmes/cleinconnue/plans/plan-C2.pdf', 'time' => (string) mktime(12, 0, 0, 1, 3, 2020)],
    ]);

    $response = $this->actingAs($admin)->get('/emails');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim promotion - Emails provenant de Corim Promotion</title>')
        ->toContain('<h1>Emails provenant de Corim Promotion</h1>')
        ->toContain('class="tablesorter-green"')
        ->toMatch('#<td[^>]*>\s*08/05/2022\s*</td>\s*<td[^>]*>\s*recent@exemple\.test\s*</td>\s*<td[^>]*>\s*COCOON TEST\s*</td>\s*<td[^>]*>\s*plan-B31\.pdf\s*</td>#')
        ->toMatch('#<td[^>]*>\s*03/01/2020\s*</td>\s*<td[^>]*>\s*inconnu@exemple\.test\s*</td>\s*<td[^>]*>\s*cleinconnue\s*</td>\s*<td[^>]*>\s*plan-C2\.pdf\s*</td>#')
        ->toMatch('#<td[^>]*>\s*22/05/2015\s*</td>\s*<td[^>]*>\s*ancien@exemple\.test\s*</td>#');
    expectInOrder($text, ['recent@exemple.test', 'inconnu@exemple.test', 'ancien@exemple.test']);

    DB::table('docs')->delete();
    expect(pageText($this->actingAs($admin)->get('/emails')->assertOk()))->not->toContain('tablesorter-green"');
});

it('C21 /flash_info liste les news par date décroissante, vignette, titre lié si url, Editer / Supprimer', function () {
    $admin = corimAdmin();
    $old = makeNews(['string1' => 'Ancienne info', 'date' => mktime(0, 0, 0, 1, 15, 2024), 'url' => '']);
    $new = makeNews(['string1' => 'Nouvelle info', 'thumb' => '22', 'date' => mktime(0, 0, 0, 6, 1, 2026), 'url' => 'https://exemple.test/news']);

    $response = $this->actingAs($admin)->get('/flash_info');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim promotion - Flash Info pour Corim Promotion</title>')
        ->toMatch('#<h1>Flash Info pour Corim Promotion <small><a href="[^"]*/flash_info/add">Ajouter un Flash Info</a></small></h1>#')
        ->toContain('01/06/2026')->toContain('15/01/2024')
        ->toMatch('#<img src="[^"]*/medias/news/22\.jpg" height="160" />#')
        ->toMatch('#<h3><a href="https://exemple\.test/news">Nouvelle info</a></h3>#')
        ->toContain('<h3>Ancienne info</h3>')
        ->toMatch('#href="[^"]*/flash_info/edit/'.$new->id.'">Editer</a>#')
        ->toContain('Supprimer</a>');
    expectInOrder($text, ['Nouvelle info', 'Ancienne info']);
});

it('C22 flash_info/add enregistre \'\' pour les champs vides et la date courante ; sans vignette → « La vignette est obligatoire »', function () {
    $admin = corimAdmin();
    makeProgram(['projet_titre' => 'PROGRAMME VIGNETTE']);

    $form = pageText($this->actingAs($admin)->get('/flash_info/add')->assertOk());
    expect($form)->toContain('<title>Corim promotion - Ajouter un Flash Info pour Corim Promotion</title>')
        ->toContain('<h1>Ajouter un Flash Info</h1>')
        ->toContain('PROGRAMME VIGNETTE</option>')
        ->toMatch('#<option value="0"\s*>AUTRE</option>#');

    expect(pageText($this->actingAs($admin)->post('/flash_info/add', flashPayload(['thumb' => '']))))->toContain('<div class="alert alert-warning">La vignette est obligatoire</div>')
        ->and(DB::table('news')->count())->toBe(0);

    $response = $this->actingAs($admin)->post('/flash_info/add', flashPayload(['string1' => 'Titre seul']));
    expect(pageText($response))->toContain('<div class="alert alert-success">Le flash info a été ajouté</div>');
    $row = (array) DB::table('news')->first();
    expect(array_intersect_key($row, flashPayload()))->toBe(['string1' => 'Titre seul', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19', 'url' => ''])
        ->and(abs((int) $row['date'] - time()))->toBeLessThan(120);
});

it('C23 flash_info/edit met à jour la ligne et la date, « Le flash info a été mis à jour » ; id inconnu → /', function () {
    $admin = corimAdmin();
    $news = makeNews(['string1' => 'Avant', 'date' => 1000000000]);

    $form = pageText($this->actingAs($admin)->get("/flash_info/edit/$news->id")->assertOk());
    expect($form)->toContain('<title>Corim promotion - Éditer un Flash Info pour Corim Promotion</title>')
        ->toContain('<h1>Modifier le Flash Info Avant</h1>')
        ->toMatch('#name="string1" id="string1" value="Avant"#')
        ->toMatch('#<img src="[^"]*/medias/news/19\.jpg" class="media-object" />#');

    $response = $this->actingAs($admin)->post("/flash_info/edit/$news->id", flashPayload(['string1' => 'Après', 'thumb' => '23', 'id' => $news->id, 'url' => 'https://exemple.test/apres']));
    expect(pageText($response))->toContain('<div class="alert alert-success">Le flash info a été mis à jour</div>');
    $row = DB::table('news')->find($news->id);
    expect([$row->string1, $row->thumb, $row->url, $row->string2])->toBe(['Après', '23', 'https://exemple.test/apres', ''])
        ->and(abs((int) $row->date - time()))->toBeLessThan(120);

    expect($this->actingAs($admin)->get('/flash_info/edit/999999')->isRedirect(url('/')))->toBeTrue();
});

it('C24 flash_info/delete en POST supprime puis redirige vers /flash_info ; GET sans effet ; id inconnu → / sans suppression', function () {
    $admin = corimAdmin();
    $news = makeNews();
    $other = makeNews();

    $this->actingAs($admin)->get("/flash_info/delete/$news->id");
    expect(DB::table('news')->count())->toBe(2);

    $response = $this->actingAs($admin)->post("/flash_info/delete/$news->id");
    expect($response->isRedirect(url('/flash_info')))->toBeTrue()
        ->and(DB::table('news')->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$other->id]);

    expect($this->actingAs($admin)->post('/flash_info/delete/999999')->isRedirect(url('/')))->toBeTrue()
        ->and(DB::table('news')->count())->toBe(1);
});

it('C25 <script> et javascript: dans un flash info sont neutralisés, allnews garde sa structure', function () {
    $admin = corimAdmin();
    makeNews(['string1' => 'Info piégée', 'string3' => '<p>Texte <b>gras</b></p><script>alert(1)</script>', 'url' => 'javascript:alert(2)']);

    $raw = (string) $this->actingAs($admin)->get('/flash_info')->assertOk()->getContent();
    expect($raw)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('href="javascript:alert(2)"')
        ->toContain('Info piégée')
        ->toContain('<b>gras</b>');

    $edit = (string) $this->actingAs($admin)->get('/flash_info/edit/'.DB::table('news')->value('id'))->getContent();
    expect($edit)->not->toContain('<script>alert(1)</script>');

    app('auth')->forgetGuards();
    $data = json_decode(jsonpUnwrap($this->get('/index.php/search/news?action=allnews')->getContent(), 'MyFirstCallbackNews'), true);
    expect($data['errors'])->toBe(1)
        ->and(array_keys($data['data'][0]))->toBe(['id', 'string1', 'string2', 'string3', 'string4', 'url', 'thumb']);
});
