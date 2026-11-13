<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('programmes'));

function evoPdf(string $name, string $content = '%PDF-1.4 plan'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

dataset('plan_names', ['A12.pdf', 'plan-A12.pdf', 'Plan A12.pdf', 'plan.A12.pdf', 'PLAN_a12.PDF', 'Plan-A12.pdf']);

it('L3 l\'envoi en masse range le plan sous plan-{référence}.pdf quel que soit le nom du fichier', function (string $name) {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);
    makeLot($program->projet_id, ['numero' => 'A1']);

    $this->actingAs(makeAdmin())->post("/ajouter_plan/show/$key", ['plan_upload' => [evoPdf($name)]])->assertOk();

    $disk = Storage::disk('programmes');
    $disk->assertExists("$key/plans/plan-A12.pdf");
    expect($disk->files("$key/plans"))->toBe(["$key/plans/plan-A12.pdf"]);
})->with('plan_names');

it('L3 l\'envoi un par un (uploadify) applique la même correspondance', function (string $name) {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);

    $response = $this->actingAs(makeAdmin())->post("/ajouter_plan/uploadify/$key", ['Filedata' => evoPdf($name)]);

    expect((string) $response->getContent())->toBe('1');
    expect(Storage::disk('programmes')->files("$key/plans"))->toBe(["$key/plans/plan-A12.pdf"]);
})->with(['A12.pdf', 'Plan A12.pdf', 'plan.A12.pdf']);

it('L3 un fichier qui ne correspond à aucun lot est refusé avec « aucun lot » et n\'est pas stocké', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);
    $other = makeProgram();
    makeLot($other->projet_id, ['numero' => 'A99']);
    $admin = makeAdmin();

    $text = pageText($this->actingAs($admin)->post("/ajouter_plan/show/$key", ['plan_upload' => [evoPdf('A99.pdf'), evoPdf('A12.pdf')]])->assertOk());
    expect(mb_strtolower($text))->toContain('aucun lot')
        ->and($text)->toContain('A99');

    $uploadify = $this->actingAs($admin)->post("/ajouter_plan/uploadify/$key", ['Filedata' => evoPdf('plan-B3.pdf')]);
    expect((string) $uploadify->getContent())->not->toBe('1')
        ->and(mb_strtolower((string) $uploadify->getContent()))->toContain('aucun lot');

    expect(Storage::disk('programmes')->files("$key/plans"))->toBe(["$key/plans/plan-A12.pdf"]);
});

it('L3 un nouvel envoi écrase le plan existant du lot', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);
    $disk = Storage::disk('programmes');
    $disk->put("$key/plans/plan-A12.pdf", '%PDF-1.4 ancien');

    $this->actingAs(makeAdmin())->post("/ajouter_plan/show/$key", ['plan_upload' => [evoPdf('Plan A12.pdf', '%PDF-1.4 nouveau')]])->assertOk();

    expect($disk->get("$key/plans/plan-A12.pdf"))->toBe('%PDF-1.4 nouveau')
        ->and($disk->files("$key/plans"))->toBe(["$key/plans/plan-A12.pdf"]);
});

it('L3 le plan envoyé est servi au chemin public de l\'API et signalé OK sur la page', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);
    $admin = makeAdmin();

    $this->actingAs($admin)->post("/ajouter_plan/show/$key", ['plan_upload' => [evoPdf('plan.A12.pdf', '%PDF-1.4 public')]]);

    $response = $this->get("/medias/programmes/$key/plans/plan-A12.pdf");
    expect($response->getStatusCode())->toBe(200)
        ->and(responseBody($response))->toBe('%PDF-1.4 public');
    $this->actingAs($admin)->get("/ajouter_plan/show/$key")->assertOk()
        ->assertDontSee('Le fichier <b style="color:#F60">plan-A12.pdf</b> est manquant', false);
});

it('L3 un fichier non PDF reste refusé même s\'il correspond à un lot', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A12']);

    $this->actingAs(makeAdmin())->post("/ajouter_plan/show/$key", ['plan_upload' => [
        UploadedFile::fake()->createWithContent('A12.php', '<?php echo 1;'),
        UploadedFile::fake()->createWithContent('A12.pdf.php', '<?php echo 1;'),
    ]]);

    expect(Storage::disk('programmes')->files("$key/plans"))->toBe([]);
});
