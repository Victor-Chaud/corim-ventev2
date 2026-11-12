<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C26 /contact : programmes accessibles, erreur objet, mp vers l\'admin 1 préfixé du programme, e-mail de notification exact', function () {
    $admin = corimAdmin();
    $presc = makeCommercial();
    $mine = makeProjet(['projet_titre' => 'MON PROGRAMME']);
    makeProjet(['projet_titre' => 'PAS LE MIEN']);
    DB::table('acces')->insert(['iduser' => $presc->id, 'idprojet' => $mine->projet_id]);

    $form = pageText($this->actingAs($presc)->get('/contact')->assertOk());
    expect($form)->toContain('<title>Corim promotion - Nous contacter</title>')
        ->toContain('<h1>Nous contacter</h1>')
        ->toContain('<option value="0">Choisir un programme</option>')
        ->toContain('<option value="MON PROGRAMME">MON PROGRAMME</option>')
        ->not->toContain('PAS LE MIEN');

    $error = $this->actingAs($presc)->post('/contact', ['objet' => '', 'programme' => '0', 'message' => 'Bonjour']);
    expect(pageText($error))->toContain('<div class="alert alert-warning" role="alert">Le champ objet est requis <br /></div>')
        ->and(DB::table('mp')->count())->toBe(0)
        ->and(sentMails())->toBeEmpty();

    $ok = $this->actingAs($presc)->post('/contact', ['objet' => 'Demande de plan', 'programme' => 'MON PROGRAMME', 'message' => 'Pouvez-vous m\'envoyer le plan ?']);
    expect(pageText($ok))->toContain('<div class="alert alert-success" role="alert">Votre message à bien été envoyé</div>');
    $mp = DB::table('mp')->first();
    expect((int) $mp->idemetteur)->toBe($presc->id)
        ->and((int) $mp->idreceveur)->toBe(1)
        ->and(decoded($mp->sujet))->toBe('Demande de plan')
        ->and(decoded($mp->message))->toBe('<b>Programme concerné :</b> MON PROGRAMME<br /><br />Pouvez-vous m\'envoyer le plan ?')
        ->and((int) $mp->lu)->toBe(0);
    expect(sentMails())->toHaveCount(1);
    expectLegacyMail(sentMails()[0], CORIM_TO, '[Corim promotion] - Vous avez reçu un nouveau message', contactMailBody());

    $this->actingAs($presc)->post('/contact', ['objet' => 'Sans programme', 'programme' => '0', 'message' => 'Texte seul']);
    expect(decoded(DB::table('mp')->orderByDesc('id')->value('message')))->toBe('Texte seul');
});

it('C27 /agenda et ses sous-URL redirigent vers / ou /connexion sans écriture ; json/DeleteEvent n\'existe plus', function () {
    $presc = makeCommercial();
    DB::table('agenda')->insert(['user_id' => $presc->id, 'agenda_titre' => 'RDV', 'agenda_description' => 'x', 'agenda_date' => '20260301', 'agenda_heure' => '10h00']);
    $before = tablesState(['agenda']);
    $urls = ['/agenda', '/agenda/event', '/agenda/show/2026-03', '/agenda/classique', '/agenda/calendar/2026-03'];

    foreach ($urls as $url) {
        foreach (['get', 'post'] as $method) {
            app('auth')->forgetGuards();
            $data = ['titre' => 'Nouveau', 'description' => 'x', 'date' => '2026-03-02', 'heure' => '10h00'];
            expect($this->actingAs($presc)->$method($url, $data)->isRedirect(url('/')))->toBeTrue("$method $url connecté");
            app('auth')->forgetGuards();
            expect($this->$method($url, $data)->isRedirect(url('/connexion')))->toBeTrue("$method $url invité");
        }
    }
    expect(tablesState(['agenda']))->toBe($before);

    $id = DB::table('agenda')->value('agenda_id');
    expect(jsonAction($presc, 'DeleteEvent', ['idevent' => $id])->getStatusCode())->toBe(404)
        ->and(jsonAction(null, 'DeleteEvent', ['idevent' => $id])->getStatusCode())->toBeIn([302, 401, 404])
        ->and(tablesState(['agenda']))->toBe($before);
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
