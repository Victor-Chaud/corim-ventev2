<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

const EVO_HEADERS = ['Référence', 'Type', 'Catégorie', 'Prix', 'Prix m²', 'Étage', 'RDC', 'Jardin', 'Terrasse', 'Superficie', 'Stationnement', 'Exposition', 'Description', 'Plan', 'Cellier', 'Accession', 'Date de livraison'];

beforeEach(fn () => Storage::fake('programmes'));

function evoCsv(array ...$rows): string
{
    return implode('', array_map(fn ($row) => implode(';', $row)."\r\n", $rows));
}

function evoLotRow(string $number, array $overrides = []): array
{
    return array_merge([
        'Référence' => $number, 'Type' => 'T3', 'Catégorie' => 'Appartement', 'Prix' => '219 900', 'Prix m²' => '3 450',
        'Étage' => 'R2', 'RDC' => '', 'Jardin' => '', 'Terrasse' => '12,67', 'Superficie' => '63,66', 'Stationnement' => '24-32',
        'Exposition' => 'NE', 'Description' => 'Séjour lumineux', 'Plan' => '', 'Cellier' => 'C1', 'Accession' => 'LIBRE', 'Date de livraison' => '3T2028',
    ], $overrides);
}

function evoImport(object $program, string $content)
{
    return test()->actingAs(makeAdmin())->post("/ajouter_lots/show/$program->projet_titre_key", [
        'name' => $program->projet_id, 'departement' => '34', 'ville' => $program->projet_lieu, 'csv' => '0',
        'csv_upload' => UploadedFile::fake()->createWithContent('grille.csv', $content),
    ]);
}

function evoLot(object $program, string $number): ?object
{
    return DB::table('appartements')->where('lot', (string) $program->projet_id)->where('numero', $number)->first();
}

it('L2 la ligne d\'en-têtes du modèle n\'est pas importée comme un lot', function () {
    $program = makeProgram();
    $rows = [evoLotRow('A1'), evoLotRow('A2', ['Prix' => '250 000'])];

    evoImport($program, evoCsv(EVO_HEADERS, ...array_map('array_values', $rows)))->assertOk();

    expect(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(2)
        ->and(evoLot($program, 'Référence'))->toBeNull()
        ->and(DB::table('appartements')->where('type', 'Type')->exists())->toBeFalse();
    $a1 = evoLot($program, 'A1');
    expect([$a1->date, $a1->type, $a1->categorie, $a1->prix, $a1->prix_m2, $a1->etage, $a1->terrasse, $a1->superficie, $a1->stationnement, $a1->exposition, decoded($a1->description), $a1->cellier, $a1->accession])
        ->toBe(['3T2028', 'T3', 'Appartement', '219 900', '3 450', 'R2', '12,67', '63,66', '24-32', 'NE', 'Séjour lumineux', 'C1', 'LIBRE'])
        ->and(evoLot($program, 'A2')->prix)->toBe('250 000');
});

it('L2 les colonnes sont retrouvées par leur nom, dans n\'importe quel ordre, sans tenir compte des accents ni de la casse', function () {
    $program = makeProgram();
    $header = ['PRIX', 'reference', 'Date de livraison', 'SUPERFICIE', 'categorie', 'TYPE', 'etage', 'exposition', 'Stationnement', 'Accession'];
    $line = ['199 000', 'B7', '1T2029', '41,10', 'Appartement', 'T2', 'R3', 'SO', '12', 'LIBRE'];

    evoImport($program, evoCsv($header, $line))->assertOk();

    $lot = evoLot($program, 'B7');
    expect($lot)->not->toBeNull()
        ->and([$lot->prix, $lot->date, $lot->superficie, $lot->categorie, $lot->type, $lot->etage, $lot->exposition, $lot->stationnement, $lot->accession])
        ->toBe(['199 000', '1T2029', '41,10', 'Appartement', 'T2', 'R3', 'SO', '12', 'LIBRE'])
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(1);
});

it('L2 un fichier Excel enregistré en CSV Windows-1252 avec en-têtes accentués est lu par nom de colonne', function () {
    $program = makeProgram();
    $header = array_reverse(EVO_HEADERS);
    $line = array_reverse(array_values(evoLotRow('C3', ['Description' => 'Exposé sud'])));

    evoImport($program, mb_convert_encoding(evoCsv($header, $line), 'Windows-1252', 'UTF-8'))->assertOk();

    $lot = evoLot($program, 'C3');
    expect($lot)->not->toBeNull()
        ->and($lot->prix)->toBe('219 900')
        ->and($lot->date)->toBe('3T2028')
        ->and($lot->etage)->toBe('R2')
        ->and(decoded($lot->description))->toBe('Exposé sud')
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(1);
});

it('L2 un ancien fichier sans en-têtes reste lu par position', function () {
    $program = makeProgram(['projet_lieu' => 'AGDE']);
    $legacy = ['3T2028', 'T3', 'Appartement', '219 900', '', 'R2', '', '', '12,67', '63,66', '24-32', 'NE', 'Vue mer', 'plan.pdf', 'D4', 'C1', 'LIBRE'];

    evoImport($program, evoCsv($legacy, array_replace($legacy, [14 => 'D5', 3 => '230 000'])))->assertOk();

    $lot = evoLot($program, 'D4');
    expect([$lot->date, $lot->type, $lot->categorie, $lot->prix, $lot->etage, $lot->terrasse, $lot->superficie, $lot->stationnement, $lot->exposition, $lot->description, $lot->numero, $lot->cellier, $lot->accession])
        ->toBe(['3T2028', 'T3', 'Appartement', '219 900', 'R2', '12,67', '63,66', '24-32', 'NE', 'Vue mer', 'D4', 'C1', 'LIBRE'])
        ->and(evoLot($program, 'D5')->prix)->toBe('230 000')
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(2);
});

it('L2 une référence existante est mise à jour sans toucher statut, client ni vendeur ; une nouvelle est ajoutée ; rien n\'est supprimé', function () {
    $program = makeProgram();
    $other = makeProgram();
    $seller = makeCommercial();
    $reserve = makeLot($program->projet_id, ['numero' => 'E1', 'prix' => '100 000', 'superficie' => '40,00', 'statut' => 'res', 'client' => 'Mme CLIENTE Anne', 'vendeur' => $seller->id]);
    $absent = makeLot($program->projet_id, ['numero' => 'E9', 'prix' => '90 000']);
    $namesake = makeLot($other->projet_id, ['numero' => 'E1', 'prix' => '100 000']);

    $response = evoImport($program, evoCsv(
        EVO_HEADERS,
        array_values(evoLotRow('E1', ['Prix' => '120 000', 'Superficie' => '41,50', 'Prix m²' => '2 900'])),
        array_values(evoLotRow('E2', ['Prix' => '150 000'])),
    ))->assertOk();

    $updated = DB::table('appartements')->find($reserve->id);
    expect($updated->prix)->toBe('120 000')
        ->and($updated->superficie)->toBe('41,50')
        ->and($updated->prix_m2)->toBe('2 900')
        ->and($updated->statut)->toBe('res')
        ->and($updated->client)->toBe('Mme CLIENTE Anne')
        ->and((int) $updated->vendeur)->toBe($seller->id)
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->where('numero', 'E1')->count())->toBe(1)
        ->and(evoLot($program, 'E2')?->prix)->toBe('150 000')
        ->and(DB::table('appartements')->find($absent->id)->prix)->toBe('90 000')
        ->and(DB::table('appartements')->find($namesake->id)->prix)->toBe('100 000')
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(3);

    $text = pageText($response);
    expect($text)->toMatch('/1\D{0,30}ajout/u')->toMatch('/1\D{0,30}mis\w* à jour/u');
});

it('L2 réimporter la même grille ne crée aucun doublon', function () {
    $program = makeProgram();
    $content = evoCsv(EVO_HEADERS, array_values(evoLotRow('F1')), array_values(evoLotRow('F2')));

    evoImport($program, $content);
    evoImport($program, $content)->assertOk();

    expect(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(2);
});

it('L2 le modèle de fichier est téléchargeable depuis la page et contient la ligne d\'en-têtes', function () {
    $program = makeProgram();
    $admin = makeAdmin();
    $page = (string) $this->actingAs($admin)->get("/ajouter_lots/show/$program->projet_titre_key")->assertOk()->getContent();

    preg_match_all('/href="([^"]+)"/', $page, $links);
    $template = collect($links[1])->first(fn ($href) => preg_match('/mod[eè]le/i', $href) === 1);
    expect($template)->not->toBeNull('lien vers le modèle absent de la page');

    $response = $this->actingAs($admin)->get(html_entity_decode($template));
    $response->assertOk();
    $body = responseBody($response);
    if (! mb_check_encoding($body, 'UTF-8')) {
        $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
    }
    $firstLine = explode("\n", ltrim($body, "\xEF\xBB\xBF"))[0];
    $columns = array_map(fn ($c) => trim($c, " \r\""), explode(';', $firstLine));
    foreach (['Référence', 'Type', 'Prix', 'Superficie', 'Date de livraison'] as $header) {
        expect($columns)->toContain($header);
    }
});

it('L2 l\'aperçu avant import indique ajout ou mise à jour pour chaque ligne', function () {
    $program = makeProgram();
    makeLot($program->projet_id, ['numero' => 'G1']);
    $admin = makeAdmin();
    $key = $program->projet_titre_key;

    $this->actingAs($admin)->post("/ajouter_lots/uploadify/$key", [
        'Filedata' => UploadedFile::fake()->createWithContent('grille.csv', evoCsv(EVO_HEADERS, array_values(evoLotRow('G1')), array_values(evoLotRow('G2')))),
    ]);
    $text = pageText($this->actingAs($admin)->get("/ajouter_lots/show/$key")->assertOk());

    expect($text)->toMatch('/mise à jour/iu')->toMatch('/ajout/iu')
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(1);
});
