<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C21bis clients et prospects sont stockés encodés une seule fois (ENT_QUOTES), jamais doublement, et affichés décodés', function () {
    $w = workflowWorld();
    $admin = $w->corim;
    $typed = "D'ANGELO & Fils";
    $encoded = htmlspecialchars($typed, ENT_QUOTES);

    $this->actingAs($admin)->post('/gestion_clients/ajouter_client', clientValues(['nom' => $typed]));
    $client = DB::table('clients')->first();
    expect($client->nom)->toBe($encoded);

    $form = $this->actingAs($admin)->get("/gestion_clients/edition_client/$client->idclient")->assertOk();
    expect((string) $form->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($form))->toContain($typed);

    $this->actingAs($admin)->post("/gestion_clients/edition_client/$client->idclient", clientValues(['nom' => $typed]));
    expect(DB::table('clients')->where('idclient', $client->idclient)->value('nom'))->toBe($encoded);

    $this->actingAs($admin)->post("/gestion_clients/edition_client/$client->idclient", clientValues(['nom' => $encoded]));
    expect(DB::table('clients')->where('idclient', $client->idclient)->value('nom'))->toBe($encoded);

    DB::table('d_prospects')->delete();
    jsonAction($w->presc, 'AddProspect', prospectPayload($w->projet->projet_id, ['f_new_prospect_name' => $typed, 'f_new_prospect_sms' => $typed]));
    $prospect = DB::table('d_prospects')->first();
    expect($prospect->nomprospect)->toBe(htmlspecialchars("Mme & M. $typed Louis", ENT_QUOTES))
        ->and($prospect->smspromoteur)->toBe($encoded);

    $page = $this->actingAs($admin)->get('/ask_denonciations')->assertOk();
    expect((string) $page->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($page))->toContain($typed);
});
