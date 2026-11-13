<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

dataset('doubled_approvals', [
    'dénonciation validée' => ['AcceptAddDenonciation', 'prospects'],
    'dénonciation refusée' => ['RejetAddDenonciation', 'r_prospects'],
    'option validée' => ['AcceptAddOption', 'options'],
    'option refusée' => ['RejetAddOption', null],
    'réservation validée' => ['AcceptAddResa', 'reservations'],
    'réservation refusée' => ['RejetAddResa', null],
]);

it('L1 deux appels successifs à la même décision ne créent qu\'une ligne et n\'envoient qu\'un mail', function (string $action, ?string $table) {
    $w = workflowWorld();
    $data = workflowActions($w)[$action];
    $before = $table ? DB::table($table)->count() : 0;

    expect(jsonAction($w->corim, $action, $data)->getContent())->toBe('{"errors":1}');
    $after = workflowState();
    jsonAction($w->corim, $action, $data);

    expect(sentMails())->toHaveCount(1);
    if ($table) {
        expect(DB::table($table)->count())->toBe($before + 1);
    }
    expect(workflowState())->toBe($after);
})->with('doubled_approvals');

it('L1 un double clic sur Valider une dénonciation donne une seule ligne dans prospects pour cet acquéreur', function () {
    $w = workflowWorld();
    $data = workflowActions($w)['AcceptAddDenonciation'];

    jsonAction($w->corim, 'AcceptAddDenonciation', $data);
    jsonAction($w->corim, 'AcceptAddDenonciation', $data);

    expect(DB::table('prospects')->where('nomprospect', 'M PROSPECT Paul')->count())->toBe(1)
        ->and(DB::table('d_prospects')->where('idprospect', $w->askProspect)->count())->toBe(0)
        ->and(sentMails())->toHaveCount(1);
});

it('L1 deux envois identiques de AddProspect ne créent qu\'une dénonciation et un seul e-mail', function () {
    $w = workflowWorld();
    DB::table('d_prospects')->delete();
    $payload = prospectPayload($w->program->projet_id);

    expect(jsonAction($w->prescriber, 'AddProspect', $payload)->getContent())->toBe('{"errors":1}')
        ->and(jsonAction($w->prescriber, 'AddProspect', $payload)->getContent())->toBe('{"errors":1}')
        ->and(DB::table('d_prospects')->count())->toBe(1)
        ->and(sentMails())->toHaveCount(1);
});

it('L1 le bouton de création d\'une dénonciation est désactivé pendant l\'envoi', function () {
    $js = (string) file_get_contents(public_path('medias/js/global.js'));
    preg_match('/\$\("#submit_f_new_prospect"\)\.click\(function\(\)\{(.*?)return false;/s', $js, $m);

    expect($m[1] ?? '')->toContain("prop('disabled', true)")->toContain("complete: function(){ btn.prop('disabled', false); }");
});
