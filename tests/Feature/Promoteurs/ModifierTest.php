<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function postModifier(object $admin, object $user, array $overrides = [])
{
    return test()->actingAs($admin)->post("/gestion_promoteurs/modifier/$user->id", editPayload($user, $overrides));
}

it('C22 le formulaire modifier est pré-rempli, mot de passe vide, aucun hash dans le HTML', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'jmartin', 'name' => 'MARTIN Jean', 'email' => 'jean@exemple.test', 'statut' => 0]);

    $response = $this->actingAs($admin)->get("/gestion_promoteurs/modifier/$user->id");

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim promotion - Gestion des promoteurs - Modifier le compte</title>')
        ->toContain('<h1>Modifier le prescripteur MARTIN Jean</h1>')
        ->toContain('value="jmartin"')->toContain('value="MARTIN Jean"')->toContain('value="jean@exemple.test"')
        ->toMatch('#<input type="password" name="f_edit_promoteur_pwd" id="f_edit_promoteur_pwd" value="" class="form-control" />#')
        ->toMatch('#<option value="0"\s+selected="selected"\s*>Désactiver</option>#')
        ->not->toContain('hidden_pwd')
        ->not->toContain($user->password);

    $unknown = pageText($this->actingAs($admin)->get('/gestion_promoteurs/modifier/999999')->assertOk());
    expect($unknown)->toMatch('#<h1>Modifier le prescripteur\s*</h1>#')->not->toContain('name="f_edit_promoteur_pseudo"');
    expect($this->actingAs($admin)->get('/gestion_promoteurs/modifier')->isRedirect(url('/')))->toBeTrue();
});

it('C23 identifiant, nom ou email vide → alerte, rien en base', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $before = tablesState(['user']);

    foreach (['f_edit_promoteur_pseudo', 'f_edit_promoteur_name', 'f_edit_promoteur_email'] as $field) {
        expect(pageText(postModifier($admin, $user, [$field => '', 'f_edit_promoteur_pwd' => 'nouveau'])))
            ->toContain('<div class="alert alert-warning" role="alert">Attention, les champs Identifiant, Nom, E-mail et  Statut sont obligatoires</div>');
    }

    expect(tablesState(['user']))->toBe($before)->and(sentMails())->toBeEmpty();
});

it('C24 email invalide → alerte, rien en base', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $before = tablesState(['user']);

    $text = pageText(postModifier($admin, $user, ['f_edit_promoteur_email' => 'pas-un-email']));

    expect(str_contains($text, "Attention, l'e-mail renseigné ne correspond pas à un e-mail valide") || str_contains($text, 'Attention, l\\e-mail renseigné ne correspond pas à un e-mail valide'))->toBeTrue()
        ->and(tablesState(['user']))->toBe($before);
});

it('C25 modifier sans mot de passe garde le hash, met à jour les autres champs et affiche « Le compte a été modifié »', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'ancien']);
    $hash = $user->password;

    $text = pageText(postModifier($admin, $user, ['f_edit_promoteur_pseudo' => 'renomme', 'f_edit_promoteur_name' => 'NOUVEAU Nom', 'f_edit_promoteur_email' => 'nouveau@exemple.test', 'f_edit_promoteur_statut' => '0']));

    $row = DB::table('user')->find($user->id);
    expect($text)->toContain('<div class="alert alert-success" role="alert">Le compte a été modifié</div>')
        ->and($row->password)->toBe($hash)
        ->and($row->pseudo)->toBe('renomme')
        ->and($row->name)->toBe('NOUVEAU Nom')
        ->and($row->email)->toBe('nouveau@exemple.test')
        ->and((int) $row->statut)->toBe(0);
});

it('C26 modifier avec mot de passe pose un nouveau hash et permet la connexion', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'changepwd']);

    postModifier($admin, $user, ['f_edit_promoteur_pwd' => 'Nouveau&Secret']);

    expect(DB::table('user')->where('id', $user->id)->value('password'))->not->toBe($user->password)->not->toContain('Nouveau')
        ->and(canLogin('changepwd', 'Nouveau&Secret'))->toBeTrue()
        ->and(canLogin('changepwd', 'secret'))->toBeFalse();
});

it('C27 modifier valide envoie l\'e-mail « Votre accès à la plateforme CORIM » du legacy', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'mailpwd', 'email' => 'old@exemple.test']);

    postModifier($admin, $user, ['f_edit_promoteur_email' => 'dest@exemple.test', 'f_edit_promoteur_pwd' => 'Choisi123']);

    $mails = sentMails();
    expect($mails)->toHaveCount(1);
    expectLegacyMail($mails[0], 'dest@exemple.test', '[Corim promotion] - Votre accès à la plateforme CORIM',
        accessMail('modifié', 'mailpwd', 'Choisi123', 'Fax : 04 67 60 40 77'));

    postModifier($admin, DB::table('user')->find($user->id), ['f_edit_promoteur_name' => 'Autre Nom']);
    expect(sentMails())->toHaveCount(2)
        ->and((string) sentMails()[1]->getHtmlBody())->toContain('Identifiant : mailpwd<br />Mot de passe : <br />');
});
