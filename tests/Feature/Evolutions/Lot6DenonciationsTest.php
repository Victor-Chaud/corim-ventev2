<?php

use Illuminate\Support\Facades\DB;

function evoDenonciationsWorld(): object
{
    corimAdmin();
    $alice = makeCommercial(['name' => 'Alice Perf']);
    $bruno = makeCommercial(['name' => 'Bruno Perf']);
    $projet = makeProjet(['projet_titre' => 'PROGRAMME PERF']);
    $ids = [];
    foreach ([[$alice, 'ACQALICEUN', '2026-01-05 10:00:00'], [$alice, 'ACQALICEDEUX', '2026-02-05 10:00:00'], [$bruno, 'ACQBRUNO', '2026-03-05 10:00:00']] as [$user, $nom, $date]) {
        $ids[$nom] = DB::table('prospects')->insertGetId(prospectRow($projet->projet_id, $user->id, ['nomprospect' => $nom, 'date' => $date]), 'idprospect');
    }

    return (object) compact('alice', 'bruno', 'projet', 'ids');
}

it('L6 /denonciations affiche toujours toutes les lignes à l\'admin, avec date modifiable, dans l\'ordre décroissant', function () {
    $w = evoDenonciationsWorld();

    $response = $this->actingAs(makeAdmin())->get('/denonciations')->assertOk();
    $content = (string) $response->getContent();

    expectInOrder(pageText($response), ['ACQBRUNO', 'ACQALICEDEUX', 'ACQALICEUN']);
    expect($content)->toContain('Alice Perf')->toContain('Bruno Perf')->toContain('PROGRAMME PERF');
    foreach ($w->ids as $id) {
        expect($content)->toContain('id="datepicker_'.$id.'_den"');
    }
    expect($content)->toContain('value="05.03.2026"');
});

it('L6 /denonciations n\'affiche au prescripteur que ses propres lignes, sans champ date modifiable', function () {
    $w = evoDenonciationsWorld();

    $content = (string) $this->actingAs($w->alice)->get('/denonciations')->assertOk()->getContent();

    expect($content)->toContain('ACQALICEUN')->toContain('ACQALICEDEUX')->not->toContain('ACQBRUNO')
        ->not->toContain('class="datepicker"')
        ->toContain('05.02.2026');
});
