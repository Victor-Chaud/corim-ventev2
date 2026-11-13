<?php

use App\Models\User;

const EVO_MESSAGES_URL = '/messages_automatiques';

const EVO_SETTINGS_TABLES = ['messages_auto'];

beforeEach(fn () => legacyMailBase());

function evoSaveMessages(User $user, array $texts, string $signature)
{
    app('auth')->forgetGuards();

    return test()->actingAs($user)->post(EVO_MESSAGES_URL, $texts + ['signature' => $signature]);
}

function evoMailHtml(): string
{
    $mails = sentMails();
    expect($mails)->toHaveCount(1);

    return (string) $mails[0]->getHtmlBody();
}

it('L7b l\'écran « Messages automatiques » est réservé à l\'admin', function () {
    $prescriber = makeCommercial();

    $refused = $this->actingAs($prescriber)->get(EVO_MESSAGES_URL);
    expect($refused->getStatusCode())->not->toBe(200);

    app('auth')->forgetGuards();
    $this->get(EVO_MESSAGES_URL)->assertRedirect('/connexion');

    app('auth')->forgetGuards();
    $this->actingAs(makeAdmin())->get(EVO_MESSAGES_URL)->assertOk()->assertSee('Messages automatiques');
});

it('L7b un prescripteur ne peut pas enregistrer de messages ni de signature', function () {
    $prescriber = makeCommercial();
    $before = tablesState(EVO_SETTINGS_TABLES);

    evoSaveMessages($prescriber, ['denonciation_validee' => 'PIRATE'], 'SIGNATURE PIRATE');

    expect(tablesState(EVO_SETTINGS_TABLES))->toBe($before);
});

it('L7b les textes et la signature enregistrés sont réaffichés à l\'écran', function () {
    $admin = makeAdmin();

    evoSaveMessages($admin, ['denonciation_validee' => 'Bonjour {prescripteur}, dénonciation OK', 'option_refusee' => 'Option refusée pour {acquereur}'], "Jean DUPONT\nDirecteur commercial");

    app('auth')->forgetGuards();
    $text = pageText($this->actingAs($admin)->get(EVO_MESSAGES_URL)->assertOk());
    expect($text)->toContain('Bonjour {prescripteur}, dénonciation OK')
        ->toContain('Option refusée pour {acquereur}')
        ->toContain('Jean DUPONT')->toContain('Directeur commercial');
});

it('L7b la fenêtre de décision est pré-remplie avec le message type', function () {
    $w = workflowWorld();

    evoSaveMessages(makeAdmin(), [
        'denonciation_validee' => 'TEXTE-DEN-OK', 'denonciation_refusee' => 'TEXTE-DEN-KO',
        'option_validee' => 'TEXTE-OPT-OK', 'option_refusee' => 'TEXTE-OPT-KO',
        'reservation_validee' => 'TEXTE-RES-OK', 'reservation_refusee' => 'TEXTE-RES-KO',
    ], 'Signature');

    app('auth')->forgetGuards();
    foreach (['/ask_denonciations' => ['f_confirm_ask_denonciation_sms' => 'TEXTE-DEN-OK', 'f_refuser_ask_denonciation_sms' => 'TEXTE-DEN-KO'],
        '/ask_options' => ['f_confirm_ask_option_sms' => 'TEXTE-OPT-OK', 'f_refuser_ask_option_sms' => 'TEXTE-OPT-KO'],
        '/ask_reservations' => ['f_confirm_ask_resa_sms' => 'TEXTE-RES-OK', 'f_refuser_ask_resa_sms' => 'TEXTE-RES-KO']] as $url => $fields) {
        $content = (string) $this->actingAs($w->corim)->get($url)->assertOk()->getContent();
        foreach ($fields as $field => $text) {
            expect(preg_match('#<textarea[^>]*name="'.$field.'"[^>]*>([^<]*)</textarea>#', $content, $m))->toBe(1, "$url $field")
                ->and($m[1])->toContain($text);
        }
    }
});

it('L7b les variables {prescripteur} {programme} {lot} {acquereur} sont remplacées dans le mail envoyé', function () {
    $w = workflowWorld();

    jsonAction($w->corim, 'AcceptAddOption', [
        'f_confirm_ask_option_lot' => $w->lots['askopt']->id, 'f_confirm_ask_option_user' => $w->prescriber->id,
        'f_confirm_ask_option_sms' => 'Bonjour {prescripteur}, option sur {programme} lot {lot} pour {acquereur}.',
    ]);

    $text = decoded(strip_tags(evoMailHtml()));
    expect($text)->toContain('Bonjour Paul Prescripteur, option sur LES JARDINS TEST lot ASKOPT-1 pour Mme ACQUEREUR Anne.')
        ->not->toContain('{prescripteur}')->not->toContain('{acquereur}');
});

it('L7b les variables sont aussi remplacées pour une dénonciation', function () {
    $w = workflowWorld();

    jsonAction($w->corim, 'AcceptAddDenonciation', [
        'f_confirm_ask_denonciation_lot' => $w->askProspect, 'f_confirm_ask_denonciation_user' => $w->prescriber->id,
        'f_confirm_ask_denonciation_sms' => '{prescripteur} / {programme} / {acquereur}',
    ]);

    expect(decoded(strip_tags(evoMailHtml())))->toContain('Paul Prescripteur / LES JARDINS TEST / M PROSPECT Paul');
});

it('L7b le mail fixe police et taille en ligne, garde les retours à la ligne et échappe le message', function () {
    $w = workflowWorld();

    jsonAction($w->corim, 'AcceptAddOption', [
        'f_confirm_ask_option_lot' => $w->lots['askopt']->id, 'f_confirm_ask_option_user' => $w->prescriber->id,
        'f_confirm_ask_option_sms' => "Ligne un\nLigne <b>deux</b>",
    ]);

    $html = evoMailHtml();
    expect($html)->toMatch('/style="[^"]*font-family:[^"]*"/')
        ->toMatch('/style="[^"]*font-size:\s*\d+px[^"]*"/')
        ->toMatch('#Ligne un\s*<br\s*/?>\s*Ligne &lt;b&gt;deux&lt;/b&gt;#')
        ->not->toContain('<b>deux</b>');
});

it('L7b sans paramétrage, la signature par défaut est l\'actuelle (Le Cristal)', function () {
    $w = workflowWorld();

    jsonAction($w->corim, 'RejetAddOption', ['f_refuser_ask_option_lot' => $w->lots['askopt']->id, 'f_refuser_ask_option_user' => $w->prescriber->id, 'f_refuser_ask_option_sms' => 'Non']);

    $text = decoded(evoMailHtml());
    expect($text)->toContain('Immeuble "Le Cristal"')->toContain('1475 av.Albert Einstein')->toContain('04 99 58 86 86')->toContain('06 12 36 06 94');
});

it('L7b la signature enregistrée par l\'admin remplace la signature en dur, avec ses retours à la ligne', function () {
    $w = workflowWorld();
    evoSaveMessages(makeAdmin(), [], "Jean DUPONT\nDirecteur <commercial>");

    app('auth')->forgetGuards();
    jsonAction($w->corim, 'AcceptAddResa', ['f_confirm_ask_resa_lot' => $w->lots['askres']->id, 'f_confirm_ask_resa_user' => $w->prescriber->id, 'f_confirm_ask_resa_sms' => 'Ok']);

    $html = evoMailHtml();
    expect($html)->toMatch('#Jean DUPONT\s*<br\s*/?>\s*Directeur &lt;commercial&gt;#')
        ->not->toContain('Le Cristal')
        ->and(substr_count($html, 'Jean DUPONT'))->toBe(1);
});
