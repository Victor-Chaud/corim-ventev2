<?php

use Illuminate\Support\Facades\DB;

it('R1 l\'admin envoie au prescripteur un lien de réinitialisation qui lui permet de choisir son mot de passe', function () {
    $admin = makeAdmin();
    $prescriber = makeCommercial(['email' => 'presc@exemple.test']);

    $this->actingAs($admin)->get("/gestion_promoteurs/modifier/$prescriber->id")->assertOk()
        ->assertSee('action="/gestion_promoteurs/reinitialisation/'.$prescriber->id.'"', false)
        ->assertSee('Envoyer un lien de réinitialisation')
        ->assertSee('name="f_edit_promoteur_pwd"', false);

    $this->actingAs($admin)->post("/gestion_promoteurs/reinitialisation/$prescriber->id")
        ->assertRedirect("/gestion_promoteurs/modifier/$prescriber->id")
        ->assertSessionHas('reset_link_message', 'Un lien de réinitialisation a été envoyé à presc@exemple.test. Il est valable une heure.');

    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getTo()[0]->getAddress())->toBe('presc@exemple.test')
        ->and($mails[0]->getSubject())->toBe('[Corim promotion] - Réinitialisation de votre mot de passe')
        ->and(DB::table('user')->where('id', $prescriber->id)->value('password'))->toBe($prescriber->password);

    preg_match('#href="https?://[^/"]+(/connexion/reinitialisation/[^"]+)"#', (string) $mails[0]->getHtmlBody(), $m);
    app('auth')->forgetGuards();
    $this->post(html_entity_decode($m[1]), ['password' => 'n0uveau-pass', 'password_confirmation' => 'n0uveau-pass'])
        ->assertOk()->assertSee('Votre nouveau mot de passe est enregistré.');
    expect(password_verify('n0uveau-pass', (string) DB::table('user')->where('id', $prescriber->id)->value('password')))->toBeTrue();
});

it('R2 sans e-mail valide, aucun lien n\'est envoyé et l\'admin est prévenu', function () {
    $prescriber = makeCommercial(['email' => '']);

    $this->actingAs(makeAdmin())->post("/gestion_promoteurs/reinitialisation/$prescriber->id")
        ->assertRedirect("/gestion_promoteurs/modifier/$prescriber->id")
        ->assertSessionHas('reset_link_error', 'Aucun lien envoyé : ce compte n\'a pas d\'e-mail valide.');
    expect(sentMails())->toBeEmpty();
});

it('R3 seul un admin peut envoyer un lien, en POST uniquement, pour un compte existant', function () {
    $prescriber = makeCommercial(['email' => 'presc@exemple.test']);

    expect($this->post("/gestion_promoteurs/reinitialisation/$prescriber->id")->isRedirect(url('/connexion')))->toBeTrue();
    expect($this->actingAs(makeCommercial())->post("/gestion_promoteurs/reinitialisation/$prescriber->id")->isRedirect(url('/')))->toBeTrue();
    $this->actingAs(makeAdmin())->get("/gestion_promoteurs/reinitialisation/$prescriber->id")->assertStatus(405);
    expect($this->actingAs(makeAdmin())->post('/gestion_promoteurs/reinitialisation/999999')->isRedirect(url('/')))->toBeTrue();
    expect(sentMails())->toBeEmpty();
});

it('R4 l\'admin peut toujours saisir lui-même un nouveau mot de passe', function () {
    $prescriber = makeCommercial(['email' => 'presc@exemple.test']);

    $this->actingAs(makeAdmin())->post("/gestion_promoteurs/modifier/$prescriber->id", [
        'f_edit_promoteur_pseudo' => $prescriber->pseudo, 'f_edit_promoteur_name' => 'Nom', 'f_edit_promoteur_email' => 'presc@exemple.test',
        'f_edit_promoteur_pwd' => 'choisi-par-admin', 'f_edit_promoteur_statut' => '1',
    ])->assertOk()->assertSee('Le compte a été modifié');
    expect(password_verify('choisi-par-admin', (string) DB::table('user')->where('id', $prescriber->id)->value('password')))->toBeTrue();
});
