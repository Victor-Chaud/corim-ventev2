<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('programmes');
});

function linkedRows(int $lotId, int $programId, int $userId): void
{
    $base = ['nom' => 'Prospect Test', 'adresse' => '1 rue Test', 'message' => '', 'idprojet' => $programId, 'date' => '2026-01-01 10:00:00'];
    DB::table('d_options')->insert($base + ['iduser' => $userId, 'iddlot' => $lotId]);
    DB::table('d_reservations')->insert($base + ['iduser' => $userId, 'iddlot' => $lotId]);
    DB::table('options')->insert($base + ['iduser' => $userId, 'idlot' => $lotId, 'statut' => 1]);
    DB::table('reservations')->insert($base + ['iduser' => $userId, 'idlot' => $lotId, 'statut' => 1]);
    DB::table('acces_lot')->insert(['iduser' => $userId, 'idlot' => $lotId]);
}

function linkedCount(int $lotId): int
{
    return DB::table('d_options')->where('iddlot', $lotId)->count()
        + DB::table('d_reservations')->where('iddlot', $lotId)->count()
        + DB::table('options')->where('idlot', $lotId)->count()
        + DB::table('reservations')->where('idlot', $lotId)->count();
}

function datagrid(object $user, int $programId, array $data = [], ?int $urlUser = null)
{
    return test()->actingAs($user)->post('/index.php/json/JsonDatagrid/'.$programId.'/'.($urlUser ?? $user->id), array_merge(
        ['page' => '1', 'rp' => '100', 'sortname' => 'numero', 'sortorder' => 'asc', 'query' => '', 'qtype' => 'numero'],
        $data
    ));
}

it('C11 ArchivageProjet et DesarchivageProjet basculent archive, refusés hors admin', function () {
    $program = makeProgram();
    $key = $program->projet_titre_key;
    $admin = makeAdmin();
    $archive = fn () => (int) DB::table('projet')->where('projet_id', $program->projet_id)->value('archive');

    expect($this->actingAs($admin)->post('/index.php/json/ArchivageProjet', ['key' => $key])->json())->toBe(['errors' => 1])
        ->and($archive())->toBe(1)
        ->and($this->actingAs($admin)->post('/index.php/json/ArchivageProjet', ['key' => $key])->json())->toBe(['errors' => 0]);

    expect($this->actingAs($admin)->post('/index.php/json/DesarchivageProjet', ['key' => $key])->json())->toBe(['errors' => 1])
        ->and($archive())->toBe(0);

    app('auth')->forgetGuards();
    expect($this->post('/index.php/json/ArchivageProjet', ['key' => $key])->getStatusCode())->toBeIn([302, 401, 403]);
    expect($this->actingAs(makeCommercial())->post('/index.php/json/ArchivageProjet', ['key' => $key])->getStatusCode())->toBeIn([302, 401, 403]);
    expect($archive())->toBe(0);
});

it('C12 SupprimerLot supprime le lot, ses options et réservations comme l\'ancien code, refuse des identifiants discordants', function () {
    $admin = makeAdmin();
    $program = makeProgram();
    $lot = makeLot($program->projet_id);
    $keep = makeLot($program->projet_id);
    linkedRows($lot->id, $program->projet_id, $admin->id);
    linkedRows($keep->id, $program->projet_id, $admin->id);
    $otherProgram = makeProgram();

    expect($this->actingAs($admin)->post('/index.php/json/SupprimerLot', ['idlot' => $keep->id, 'idprojet' => $otherProgram->projet_id])->json())
        ->toBe(['errors' => 0])
        ->and(DB::table('appartements')->find($keep->id))->not->toBeNull();

    expect($this->actingAs($admin)->post('/index.php/json/SupprimerLot', ['idlot' => $lot->id, 'idprojet' => $program->projet_id])->json())
        ->toBe(['errors' => 1])
        ->and(DB::table('appartements')->find($lot->id))->toBeNull()
        ->and(linkedCount($lot->id))->toBe(0)
        ->and(linkedCount($keep->id))->toBe(4)
        ->and(DB::table('acces_lot')->where('idlot', $lot->id)->count())->toBe(1);

    $this->actingAs($admin)->get("/gerer_lots/show/$program->projet_titre_key")
        ->assertOk()
        ->assertDontSee("gerer_lots/modifier/$program->projet_titre_key-$lot->id", false)
        ->assertSee("gerer_lots/modifier/$program->projet_titre_key-$keep->id", false);
});

it('C13 DeleteAllInProject supprime les lots du projet et leurs acces_lot, sans toucher aux options comme l\'ancien code', function () {
    $admin = makeAdmin();
    $program = makeProgram();
    $lots = [makeLot($program->projet_id), makeLot($program->projet_id)];
    $other = makeLot(makeProgram()->projet_id);
    foreach ([...$lots, $other] as $lot) {
        DB::table('acces_lot')->insert(['iduser' => $admin->id, 'idlot' => $lot->id]);
    }
    linkedRows($other->id, $program->projet_id, makeCommercial()->id);
    $base = ['nom' => 'Prospect Test', 'adresse' => '1 rue Test', 'message' => '', 'idprojet' => $program->projet_id, 'date' => '2026-01-01 10:00:00'];
    DB::table('options')->insert($base + ['iduser' => $admin->id, 'idlot' => $lots[0]->id, 'statut' => 1]);

    expect($this->actingAs($admin)->post('/index.php/json/DeleteAllInProject', ['project' => $program->projet_id])->json())->toBe(['errors' => 1])
        ->and(DB::table('appartements')->where('lot', (string) $program->projet_id)->count())->toBe(0)
        ->and(DB::table('acces_lot')->whereIn('idlot', array_column($lots, 'id'))->count())->toBe(0)
        ->and(DB::table('acces_lot')->where('idlot', $other->id)->count())->toBe(2)
        ->and(DB::table('options')->where('idlot', $lots[0]->id)->count())->toBe(1);

    $this->actingAs($admin)->post("/index.php/json/JsonDatagrid/$program->projet_id/$admin->id", ['page' => '1', 'rp' => '100', 'sortname' => 'numero', 'sortorder' => 'asc'])
        ->assertOk()->assertJsonPath('rows', []);

    expect($this->actingAs($admin)->post('/index.php/json/DeleteAllInProject', ['project' => $program->projet_id])->json())->toBe(['errors' => 0]);
});

it('C14 DeleteFile et DeleteDiapo confinent la suppression au dossier du programme', function () {
    $admin = makeAdmin();
    $key = makeProgram()->projet_titre_key;
    $disk = Storage::disk('programmes');
    $disk->put("$key/documents/notice.pdf", '%PDF');
    $disk->put("$key/diapo/photo.jpg", 'jpg');
    $disk->put('secret.txt', 'secret');
    $disk->put("$key/thumb/thumb.jpg", 'thumb');

    foreach (["/medias/programmes/$key/documents/../../secret.txt", "/medias/programmes/$key/thumb/thumb.jpg", '/medias/programmes/../../.env', '/../../.env'] as $url) {
        expect($this->actingAs($admin)->post('/index.php/json/DeleteFile', ['url' => $url])->json())->toBe(['errors' => 0], $url);
        expect($this->actingAs($admin)->post('/index.php/json/DeleteDiapo', ['key' => $url])->json())->toBe(['errors' => 0], $url);
    }
    $disk->assertExists('secret.txt');
    $disk->assertExists("$key/thumb/thumb.jpg");

    expect($this->actingAs($admin)->post('/index.php/json/DeleteFile', ['url' => "/medias/programmes/$key/documents/notice.pdf"])->json())->toBe(['errors' => 1]);
    $disk->assertMissing("$key/documents/notice.pdf");
    expect($this->actingAs($admin)->post('/index.php/json/DeleteDiapo', ['key' => "/medias/programmes/$key/diapo/photo.jpg"])->json())->toBe(['errors' => 1]);
    $disk->assertMissing("$key/diapo/photo.jpg");

    $disk->put("$key/documents/autre.pdf", '%PDF');
    $this->actingAs(makeCommercial())->post('/index.php/json/DeleteFile', ['url' => "/medias/programmes/$key/documents/autre.pdf"]);
    $disk->assertExists("$key/documents/autre.pdf");
});

it('C28 JsonDatagrid admin renvoie page, total et lignes à 16 cellules', function () {
    $admin = makeAdmin();
    $program = makeProgram();
    $key = $program->projet_titre_key;
    makeLot($program->projet_id, ['numero' => 'A 1', 'statut' => 'opt', 'superficie' => '45,20']);
    makeLot($program->projet_id, ['numero' => 'A2', 'statut' => 'res']);
    makeLot($program->projet_id, ['numero' => 'A3', 'statut' => 'act']);
    makeLot($program->projet_id, ['numero' => 'A4', 'statut' => '']);
    makeLot(makeProgram()->projet_id);
    Storage::disk('programmes')->put("$key/plans/plan-A1.pdf", '%PDF');

    $data = datagrid($admin, $program->projet_id)->assertOk()->json();

    expect(array_keys($data))->toBe(['page', 'total', 'rows'])
        ->and($data['page'])->toBe('1')
        ->and($data['total'])->toBe('4')
        ->and($data['rows'])->toHaveCount(4);
    $byNumber = collect($data['rows'])->keyBy(fn ($row) => $row['cell'][13]);
    foreach ($data['rows'] as $row) {
        expect(array_keys($row))->toBe(['id', 'cell'])->and($row['id'])->toBeString()->and($row['cell'])->toHaveCount(16)
            ->and($row['cell'][9])->toEndWith(' m²');
    }
    expect($byNumber['A 1']['cell'][0])->toBe('<span class="opt">2T2028</span>')
        ->and($byNumber['A2']['cell'][0])->toBe('<span class="res">2T2028</span>')
        ->and($byNumber['A3']['cell'][0])->toBe('<span class="act">2T2028</span>')
        ->and($byNumber['A4']['cell'][0])->toBe('2T2028')
        ->and($byNumber['A 1']['cell'][9])->toBe('45,20 m²')
        ->and($byNumber['A 1']['cell'][12])->toBe('<a href="/medias/programmes/'.$key.'/plans/plan-A1.pdf" target="_blank" class="btn btn-info btn-xs btn-block">Plan</a>')
        ->and($byNumber['A2']['cell'][12])->toBe('-');
});

it('C29 JsonDatagrid prescripteur ne renvoie que ses lots acces_lot de session, recherche comprise', function () {
    $program = makeProgram();
    $mine = makeLot($program->projet_id, ['numero' => 'M1']);
    $theirs = makeLot($program->projet_id, ['numero' => 'M2']);
    $me = makeCommercial();
    $other = makeCommercial();
    DB::table('acces')->insert([['iduser' => $me->id, 'idprojet' => $program->projet_id], ['iduser' => $other->id, 'idprojet' => $program->projet_id]]);
    DB::table('acces_lot')->insert([['iduser' => $me->id, 'idlot' => $mine->id], ['iduser' => $other->id, 'idlot' => $theirs->id]]);

    $ids = fn ($response) => array_column($response->json('rows'), 'id');

    expect($ids(datagrid($me, $program->projet_id)))->toBe([(string) $mine->id])
        ->and($ids(datagrid($me, $program->projet_id, [], $other->id)))->toBe([(string) $mine->id])
        ->and($ids(datagrid($me, $program->projet_id, ['query' => 'M', 'qtype' => 'numero'])))->toBe([(string) $mine->id]);

    $nobody = makeCommercial();
    datagrid($nobody, $program->projet_id)->assertOk()->assertJsonPath('rows', []);
});

it('C30 sortname ou qtype hors liste blanche ne provoquent jamais d\'erreur SQL', function () {
    $admin = makeAdmin();
    $program = makeProgram();
    makeLot($program->projet_id);

    foreach ([['sortname' => 'id;DROP TABLE user'], ['sortorder' => 'asc;--'], ['qtype' => 'numero) OR 1=1 --', 'query' => 'x'], ['query' => "a'b"], ['qtype' => 'cat', 'query' => 'Appart'], ['sortname' => 'cat'], ['rp' => '1;--'], ['page' => 'x']] as $data) {
        $response = datagrid($admin, $program->projet_id, $data);
        expect($response->getStatusCode())->toBeIn([200, 422])
            ->and($response->getContent())->not->toContain('SQLSTATE');
    }
    expect(Schema::hasTable('user'))->toBeTrue();
});
