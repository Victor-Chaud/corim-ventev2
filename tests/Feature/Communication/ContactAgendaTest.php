<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C26 /contact : programmes accessibles, erreur objet, mp vers l\'admin 1 préfixé du programme, e-mail de notification exact', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $mine = makeProgram(['projet_titre' => 'MON PROGRAMME']);
    makeProgram(['projet_titre' => 'PAS LE MIEN']);
    DB::table('acces')->insert(['iduser' => $prescriber->id, 'idprojet' => $mine->projet_id]);

    $form = pageText($this->actingAs($prescriber)->get('/contact')->assertOk());
    expect($form)->toContain('<title>Corim promotion - Nous contacter</title>')
        ->toContain('<h1>Nous contacter</h1>')
        ->toContain('<option value="0">Choisir un programme</option>')
        ->toContain('<option value="MON PROGRAMME">MON PROGRAMME</option>')
        ->not->toContain('PAS LE MIEN');

    $error = $this->actingAs($prescriber)->post('/contact', ['objet' => '', 'programme' => '0', 'message' => 'Bonjour']);
    expect(pageText($error))->toContain('<div class="alert alert-warning" role="alert">Le champ objet est requis <br /></div>')
        ->and(DB::table('mp')->count())->toBe(0)
        ->and(sentMails())->toBeEmpty();

    $ok = $this->actingAs($prescriber)->post('/contact', ['objet' => 'Demande de plan', 'programme' => 'MON PROGRAMME', 'message' => 'Pouvez-vous m\'envoyer le plan ?']);
    expect(pageText($ok))->toContain('<div class="alert alert-success" role="alert">Votre message à bien été envoyé</div>');
    $mp = DB::table('mp')->first();
    expect((int) $mp->idemetteur)->toBe($prescriber->id)
        ->and((int) $mp->idreceveur)->toBe(1)
        ->and(decoded($mp->sujet))->toBe('Demande de plan')
        ->and(decoded($mp->message))->toBe('<b>Programme concerné :</b> MON PROGRAMME<br /><br />Pouvez-vous m\'envoyer le plan ?')
        ->and((int) $mp->lu)->toBe(0);
    expect(sentMails())->toHaveCount(1);
    expectLegacyMail(sentMails()[0], CORIM_TO, '[Corim promotion] - Vous avez reçu un nouveau message', contactMailBody());

    $this->actingAs($prescriber)->post('/contact', ['objet' => 'Sans programme', 'programme' => '0', 'message' => 'Texte seul']);
    expect(decoded(DB::table('mp')->orderByDesc('id')->value('message')))->toBe('Texte seul');
});

it('C28 /formulaire : 404, ou formulaire fonctionnel envoyant « demande infos : site CORIM » avec Reply-To validé', function () {
    $response = $this->get('/formulaire');

    if ($response->getStatusCode() === 404) {
        expect($this->post('/formulaire', ['nom' => 'X', 'prenom' => 'Y', 'email' => 'x@exemple.test', 'commentaire' => 'Z'])->getStatusCode())->toBe(404)
            ->and(sentMails())->toBeEmpty();

        return;
    }

    $response->assertOk();
    $this->post('/formulaire', ['nom' => 'DUPONT', 'prenom' => 'Claire', 'email' => "pirate@exemple.test\r\nBcc: victime@exemple.test", 'commentaire' => 'Infos']);
    expect(sentMails())->toBeEmpty();

    $this->post('/formulaire', ['nom' => 'DUPONT', 'prenom' => 'Claire', 'email' => 'claire@exemple.test', 'commentaire' => 'Je voudrais des infos']);
    expect(sentMails())->not->toBeEmpty();
    $mail = sentMails()[0];
    expect($mail->getSubject())->toBe('demande infos : site CORIM')
        ->and(array_map(fn ($a) => $a->getAddress(), $mail->getReplyTo()))->toBe(['claire@exemple.test'])
        ->and((string) $mail->getHtmlBody())->toContain('DUPONT Claire (claire@exemple.test)')->toContain('Je voudrais des infos');
});
