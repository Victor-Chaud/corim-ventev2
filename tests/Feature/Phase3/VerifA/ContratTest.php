<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('programmes');
});

it('un fichier PHP déguisé sous une extension autorisée est refusé sur chaque route d\'upload, uploadify et diaporama compris', function () {
    $projet = makeProjet();
    makeLot($projet->projet_id, ['numero' => 'T1']);
    $key = $projet->projet_titre_key;
    $admin = makeAdmin();
    $fake = function (string $name) {
        $path = tempnam(sys_get_temp_dir(), 'verifa');
        file_put_contents($path, "<?php system(\$_GET['c']); ?>\n");

        return new UploadedFile($path, $name, null, null, true);
    };

    $this->actingAs($admin)->post('/ajouter_programme', [
        'titre' => 'DEGUISE', 'livraison' => '1T2030', 'ville' => 'X', 'description' => 'd', 'thumb' => $fake('thumb.png'),
    ])->assertOk();
    foreach (['visuels' => 'v.jpg', 'documents' => 'd.pdf', 'plans' => 'plan-T1.pdf'] as $type => $name) {
        $this->actingAs($admin)->post("/modifier_programme/show/$key", ['action' => 'uploader', 'type' => $type, 'filer' => [$fake($name)]])->assertOk();
    }
    $this->actingAs($admin)->post("/gestion_programmes/diaporama_projet/$key", ['diapo' => $fake('d.jpg')])->assertOk();
    $this->actingAs($admin)->post("/ajouter_plan/show/$key", ['plan_upload' => [$fake('plan-T1.pdf')]])->assertOk();
    expect($this->actingAs($admin)->post("/ajouter_plan/uploadify/$key", ['Filedata' => $fake('plan-T1.pdf')])->getContent())->not->toBe('1');
    expect($this->actingAs($admin)->post("/ajouter_lots/uploadify/$key", ['Filedata' => $fake('import.csv')])->getContent())->toBe('Invalid file type.');

    foreach (Storage::disk('programmes')->allFiles() as $file) {
        expect(Storage::disk('programmes')->get($file))->not->toContain('<?php');
    }
});

it('les envois légitimes passent sur les routes uploadify et diaporama', function () {
    $projet = makeProjet();
    makeLot($projet->projet_id, ['numero' => 'T1']);
    $key = $projet->projet_titre_key;
    $admin = makeAdmin();

    expect($this->actingAs($admin)->post("/ajouter_plan/uploadify/$key", ['Filedata' => UploadedFile::fake()->create('T1.pdf', 10, 'application/pdf')])->getContent())->toBe('1');
    expect($this->actingAs($admin)->post("/ajouter_lots/uploadify/$key", ['Filedata' => UploadedFile::fake()->createWithContent('lots.csv', "1T2030;T2\n")])->getContent())->toBe('1');
    $this->actingAs($admin)->post("/gestion_programmes/diaporama_projet/$key", ['diapo' => UploadedFile::fake()->image('vue.jpg')])->assertOk();

    Storage::disk('programmes')->assertExists(["$key/plans/plan-T1.pdf", "$key/import.csv", "$key/diapo/vue.jpg"]);
});

it('la route /medias ne sert rien hors du dossier du programme ni hors liste blanche', function () {
    $projet = makeProjet();
    $key = $projet->projet_titre_key;
    Storage::disk('programmes')->put("$key/plans/plan-T1.pdf", '%PDF-1.4');
    Storage::disk('programmes')->put("$key/import.csv", 'a;b');
    Storage::disk('programmes')->put("$key/documents/x.php", '<?php');

    $this->get("/medias/programmes/$key/plans/plan-T1.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    foreach (["$key/import.csv", "$key/documents/x.php", "$key/plans/../import.csv"] as $path) {
        expect($this->get("/medias/programmes/$path")->getStatusCode())->toBe(404, $path);
    }
});
