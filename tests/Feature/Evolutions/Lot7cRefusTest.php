<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

dataset('refus', [
    'option' => ['RejetAddOption', 'f_refuser_ask_option', 'askopt', 'd_options', 'r_options'],
    'réservation' => ['RejetAddResa', 'f_refuser_ask_resa', 'askres', 'd_reservations', 'r_reservations'],
]);

it('L7c refuser une demande garde une trace complète et libère toujours le lot', function (string $action, string $prefix, string $lotName, string $demandes, string $refus) {
    $w = workflowWorld();
    $lot = $w->lots[$lotName];
    $this->travelTo('2026-11-04 15:30:00');

    expect(jsonAction($w->corim, $action, [$prefix.'_lot' => $lot->id, $prefix.'_user' => $w->presc->id, $prefix.'_sms' => 'Dossier incomplet'])->getContent())
        ->toBe('{"errors":1}');

    $row = DB::table($refus)->where('idlot', $lot->id)->first();
    expect($row)->not->toBeNull()
        ->and(decoded($row->nom))->toBe('Mme ACQUEREUR Anne')
        ->and((int) $row->iduser)->toBe($w->presc->id)
        ->and((int) $row->idprojet)->toBe($w->projet->projet_id)
        ->and($row->numero)->toBe($lot->numero)
        ->and((string) $row->date)->toBe('2026-01-10 10:00:00')
        ->and((string) $row->date_refus)->toBe('2026-11-04 15:30:00')
        ->and(decoded($row->message))->toContain('Dossier incomplet')
        ->and(DB::table($demandes)->where('iddlot', $lot->id)->count())->toBe(0)
        ->and(lotStatut($lot->id))->toBe('')
        ->and(sentMails())->toHaveCount(1);
})->with('refus');

it('L7c refuser une dénonciation enregistre la date de décision', function () {
    $w = workflowWorld();
    $this->travelTo('2026-11-04 16:45:00');

    jsonAction($w->corim, 'RejetAddDenonciation', ['f_refuser_ask_denonciation_lot' => $w->askProspect, 'f_refuser_ask_denonciation_user' => $w->presc->id, 'f_refuser_ask_denonciation_sms' => 'Déjà connu']);

    $row = DB::table('r_prospects')->where('idprospect', $w->askProspect)->first();
    expect((string) $row->date)->toBe('2026-01-20 10:00:00')
        ->and((string) $row->date_refus)->toBe('2026-11-04 16:45:00');
});

it('L7c les pages d\'annulation existantes gardent le libellé « annulées »', function () {
    $w = workflowWorld();

    expect(pageText($this->actingAs($w->corim)->get('/options/refused')->assertOk()))->toContain('<h1>Options annulées</h1>')
        ->and(pageText($this->actingAs($w->corim)->get('/reservations/refused')->assertOk()))->toContain('<h1>Réservations annulées</h1>');
});

function EVO_REFUS_URL(string $table): string
{
    return $table === 'r_options' ? '/options/refusees' : '/reservations/refusees';
}

function evoRefusWorld(): object
{
    corimAdmin();
    $alice = makeCommercial(['name' => 'Alice Refus']);
    $bruno = makeCommercial(['name' => 'Bruno Refus']);
    $projet = makeProjet(['projet_titre' => 'PROGRAMME REFUS']);
    foreach (['r_options', 'r_reservations'] as $table) {
        foreach ([[$alice, 'ACQALICE', 'REFA'], [$bruno, 'ACQBRUNO', 'REFB']] as [$user, $nom, $numero]) {
            $lot = makeLot($projet->projet_id, ['numero' => $numero.'-'.$table]);
            DB::table($table)->insert([
                'iduser' => $user->id, 'nom' => $nom.'-'.$table, 'adresse' => 'VILLE 34000', 'message' => 'Message'.SEPARATOR.'Motif '.$nom,
                'idlot' => $lot->id, 'idprojet' => $projet->projet_id, 'numero' => $lot->numero,
                'date' => '2026-10-01 10:00:00', 'date_refus' => '2026-10-02 11:00:00',
            ]);
        }
    }

    return (object) compact('alice', 'bruno', 'projet');
}

dataset('pages_refus', [
    'options' => ['r_options', 'Options refusées'],
    'réservations' => ['r_reservations', 'Réservations refusées'],
]);

it('L7c les pages des refus : l\'admin voit tout, le prescripteur seulement les siens', function (string $table, string $titre) {
    $w = evoRefusWorld();
    $url = EVO_REFUS_URL($table);

    $admin = pageText($this->actingAs(makeAdmin())->get($url)->assertOk());
    expect($admin)->toContain($titre)
        ->toContain('ACQALICE-'.$table)->toContain('ACQBRUNO-'.$table)
        ->toContain('PROGRAMME REFUS')->toContain('Alice Refus')->toContain('Bruno Refus')
        ->toContain('REFA-'.$table)->toContain('Motif ACQALICE')
        ->toContain('02.10.2026');

    app('auth')->forgetGuards();
    $alice = pageText($this->actingAs($w->alice)->get($url)->assertOk());
    expect($alice)->toContain('ACQALICE-'.$table)->not->toContain('ACQBRUNO-'.$table)->not->toContain('Motif ACQBRUNO');

    app('auth')->forgetGuards();
    $this->get($url)->assertRedirect('/connexion');
})->with('pages_refus');

it('L7c les pages des refus échappent les messages', function () {
    $w = evoRefusWorld();
    DB::table('r_options')->update(['message' => 'Avant<script>alert("x")</script>']);

    $content = (string) $this->actingAs(makeAdmin())->get(EVO_REFUS_URL('r_options'))->assertOk()->getContent();

    expect($content)->not->toContain('<script>alert("x")</script>')->toContain('&lt;script&gt;');
});
