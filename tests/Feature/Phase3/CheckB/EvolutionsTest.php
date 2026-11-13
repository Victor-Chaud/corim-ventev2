<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('VB-E1 un texte trop long sur /messages_automatiques est refusé avec un message visible, sans perte silencieuse', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->followingRedirects()->post('/messages_automatiques', [
        'option_validee' => str_repeat('a', 5001),
        'signature' => 'Signature',
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and(DB::table('messages_auto')->count())->toBe(0)
        ->and((string) $response->getContent())->toContain('alert-warning');
});

it('VB-E2 chaque bouton de décision du workflow est désactivé au clic et réactivé en cas d\'erreur (global.js)', function () {
    $js = (string) file_get_contents(__DIR__.'/../../../../public/medias/js/global.js');
    $buttons = ['annuler_la_vente', 'passer_en_act', 'refuser_ask_resa', 'confirm_ask_resa', 'confirm_ask_denonciation', 'refuser_ask_denonciation', 'annuler_resa', 'passer_en_resa', 'annuler_option', 'refuser_ask_option', 'confirm_ask_option'];

    foreach ($buttons as $button) {
        $start = strpos($js, '$("#submit_f_'.$button.'").click(');
        expect($start)->not->toBeFalse($button);
        $block = substr($js, (int) $start, 900);
        expect($block)->toContain("var btn = $(this).prop('disabled', true);")
            ->toContain("error: function(){ btn.prop('disabled', false); }")
            ->toContain("if(call.errors!=1){ btn.prop('disabled', false); }");
    }
});

it('VB-E3 les modales de décision sont pré-remplies avec le message type, échappé', function () {
    $w = workflowWorld();
    DB::table('messages_auto')->insert([
        ['cle' => 'option_annulee', 'texte' => '</textarea><script>alert(1)</script>', 'updated_at' => now()],
        ['cle' => 'vente_annulee', 'texte' => 'Vente annulée pour {acquereur}', 'updated_at' => now()],
    ]);

    $options = (string) $this->actingAs($w->corim)->get('/options')->getContent();
    expect($options)->toContain('&lt;/textarea&gt;&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');

    app('auth')->forgetGuards();
    expect(pageText($this->actingAs($w->corim)->get('/ventes')))->toContain('Vente annulée pour {acquereur}');
});
