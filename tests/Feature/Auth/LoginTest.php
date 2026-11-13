<?php

use Illuminate\Support\Facades\Hash;

function login(string $username, string $password)
{
    return test()->post('/connexion', ['action' => 'connexion', 'identifiant' => $username, 'password' => $password]);
}

it('C1 la page de connexion s\'affiche avec le formulaire et la modale', function () {
    foreach (['/connexion', '/index.php/connexion'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('<title>Corim Promotion - Connexion à votre espace</title>', false)
            ->assertSee('id="login"', false)
            ->assertSee('name="identifiant"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="action" value="connexion"', false)
            ->assertSee('id="lost-password"', false)
            ->assertSee('id="logoff"', false);
    }
});

it('C2 la page de connexion redirige vers / si l\'utilisateur est connecté', function () {
    $this->actingAs(makeCommercial())->get('/connexion')->assertRedirect('/');
});

it('C3 des identifiants valides (hash hérité) connectent et régénèrent la session', function () {
    $user = makeCommercial(['pseudo' => 'jdupont']);
    $before = sessionCookieFrom($this->get('/connexion'));

    $response = $this->withCookie(config('session.cookie'), $before)->post('/connexion', ['action' => 'connexion', 'identifiant' => 'jdupont', 'password' => 'secret']);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    expect(sessionCookieFrom($response))->not->toBe('')->not->toBe($before);
});

it('C4 après connexion le hash est rehaché et une seconde connexion réussit', function () {
    $user = makeCommercial(['pseudo' => 'jdupont']);

    login('jdupont', 'secret')->assertRedirect('/');
    $hash = DB::table('user')->where('id', $user->id)->value('password');
    expect($hash)->not->toBe(legacyTestHash('secret'))
        ->and(Hash::check('secret', $hash))->toBeTrue()
        ->and(Hash::needsRehash($hash))->toBeFalse();

    app('auth')->forgetGuards();
    $this->get('/logout');
    app('auth')->forgetGuards();
    login('jdupont', 'secret')->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('C5 le hash hérité applique htmlspecialchars ENT_QUOTES avant sha256', function () {
    $password = 'a\'b"c&d<e>f';
    makeCommercial(['pseudo' => 'special', 'password' => hash('sha256', TEST_LEGACY_SALT.'a&#039;b&quot;c&amp;d&lt;e&gt;f')]);

    login('special', 'a\'b"c&d<e>f')->assertRedirect('/');
    $this->assertAuthenticated();
    expect(legacyTestHash($password))->toBe(hash('sha256', TEST_LEGACY_SALT.'a&#039;b&quot;c&amp;d&lt;e&gt;f'));
});

it('C6 un mauvais mot de passe affiche le message d\'échec', function () {
    makeCommercial(['pseudo' => 'jdupont']);

    $response = login('jdupont', 'mauvais');

    $response->assertOk();
    expect($response->getContent())->toMatch('#<div class="alert alert-warning">\s*<ul>\s*<li>Identifiant et/ou mot de passe non reconnu</li>#');
    $this->assertGuest();
});

it('C7 un compte inactif avec le bon mot de passe affiche le même message', function () {
    makeCommercial(['pseudo' => 'inactif', 'statut' => 0]);

    login('inactif', 'secret')->assertOk()->assertSee('<li>Identifiant et/ou mot de passe non reconnu</li>', false);
    $this->assertGuest();
});

it('C8 les champs vides affichent les messages de validation', function () {
    login('', 'secret')->assertSee("L'identifiant ne peut rester vide.", false);
    login('jdupont', '')->assertSee('Le mot de passe ne peut rester vide.');
    $this->assertGuest();
});

it('C9 le pseudo est comparé sans tenir compte de la casse', function () {
    $user = makeCommercial(['pseudo' => 'JDupont']);

    login('jdupont', 'secret')->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('C10 la 6e tentative échouée en une minute est refusée sans vérifier le mot de passe', function () {
    makeCommercial(['pseudo' => 'jdupont']);

    for ($i = 0; $i < 5; $i++) {
        login('jdupont', "mauvais$i")->assertOk()->assertSee('Identifiant et/ou mot de passe non reconnu');
    }
    $response = login('jdupont', 'secret');

    expect($response->getStatusCode() === 429 || ! $response->isRedirect('/'))->toBeTrue();
    $this->assertGuest();
});

it('C11 logout et connexion/deconnection invalident la session et redirigent vers /connexion', function () {
    foreach (['/logout', '/connexion/deconnection'] as $url) {
        $this->get($url)->assertRedirect('/connexion');

        $user = makeCommercial();
        $login = login($user->pseudo, 'secret');
        withSessionOf($login)->get($url)->assertRedirect('/connexion');
        withSessionOf($login)->get('/')->assertRedirect('/connexion');
    }
});

it('C26 un compte désactivé pendant sa session est déconnecté à la requête suivante', function () {
    $user = makeCommercial();
    $login = login($user->pseudo, 'secret');
    withSessionOf($login)->get('/')->assertOk();

    DB::table('user')->where('id', $user->id)->update(['statut' => 0]);

    withSessionOf($login)->get('/')->assertRedirect('/connexion');
});

it('C27 le cookie de session est Secure, HttpOnly, SameSite=Lax et json/* ignore callback', function () {
    $cookie = $this->get('/connexion')->getCookie(config('session.cookie'), false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and(strtolower((string) $cookie->getSameSite()))->toBe('lax');

    $admin = makeAdmin();
    $body = $this->actingAs($admin)->post('/index.php/json/GetUserPrivilege?callback=foo', ['key' => $admin->id])->getContent();
    expect($body)->not->toStartWith('foo');
});
