<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('clients'));

function searchClients(object $admin, string $search)
{
    return jsonAction($admin, 'SearchClients', ['f_recherche_client_search' => $search, 'f_recherche_client_type' => '1']);
}

it('C4 SearchClients admin : vide → 0, sans résultat → 2, résultat → call aux 14 colonnes', function () {
    $admin = makeAdmin();
    $client = makeClient();

    expect(searchClients($admin, '')->getContent())->toBe('{"errors":0}')
        ->and(searchClients($admin, 'INTROUVABLE')->getContent())->toBe('{"errors":2}');

    $data = searchClients($admin, 'DUPONT')->json();
    expect($data['errors'])->toBe(1)
        ->and($data['call'])->toHaveCount(1)
        ->and($data['call'][0])->toBe(['idclient' => (string) $client->idclient] + clientValues());
});

it('C5 la recherche clients porte sur 10 colonnes, pas sur civilite, observation, reponse', function () {
    $admin = makeAdmin();
    $searched = ['date', 'source', 'projets', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget'];
    foreach (CLIENT_FIELDS as $field) {
        makeClient([$field => 'JETON'.strtoupper($field)]);
    }

    foreach (CLIENT_FIELDS as $field) {
        $response = searchClients($admin, 'JETON'.strtoupper($field));
        if (in_array($field, $searched, true)) {
            expect($response->json('errors'))->toBe(1, $field)
                ->and($response->json('call.0.'.$field))->toBe('JETON'.strtoupper($field));
        } else {
            expect($response->getContent())->toBe('{"errors":2}', $field);
        }
    }
});

it('C6 ajouter_client sans nom affiche le message et n\'insère rien', function () {
    $response = $this->actingAs(makeAdmin())->post('/gestion_clients/ajouter_client', clientValues(['nom' => '']));

    $response->assertOk();
    expect(pageText($response))->toContain('<div class="alert alert-warning">Veuillez renseigner le nom du nouveau client</div>')
        ->and(DB::table('clients')->count())->toBe(0);
});

it('C7 ajouter_client insère les 13 valeurs saisies et affiche le message échappé', function () {
    $admin = makeAdmin();
    $page = $this->actingAs($admin)->get('/gestion_clients/ajouter_client')->assertOk();
    expect(pageText($page))->toContain('<h1>Ajouter un client</h1>')->toContain('Corim promotion - Gestion des clients - Ajouter un client');
    foreach (CLIENT_FIELDS as $field) {
        expect((string) $page->getContent())->toContain('name="'.$field.'"');
    }

    $response = $this->actingAs($admin)->post('/gestion_clients/ajouter_client', clientValues(['nom' => "D'ANGELO <b>Luc</b>"]));

    $row = (array) DB::table('clients')->first();
    unset($row['idclient']);
    expect(array_map('decoded', $row))->toBe(clientValues(['nom' => "D'ANGELO <b>Luc</b>"]))
        ->and((string) $response->getContent())->not->toContain('<b>Luc</b>')
        ->and(pageText($response))->toContain("Le client Mme D'ANGELO <b>Luc</b> a été ajouté");
});

it('C8 edition_client affiche les 13 champs préremplis et un POST valide met à jour la ligne', function () {
    $admin = makeAdmin();
    $client = makeClient();

    $page = $this->actingAs($admin)->get("/gestion_clients/edition_client/$client->idclient")->assertOk();
    $text = pageText($page);
    expect($text)->toContain("<h1>Édition d'un client</h1>")->toContain("Corim promotion - Gestion des clients - Édition d'un client")
        ->toMatch('#action="(https?://[^/"]+)?/gestion_clients/edition_client/'.$client->idclient.'"#');
    foreach (clientValues() as $field => $value) {
        expect($text)->toContain($value, $field);
    }

    $response = $this->actingAs($admin)->post("/gestion_clients/edition_client/$client->idclient", clientValues(['nom' => 'DURAND Marc', 'budget' => '300000']));
    expect(pageText($response))->toContain('Le client Mme DURAND Marc a été modifié');
    $row = DB::table('clients')->where('idclient', $client->idclient)->first();
    expect($row->nom)->toBe('DURAND Marc')->and($row->budget)->toBe('300000');
});

it('C9 edition_client avec un id inconnu n\'affiche que l\'en-tête ; sans id → 404', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->get('/gestion_clients/edition_client/999999');
    $response->assertOk();
    expect(pageText($response))->toContain("<h1>Édition d'un client</h1>")
        ->and((string) $response->getContent())->not->toContain('name="nom"');
    $this->actingAs($admin)->get('/gestion_clients/edition_client')->assertNotFound();
});

it('C10 la suppression exige un POST avec CSRF, passe par la confirmation et affiche « Le client a été supprimé »', function () {
    $admin = makeAdmin();
    $client = makeClient();

    $this->actingAs($admin)->get("/gestion_clients/supprimer_client/$client->idclient");
    expect(DB::table('clients')->where('idclient', $client->idclient)->exists())->toBeTrue();

    $response = $this->actingAs($admin)->post("/gestion_clients/supprimer_client/$client->idclient");
    expect(pageText($response))->toContain('<div class="alert alert-success" role="alert">Le client a été supprimé</div>')
        ->and(DB::table('clients')->where('idclient', $client->idclient)->exists())->toBeFalse();

    $other = makeClient();
    withoutTestingCsrfBypass();
    expect($this->actingAs($admin)->post("/gestion_clients/supprimer_client/$other->idclient")->getStatusCode())->toBe(419)
        ->and(DB::table('clients')->where('idclient', $other->idclient)->exists())->toBeTrue();

    $js = implode('', array_map('file_get_contents', glob(public_path('medias/js/*.js'))));
    expect($js)->toContain('Supprimer ce client')->toContain('Vous êtes sur le point de supprimer le client.<br />Continuez ?');
});

it('C11 telecharger_clients renvoie export.csv ISO-8859-1, ;, LF, en-tête 11 colonnes, table vide → fichier vide', function () {
    $admin = makeAdmin();

    $empty = $this->actingAs($admin)->get('/gestion_clients/telecharger_clients');
    $empty->assertOk();
    expect(responseBody($empty))->toBe('');

    makeClient();
    makeClient(['nom' => 'LÉGER Éric', 'email' => 'eric@exemple.test']);
    $response = $this->actingAs($admin)->get('/gestion_clients/telecharger_clients');

    expect((string) $response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('export.csv');
    $body = responseBody($response);
    expect(mb_check_encoding($body, 'UTF-8'))->toBeFalse();
    $lines = explode("\n", rtrim(mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1'), "\n"));
    expect($body)->not->toContain("\r")
        ->and($lines)->toBe([
            'date;source;projets;civilite;nom;adresse;tel;email;type_achat;type_logement;budget',
            '01/02/2026;Salon;LES JARDINS;Mme;DUPONT Claire;1 rue Test 34000 MONTPELLIER;0400000000;claire@exemple.test;Investissement;T2;200000',
            '01/02/2026;Salon;LES JARDINS;Mme;LÉGER Éric;1 rue Test 34000 MONTPELLIER;0400000000;eric@exemple.test;Investissement;T2;200000',
        ]);
});

it('C12 un CSV ISO-8859-1 sans en-tête de 13 colonnes est prévisualisé et importé avec les messages legacy', function () {
    $admin = makeAdmin();
    $row = fn (string $nom) => array_values(clientValues(['nom' => $nom, 'observation' => 'Très intéressé']));
    $upload = fn (string $content) => $this->actingAs($admin)->post('/gestion_clients/ajouter_clients', [
        'csv' => '0', 'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', $content),
    ]);

    $upload(mb_convert_encoding(clientCsv($row('PREMIER Élie')), 'ISO-8859-1', 'UTF-8'))->assertOk()->assertSee('1 client ont été ajouté');
    expect(DB::table('clients')->count())->toBe(1);
    $imported = (array) DB::table('clients')->first();
    unset($imported['idclient']);
    expect(array_map('decoded', $imported))->toBe(clientValues(['nom' => 'PREMIER Élie', 'observation' => 'Très intéressé']));

    $preview = pageText($this->actingAs($admin)->get('/gestion_clients/ajouter_clients')->assertOk());
    expect($preview)->toContain('<h1>Ajouter des clients en masse</h1>');
    expectInOrder($preview, ['Date', 'Source', 'Projets', 'Civilité', 'Nom', 'Adresse', 'Tél.', 'E-mail', 'Type achat', 'Type logement', 'Budget', 'Observation', 'Réponse', 'PREMIER Élie']);

    $upload(mb_convert_encoding(clientCsv($row('DEUX A'), $row('DEUX B')), 'ISO-8859-1', 'UTF-8'))->assertSee('2 clients ont été ajoutés');
    $upload('')->assertSee("Problème lors de l'enregistrement", false);
    expect(DB::table('clients')->count())->toBe(3);
});

it('C13 un fichier non CSV est refusé avec « Le format de fichier CSV n\'est pas reconnu »', function () {
    $admin = makeAdmin();

    foreach ([UploadedFile::fake()->createWithContent('import.php', '<?php echo 1;'), UploadedFile::fake()->createWithContent('import.txt', 'a;b'), UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg')] as $file) {
        $response = $this->actingAs($admin)->post('/gestion_clients/ajouter_clients', ['csv' => '0', 'csv_upload' => $file]);
        expectRouteExists($response, 'ajouter_clients');
        expect(pageText($response))->toContain("Le format de fichier CSV n'est pas reconnu");
    }
    expect(DB::table('clients')->count())->toBe(0);
});

it('C14 le fichier d\'import n\'est pas accessible par une URL publique', function () {
    $admin = makeAdmin();
    $this->actingAs($admin)->post('/gestion_clients/ajouter_clients', [
        'csv' => '0', 'csv_upload' => UploadedFile::fake()->createWithContent('import.csv', clientCsv(array_values(clientValues()))),
    ])->assertOk();
    expect(DB::table('clients')->count())->toBe(1);

    foreach (['/medias/clients/import.csv', '/medias/export/mydata.csv', '/medias/upload/import.csv', '/storage/import.csv'] as $url) {
        expect($this->get($url)->getStatusCode())->toBeIn([403, 404], $url);
    }
    expect(file_exists(public_path('medias/clients/import.csv')))->toBeFalse();
});
