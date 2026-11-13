<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('programmes');
});

function tablesSnapshot(): string
{
    return json_encode([DB::table('projet')->get(), DB::table('appartements')->get()]);
}

function validProgram(array $overrides = []): array
{
    return array_merge([
        'titre' => 'NOUVEAU PROGRAMME',
        'livraison' => '2T2029',
        'ville' => 'MONTPELLIER',
        'description' => 'Description du programme',
        'thumb' => UploadedFile::fake()->image('vignette.jpg', 400, 300),
    ], $overrides);
}

function validLot(object $program, array $overrides = []): array
{
    return array_merge([
        'name' => $program->projet_titre,
        'date' => '2T2028',
        'departement' => $program->dept,
        'ville' => $program->projet_lieu,
        'type' => 'T2',
        'cat' => 'Appartement',
        'prix' => '185 000',
        'prix_m2' => '',
        'superficie' => '45,20',
        'stationnement' => '1',
        'exposition' => 'S',
        'description' => '',
        'numero' => 'A12',
        'cellier' => '',
        'accession' => 'Libre',
        'projet_id' => $program->projet_id,
    ], $overrides);
}

function editLot(object $program, object $lot, array $overrides = []): array
{
    return array_merge([
        'date' => $lot->date,
        'numero' => $lot->numero,
        'type' => $lot->type,
        'cat' => $lot->categorie,
        'prix' => $lot->prix,
        'prix_m2' => $lot->prix_m2,
        'cellier' => $lot->cellier,
        'accession' => $lot->accession,
        'etage' => $lot->etage,
        'rdc' => $lot->RDC,
        'jardin' => $lot->jardin,
        'terrasse' => $lot->terrasse,
        'superficie' => $lot->superficie,
        'stationnement' => $lot->stationnement,
        'exposition' => $lot->exposition,
        'description' => $lot->description,
        'name' => $program->projet_id,
        'departement' => $program->dept,
        'idlot' => $lot->id,
        'ville' => $program->projet_lieu,
    ], $overrides);
}

function csvLine(array $cols): string
{
    return implode(';', $cols)."\r\n";
}

function csvRow(string $number, string $description = ''): array
{
    return ['3T2028', 'T3', 'Appartement', '219 900', '', 'R2', '', '', '12,67', '63,66', '24-32', 'NE', $description, 'plan.pdf', $number, 'C1', 'LIBRE'];
}

it('C1 la liste admin des programmes affiche en cours (id décroissant) et archivés avec leurs liens', function () {
    $a = makeProgram(['projet_titre' => 'ALPHA']);
    $b = makeProgram(['projet_titre' => 'BETA']);
    $old = makeProgram(['projet_titre' => 'ARCHIVE', 'archive' => 1]);

    $response = $this->actingAs(makeAdmin())->get('/gestion_programmes');

    $response->assertOk()
        ->assertSee('Corim Promotion - Gestion des programmes', false)
        ->assertSeeInOrder(['PROGRAMMES EN COURS', 'BETA', 'ALPHA', 'PROGRAMMES ARCHIVÉS', 'ARCHIVE'], false)
        ->assertSee('ARCHIVER LE PROJET')
        ->assertSee('RÉACTIVER LE PROJET')
        ->assertSee("($a->projet_id)");
    foreach ([$a, $b, $old] as $p) {
        $response->assertSee("modifier_programme/show/$p->projet_titre_key/", false)
            ->assertSee("gerer_lots/show/$p->projet_titre_key", false)
            ->assertSee("ajouter_lots/show/$p->projet_titre_key", false)
            ->assertSee("ajouter_plan/show/$p->projet_titre_key", false);
    }
    $response->assertSee('rel="'.$a->projet_titre_key.'"', false);
});

it('C2 prescripteur et anonyme n\'accèdent pas aux pages admin et leurs POST ne modifient rien', function () {
    $program = makeProgram();
    $lot = makeLot($program->projet_id);
    $key = $program->projet_titre_key;

    $this->get('/gestion_programmes')->assertRedirect('/connexion');
    $this->actingAs(makeCommercial())->get('/gestion_programmes')->assertRedirect('/');

    $before = tablesSnapshot();
    $posts = [
        '/ajouter_programme' => validProgram(),
        "/modifier_programme/show/$key" => ['action' => 'project', 'titre' => 'PIRATE', 'livraison' => '1T2030', 'ville' => 'X', 'dept' => '99', 'description' => 'x', 'key' => $key, 'idprojet' => $program->projet_id],
        "/ajouter_lot/show/$key" => validLot($program, ['plan' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')]),
        "/ajouter_lots/show/$key" => ['name' => $program->projet_id, 'departement' => '34', 'ville' => 'X', 'csv' => '1', 'csv_upload' => UploadedFile::fake()->createWithContent('i.csv', csvLine(csvRow('Z1')))],
        "/ajouter_plan/show/$key" => ['plan_upload' => [UploadedFile::fake()->create('plan-T1.pdf', 10, 'application/pdf')]],
        "/gerer_lots/modifier/$key-$lot->id" => editLot($program, $lot, ['prix' => '1']),
    ];
    foreach ($posts as $url => $data) {
        $this->post($url, $data)->assertRedirect('/');
    }

    expect(tablesSnapshot())->toBe($before)
        ->and(Storage::disk('programmes')->allFiles())->toBeEmpty();
});

it('C3 ajouter_programme sans un champ requis affiche « Tous les champs sont requis »', function () {
    $admin = makeAdmin();
    foreach (['titre', 'livraison', 'ville', 'description', 'thumb'] as $field) {
        $data = validProgram();
        unset($data[$field]);
        $this->actingAs($admin)->post('/ajouter_programme', $data)->assertSee('Tous les champs sont requis');
    }
    expect(DB::table('projet')->count())->toBe(0);
});

it('C4 ajouter_programme refuse une vignette non image ou de plus de 5 Mo', function () {
    $admin = makeAdmin();
    $message = "L'image doit être au format .gif, .jpg, .png et doit être inférieur à 5M";

    $this->actingAs($admin)->post('/ajouter_programme', validProgram(['thumb' => UploadedFile::fake()->create('v.txt', 1, 'text/plain')]))
        ->assertSee($message, false);
    $this->actingAs($admin)->post('/ajouter_programme', validProgram(['thumb' => UploadedFile::fake()->image('v.jpg')->size(6000)]))
        ->assertSee($message, false);

    expect(DB::table('projet')->count())->toBe(0);
});

it('C5 ajouter_programme valide crée le projet, les 5 dossiers et la vignette de 120 px', function () {
    $this->actingAs(makeAdmin())->post('/ajouter_programme', validProgram())
        ->assertSee('Le programme a été ajouté');

    $key = md5('NOUVEAU PROGRAMME');
    $program = DB::table('projet')->first();
    expect($program->projet_titre_key)->toBe($key)
        ->and((int) $program->archive)->toBe(0)
        ->and($program->dept)->toBe('')
        ->and($program->projet_image)->toBe('')
        ->and((int) $program->projet_nb_appartements)->toBe(0)
        ->and($program->livraison)->toBe('2T2029');

    $disk = Storage::disk('programmes');
    foreach (['thumb', 'diapo', 'documents', 'plans', 'visuels'] as $dir) {
        expect($disk->directoryExists("$key/$dir"))->toBeTrue($dir);
    }
    $disk->assertExists("$key/thumb/thumb.jpg");
    expect(getimagesizefromstring($disk->get("$key/thumb/thumb.jpg"))[0])->toBe(120);
});

it('C6 ajouter_programme refuse un titre dont le md5 existe déjà', function () {
    makeProgram(['projet_titre' => 'NOUVEAU PROGRAMME']);
    $admin = makeAdmin();
    $this->actingAs($admin)->get('/ajouter_programme')->assertOk();

    $this->actingAs($admin)->post('/ajouter_programme', validProgram())
        ->assertOk()
        ->assertDontSee('Le programme a été ajouté');

    expect(DB::table('projet')->count())->toBe(1);
});

it('C7 modifier_programme action=project met à jour sans changer la clé et recopie ville, dept et date sur les lots', function () {
    $program = makeProgram(['projet_titre' => 'AVANT']);
    $lots = [makeLot($program->projet_id), makeLot($program->projet_id)];
    $other = makeLot(makeProgram()->projet_id);
    $key = $program->projet_titre_key;

    $this->actingAs(makeAdmin())->post("/modifier_programme/show/$key", [
        'action' => 'project', 'titre' => 'APRES', 'livraison' => '4T2030', 'ville' => 'NIMES',
        'dept' => '30', 'description' => 'Nouvelle description', 'key' => $key, 'idprojet' => $program->projet_id,
    ])->assertSee('Le programme a bien été modifié');

    $updated = DB::table('projet')->where('projet_id', $program->projet_id)->first();
    expect($updated->projet_titre)->toBe('APRES')
        ->and($updated->projet_titre_key)->toBe($key)
        ->and($updated->projet_lieu)->toBe('NIMES')
        ->and($updated->dept)->toBe('30')
        ->and($updated->livraison)->toBe('4T2030')
        ->and($updated->projet_description)->toBe('Nouvelle description');
    foreach ($lots as $lot) {
        $row = DB::table('appartements')->find($lot->id);
        expect($row->ville)->toBe('NIMES')->and((string) $row->dept)->toBe('30')->and($row->date)->toBe('4T2030');
    }
    expect(DB::table('appartements')->find($other->id)->ville)->toBe('MONTPELLIER');
});

it('C8 une nouvelle vignette valide remplace l\'unique fichier de thumb/', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    $disk = Storage::disk('programmes');
    $disk->put("$key/thumb/thumb.png", 'ancienne');

    $this->actingAs(makeAdmin())->post("/modifier_programme/show/$key", [
        'action' => 'project', 'titre' => $program->projet_titre, 'livraison' => '2T2028', 'ville' => 'MONTPELLIER',
        'dept' => '34', 'description' => 'd', 'key' => $key, 'idprojet' => $program->projet_id,
        'thumb' => UploadedFile::fake()->image('n.jpg', 300, 200),
    ])->assertOk();

    expect($disk->files("$key/thumb"))->toBe(["$key/thumb/thumb.jpg"])
        ->and(getimagesizefromstring($disk->get("$key/thumb/thumb.jpg"))[0])->toBe(120);
});

it('C9 modifier_programme action=uploader dépose les fichiers dans le dossier choisi', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    $admin = makeAdmin();

    $this->actingAs($admin)->post("/modifier_programme/show/$key", [
        'action' => 'uploader', 'type' => 'plans',
        'filer' => [UploadedFile::fake()->create('plan-A1.pdf', 20, 'application/pdf')],
    ])->assertOk();

    Storage::disk('programmes')->assertExists("$key/plans/plan-A1.pdf");
    $this->actingAs($admin)->get("/modifier_programme/show/$key")
        ->assertSee('name="selectfile[]" value="/medias/programmes/'.$key.'/plans/plan-A1.pdf"', false)
        ->assertSee('SUPPRIMER');

    $this->actingAs($admin)->post("/modifier_programme/show/$key", [
        'action' => 'uploader', 'type' => '',
        'filer' => [UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')],
    ])->assertSee('Veuillez choisir un type de fichier');
});

it('C10 aucun fichier .php ni hors liste blanche n\'est accepté sur les routes d\'upload', function () {
    $program = makeProgram();
    $lot = makeLot($program->projet_id, ['numero' => 'T1']);
    $key = $program->projet_titre_key;
    $admin = makeAdmin();
    $php = fn ($name = 'shell.php') => UploadedFile::fake()->createWithContent($name, '<?php echo 1;');

    $this->actingAs($admin)->post('/ajouter_programme', validProgram(['titre' => 'PHP', 'thumb' => $php('x.php')]));
    foreach (['visuels', 'documents', 'plans'] as $type) {
        $this->actingAs($admin)->post("/modifier_programme/show/$key", ['action' => 'uploader', 'type' => $type, 'filer' => [$php(), $php('x.phtml')]]);
    }
    $this->actingAs($admin)->post("/modifier_programme/show/$key", [
        'action' => 'project', 'titre' => $program->projet_titre, 'livraison' => '2T2028', 'ville' => 'M', 'dept' => '34',
        'description' => 'd', 'key' => $key, 'idprojet' => $program->projet_id, 'thumb' => $php('t.php'),
    ]);
    $this->actingAs($admin)->post("/ajouter_lot/show/$key", validLot($program, ['plan' => $php('p.php')]));
    $this->actingAs($admin)->post("/gerer_lots/modifier/$key-$lot->id", editLot($program, $lot, ['plan' => $php('p.php')]));
    $this->actingAs($admin)->post("/ajouter_plan/show/$key", ['plan_upload' => [$php(), $php('plan-T1.pdf.php')]]);
    $this->actingAs($admin)->post("/ajouter_lots/show/$key", ['name' => $program->projet_id, 'departement' => '34', 'ville' => 'M', 'csv' => '1', 'csv_upload' => $php('i.php')]);

    $this->actingAs($admin)->post("/ajouter_plan/show/$key", ['plan_upload' => [UploadedFile::fake()->create('plan-T1.pdf', 10, 'application/pdf')]]);
    Storage::disk('programmes')->assertExists("$key/plans/plan-T1.pdf");

    foreach (Storage::disk('programmes')->allFiles() as $file) {
        expect($file)->not->toMatch('/\.(php\d?|phtml|phar)$/i');
        expect(Storage::disk('programmes')->get($file))->not->toContain('<?php');
    }
});

it('C15 gerer_lots/show liste tous les lots triés par id avec les 8 colonnes et les actions', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    $lots = [
        makeLot($program->projet_id, ['numero' => 'REF-B', 'statut' => 'act']),
        makeLot($program->projet_id, ['numero' => 'REF-A', 'statut' => '']),
        makeLot($program->projet_id, ['numero' => 'REF-C', 'statut' => 'opt']),
    ];

    $response = $this->actingAs(makeAdmin())->get("/gerer_lots/show/$key");

    $response->assertOk()
        ->assertSee('Corim promotion - Gérer les lots', false)
        ->assertSeeInOrder(['RÉFÉRENCE', 'STATUT', 'PROJET', 'PRIX', 'ACCESSION', 'CELLIER', 'TYPE', 'CAT.'], false)
        ->assertSeeInOrder(['REF-B', 'REF-A', 'REF-C'])
        ->assertSee("ajouter_lot/show/$key", false)
        ->assertSee('id="supprimer_all"', false);
    foreach ($lots as $lot) {
        $response->assertSee("gerer_lots/modifier/$key-$lot->id", false)
            ->assertSee('rel="'.$program->projet_id.'-'.$lot->id.'"', false);
    }
});

it('C16 ajouter_lot valide insère le lot avec les valeurs du programme et les cases à 0/1', function () {
    $program = makeProgram(['dept' => '34', 'projet_lieu' => 'MONTPELLIER']);

    $this->actingAs(makeAdmin())->post("/ajouter_lot/show/$program->projet_titre_key", validLot($program, ['option' => ['etage', 'terrasse']]))
        ->assertSee("L'appartement a bien été ajouté.", false);

    $lot = DB::table('appartements')->first();
    expect($lot->lot)->toBe((string) $program->projet_id)
        ->and($lot->statut)->toBe('')
        ->and($lot->client)->toBe('')
        ->and($lot->vendeur)->toBeNull()
        ->and((string) $lot->dept)->toBe('34')
        ->and($lot->ville)->toBe('MONTPELLIER')
        ->and($lot->etage)->toBe('1')
        ->and($lot->RDC)->toBe('0')
        ->and($lot->jardin)->toBe('0')
        ->and($lot->terrasse)->toBe('1');
});

it('C17 le plan d\'un lot est enregistré sous plan-{numero sans espaces}.pdf', function () {
    $program = makeProgram();

    $this->actingAs(makeAdmin())->post("/ajouter_lot/show/$program->projet_titre_key", validLot($program, [
        'numero' => 'C 12',
        'plan' => UploadedFile::fake()->create('quelconque.pdf', 20, 'application/pdf'),
    ]))->assertOk();

    Storage::disk('programmes')->assertExists("$program->projet_titre_key/plans/plan-C12.pdf");
});

it('C18 ajouter_lot sans numero, date, type ou cat affiche « Tous les champs sont requis »', function () {
    $program = makeProgram();
    $admin = makeAdmin();

    foreach (['numero', 'date', 'type', 'cat'] as $field) {
        $this->actingAs($admin)->post("/ajouter_lot/show/$program->projet_titre_key", validLot($program, [$field => '']))
            ->assertSee('Tous les champs sont requis');
    }
    expect(DB::table('appartements')->count())->toBe(0);
});

it('C19 gerer_lots/modifier met à jour les 16 champs et refuse un lot d\'un autre programme', function () {
    $program = makeProgram();
    $lot = makeLot($program->projet_id);
    $foreign = makeLot(makeProgram()->projet_id, ['prix' => '100 000']);
    $key = $program->projet_titre_key;
    $admin = makeAdmin();

    $values = [
        'date' => '1T2031', 'numero' => 'B 7', 'type' => 'T3', 'cat' => 'Maison', 'prix' => '250 000', 'prix_m2' => '4000',
        'cellier' => 'C2', 'accession' => 'ABORDABLE', 'etage' => 'R+1', 'rdc' => '-', 'jardin' => '25,4', 'terrasse' => '8',
        'superficie' => '70', 'stationnement' => '2', 'exposition' => 'SO', 'description' => 'Lot modifié',
    ];
    $this->actingAs($admin)->post("/gerer_lots/modifier/$key-$lot->id", editLot($program, $lot, $values))
        ->assertSee('Le lot a bien été modifié.')
        ->assertSee('Retour à la liste des lots');

    $row = DB::table('appartements')->find($lot->id);
    $columns = ['date' => 'date', 'numero' => 'numero', 'type' => 'type', 'cat' => 'categorie', 'prix' => 'prix', 'prix_m2' => 'prix_m2',
        'cellier' => 'cellier', 'accession' => 'accession', 'etage' => 'etage', 'rdc' => 'RDC', 'jardin' => 'jardin', 'terrasse' => 'terrasse',
        'superficie' => 'superficie', 'stationnement' => 'stationnement', 'exposition' => 'exposition', 'description' => 'description'];
    foreach ($columns as $field => $column) {
        expect($row->$column)->toBe($values[$field], $column);
    }

    $this->actingAs($admin)->post("/gerer_lots/modifier/$key-$foreign->id", editLot($program, $foreign, ['prix' => '1']));
    expect(DB::table('appartements')->find($foreign->id)->prix)->toBe('100 000');
});

it('C20 un lot de type T2B garde sa valeur à l\'enregistrement sans changement', function () {
    $program = makeProgram();
    $lot = makeLot($program->projet_id, ['type' => 'T2B', 'categorie' => 'Appartement (Bât A)']);
    $key = $program->projet_titre_key;
    $admin = makeAdmin();

    $html = $this->actingAs($admin)->get("/gerer_lots/modifier/$key-$lot->id")->assertOk()->getContent();
    expect($html)->toMatch('/<option[^>]*value="T2B"[^>]*selected/')
        ->toMatch('/<option[^>]*value="Appartement \(Bât A\)"[^>]*selected/');

    $this->actingAs($admin)->post("/gerer_lots/modifier/$key-$lot->id", editLot($program, $lot));
    $row = DB::table('appartements')->find($lot->id);
    expect($row->type)->toBe('T2B')->and($row->categorie)->toBe('Appartement (Bât A)');
});

it('C21 un prix « 185 000 » est stocké et restitué tel quel', function () {
    $program = makeProgram();
    $admin = makeAdmin();

    $this->actingAs($admin)->post("/ajouter_lot/show/$program->projet_titre_key", validLot($program, ['prix' => '185 000']));
    expect(DB::table('appartements')->value('prix'))->toBe('185 000');

    $grid = $this->actingAs($admin)->post("/index.php/json/JsonDatagrid/$program->projet_id/$admin->id", ['page' => 1, 'rp' => 100, 'sortname' => 'numero', 'sortorder' => 'asc'])->json();
    expect($grid['rows'][0]['cell'][3])->toBe('185 000');

    $api = json_decode($this->get('/index.php/firstsearch?action=nextjscorim')->getContent(), true);
    expect($api[0]['prix'])->toBe('185 000');
});

it('C22 l\'import CSV Windows-1252 de 17 colonnes insère une ligne par enregistrement', function () {
    $program = makeProgram(['dept' => '34', 'projet_lieu' => 'AGDE']);
    $key = $program->projet_titre_key;
    $admin = makeAdmin();
    $post = fn (string $content) => $this->actingAs($admin)->post("/ajouter_lots/show/$key", [
        'name' => $program->projet_id, 'departement' => '34', 'ville' => 'AGDE', 'csv' => '0',
        'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', $content),
    ]);

    $post(mb_convert_encoding(csvLine(csvRow('B202', 'Exposé sud')), 'Windows-1252', 'UTF-8'))
        ->assertSee('1 lot ont été ajouté');
    $lot = DB::table('appartements')->first();
    expect([$lot->date, $lot->type, $lot->categorie, $lot->prix, $lot->prix_m2, $lot->etage, $lot->RDC, $lot->jardin, $lot->terrasse,
        $lot->superficie, $lot->stationnement, $lot->exposition, $lot->description, $lot->numero, $lot->cellier, $lot->accession])
        ->toBe(['3T2028', 'T3', 'Appartement', '219 900', '', 'R2', '', '', '12,67', '63,66', '24-32', 'NE', 'Exposé sud', 'B202', 'C1', 'LIBRE'])
        ->and($lot->lot)->toBe((string) $program->projet_id)
        ->and($lot->ville)->toBe('AGDE')
        ->and((string) $lot->dept)->toBe('34');

    $post(csvLine(csvRow('B203')).csvLine(csvRow('B204')).csvLine(csvRow('B205')))->assertSee('3 lots ont été ajoutés');
    $post('')->assertSee("Problème lors de l'enregistrement", false);
    expect(DB::table('appartements')->count())->toBe(4);
});

it('C23 l\'import d\'un CSV UTF-8 conserve les accents sans double encodage', function () {
    $program = makeProgram();

    $this->actingAs(makeAdmin())->post("/ajouter_lots/show/$program->projet_titre_key", [
        'name' => $program->projet_id, 'departement' => '34', 'ville' => 'M', 'csv' => '0',
        'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', csvLine(csvRow('A1', 'Séjour exposé'))),
    ]);

    expect(DB::table('appartements')->value('description'))->toBe('Séjour exposé');
});

it('C24 l\'aperçu du CSV affiche les 17 en-têtes, colore les vides et disparaît après suppression', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    $admin = makeAdmin();

    $this->actingAs($admin)->post("/ajouter_lots/show/$key", [
        'name' => $program->projet_id, 'departement' => '34', 'ville' => 'M', 'csv' => '0',
        'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', csvLine(csvRow('A1'))),
    ]);
    $this->actingAs($admin)->get("/ajouter_lots/show/$key")
        ->assertSee('Fichier CSV sur le serveur')
        ->assertSeeInOrder(['Date de livraison', 'Type', 'Catégorie', 'Prix', 'Prix m²', 'Etage', 'RDC', 'Jardin', 'Terrasse', 'Superficie', 'Stationnement', 'Exposition', 'Description', "Plan d'architecte", 'Référence', 'Cellier', 'Accession'], false)
        ->assertSee('#A30106', false)
        ->assertSee('Supprimer le fichier CSV du serveur');

    $this->actingAs($admin)->post("/ajouter_lots/show/$key", ['csv_delete' => 'true']);
    $this->actingAs($admin)->get("/ajouter_lots/show/$key")->assertDontSee('Fichier CSV sur le serveur');
});

it('C25 ajouter_plan/show indique pour chaque lot si son plan existe', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A 1']);
    makeLot($program->projet_id, ['numero' => 'A2']);
    Storage::disk('programmes')->put("$key/plans/plan-A1.pdf", '%PDF-1.4');
    $admin = makeAdmin();

    $this->actingAs($admin)->get("/ajouter_plan/show/$key")
        ->assertOk()
        ->assertSee('plan-A1.pdf')
        ->assertSee('OK')
        ->assertSee('Le fichier <b style="color:#F60">plan-A2.pdf</b> est manquant', false);

    $empty = makeProgram();
    $this->actingAs($admin)->get("/ajouter_plan/show/$empty->projet_titre_key")
        ->assertSee('Veuillez tout d\'abord enregistrer les lots pour y associer les plans', false);
});

it('C26 ajouter_plan enregistre les PDF sous leur nom et refuse les autres fichiers', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A1']);

    $response = $this->actingAs(makeAdmin())->post("/ajouter_plan/show/$key", ['plan_upload' => [
        UploadedFile::fake()->create('plan-A1.pdf', 20, 'application/pdf'),
        UploadedFile::fake()->create('plan-A2.pdf', 20, 'application/pdf'),
        UploadedFile::fake()->createWithContent('plan-A3.txt', 'texte'),
    ]]);

    $disk = Storage::disk('programmes');
    $disk->assertExists("$key/plans/plan-A1.pdf");
    $disk->assertMissing("$key/plans/plan-A2.pdf");
    $disk->assertMissing("$key/plans/plan-A3.txt");
    expect(mb_strtolower(pageText($response)))->toContain('aucun lot');
});

it('C27 programme/show affiche la fiche connectée, redirige l\'anonyme et renvoie 404 sur clé inconnue', function () {
    $program = makeProgram(['projet_titre' => 'Cocoon Test', 'projet_lieu' => 'Castelnau', 'projet_description' => 'Belle résidence']);
    foreach (['act', 'act', 'res', 'opt', '', ''] as $status) {
        makeLot($program->projet_id, ['statut' => $status]);
    }
    $key = $program->projet_titre_key;

    $this->get("/programme/show/$key")->assertRedirect('/connexion');

    $this->actingAs(makeAdmin())->get("/programme/show/$key")
        ->assertOk()
        ->assertSee('Corim promotion - Programme', false)
        ->assertSee('COCOON TEST - CASTELNAU')
        ->assertSee('Belle résidence')
        ->assertSeeInOrder(['LOTS ACTÉS :', '2', 'RÉSERVATIONS :', '1', 'OPTIONS :', '1'], false)
        ->assertSee('<h3>VISUELS</h3>', false)
        ->assertSee('<h3>DOCUMENTS</h3>', false)
        ->assertSee('id="datagrid"', false);

    $this->actingAs(makeAdmin())->get('/programme/show/'.md5('inconnu'))->assertNotFound();
});

it('C16bis ajouter_lot et l\'import CSV encodent \' et & une seule fois comme AddAppartement, gerer_lots/modifier enregistre le texte saisi, les pages affichent le texte décodé', function () {
    $program = makeProgram(['projet_lieu' => "L'ÎLE & MER"]);
    $key = $program->projet_titre_key;
    $admin = makeAdmin();
    $typed = "Vue d'angle & jardin";
    $encoded = htmlspecialchars($typed, ENT_QUOTES);

    $this->actingAs($admin)->post("/ajouter_lot/show/$key", validLot($program, ['description' => $typed, 'numero' => 'Q1']))
        ->assertSee("L'appartement a bien été ajouté.", false);
    $lot = DB::table('appartements')->where('numero', 'Q1')->first();
    expect($lot->description)->toBe($encoded)
        ->and($lot->ville)->toBe('L&#039;ÎLE &amp; MER');

    $this->actingAs($admin)->post("/ajouter_lots/show/$key", [
        'name' => $program->projet_id, 'departement' => '34', 'ville' => $program->projet_lieu, 'csv' => '0',
        'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', csvLine(csvRow('Q2', $typed))),
    ]);
    $csvLot = DB::table('appartements')->where('numero', 'Q2')->first();
    expect($csvLot->description)->toBe($encoded)
        ->and($csvLot->ville)->toBe('L&#039;ÎLE &amp; MER');

    $form = $this->actingAs($admin)->get("/gerer_lots/modifier/$key-$lot->id")->assertOk();
    expect((string) $form->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($form))->toContain($typed);

    $this->actingAs($admin)->post("/gerer_lots/modifier/$key-$lot->id", editLot($program, DB::table('appartements')->find($lot->id), ['description' => $typed]))
        ->assertSee('Le lot a bien été modifié.');
    expect(DB::table('appartements')->find($lot->id)->description)->toBe($typed);

    $after = $this->actingAs($admin)->get("/gerer_lots/modifier/$key-$lot->id")->assertOk();
    expect((string) $after->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($after))->toContain($typed);
});
