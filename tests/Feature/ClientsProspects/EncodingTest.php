<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C21bis les prospects sont stockés encodés une seule fois (ENT_QUOTES), jamais doublement, et affichés décodés', function () {
    $w = workflowWorld();
    $admin = $w->corim;
    $typed = "D'ANGELO & Fils";
    $encoded = htmlspecialchars($typed, ENT_QUOTES);

    DB::table('d_prospects')->delete();
    jsonAction($w->prescriber, 'AddProspect', prospectPayload($w->program->projet_id, ['f_new_prospect_name' => $typed, 'f_new_prospect_sms' => $typed]));
    $prospect = DB::table('d_prospects')->first();
    expect($prospect->nomprospect)->toBe(htmlspecialchars("Mme & M. $typed Louis", ENT_QUOTES))
        ->and($prospect->smspromoteur)->toBe($encoded);

    $page = $this->actingAs($admin)->get('/ask_denonciations')->assertOk();
    expect((string) $page->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($page))->toContain($typed);
});
