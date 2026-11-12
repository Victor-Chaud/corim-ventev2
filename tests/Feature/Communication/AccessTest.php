<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

it('C1 les pages du module sans session redirigent vers /connexion', function () {
    foreach (['/messagerie', '/message/repondre/x', '/message/envoyer', '/emailing', '/emails', '/flash_info', '/contact', '/agenda'] as $url) {
        $response = $this->get($url);
        expect($response->isRedirect(url('/connexion')))->toBeTrue($url)
            ->and((string) $response->getContent())->not->toContain('navbar');
    }
});

it('C2 un prescripteur sur les pages admin est redirigé vers / sans écriture, sans e-mail et sans données', function () {
    $admin = corimAdmin();
    $presc = makeCommercial();
    $projet = makeProjet();
    DB::table('acces')->insert(['iduser' => $presc->id, 'idprojet' => $projet->projet_id]);
    makeClient(['email' => 'client-secret@exemple.test']);
    DB::table('docs')->insert(['email' => 'prospect-secret@exemple.test', 'url' => "https://corim-vente.com/medias/programmes/$projet->projet_titre_key/plans/plan-A1.pdf", 'time' => '1767225600']);
    $news = makeNews(['string1' => 'FLASH SECRET']);
    $before = communicationState();
    expectRouteExists($this->actingAs($admin)->get('/emailing'), 'emailing');

    $posts = [
        '/message/envoyer' => ['objet' => 'Pirate', 'message' => '<b>pirate</b>', 'method' => 'client', 'client_select' => 'cible@exemple.test'],
        '/emailing' => ['objet' => 'Pirate', 'message' => 'pirate', 'method' => 'keywords', 'keyword_select' => 'cible@exemple.test'],
        '/emails' => [],
        '/flash_info' => [],
        '/flash_info/add' => ['string1' => 'X', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19', 'url' => ''],
        "/flash_info/edit/$news->id" => ['string1' => 'X', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19', 'url' => '', 'id' => $news->id],
        "/flash_info/delete/$news->id" => [],
    ];
    foreach ($posts as $url => $data) {
        foreach (['get', 'post'] as $method) {
            app('auth')->forgetGuards();
            $response = $this->actingAs($presc)->$method($url, $method === 'post' ? $data : []);
            expect($response->isRedirect(url('/')))->toBeTrue("$method $url")
                ->and((string) $response->getContent())->not->toContain('@exemple.test')->not->toContain('FLASH SECRET')->not->toContain('navbar');
        }
    }

    expect(communicationState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C29 tous les formulaires du module rejettent un POST sans jeton CSRF (419)', function () {
    $admin = corimAdmin();
    $presc = makeCommercial();
    $message = makeMessage($admin->id, $presc->id);
    $news = makeNews();
    $before = communicationState();
    withoutTestingCsrfBypass();

    $forms = [
        [$presc, "/message/repondre/$message->cle", ['objet' => 'Re', 'message' => 'Réponse', 'destinataire' => $admin->id]],
        [$admin, '/message/envoyer', ['objet' => 'O', 'message' => 'M', 'method' => 'prescripteur', 'prescripteur_select' => 'all']],
        [$admin, '/emailing', ['objet' => 'O', 'message' => 'M', 'method' => 'keywords', 'keyword_select' => 'a@exemple.test']],
        [$admin, '/flash_info/add', ['string1' => 'X', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19', 'url' => '']],
        [$admin, "/flash_info/edit/$news->id", ['string1' => 'X', 'string2' => '', 'string3' => '', 'string4' => '', 'thumb' => '19', 'url' => '', 'id' => $news->id]],
        [$admin, "/flash_info/delete/$news->id", []],
        [$presc, '/contact', ['objet' => 'O', 'programme' => '0', 'message' => 'M']],
        [$presc, '/messagerie/supprimer', ['delete_message' => "$message->cle|"]],
        [$presc, '/messagerie/supprimer_tout', []],
    ];
    foreach ($forms as [$user, $url, $data]) {
        app('auth')->forgetGuards();
        expect($this->actingAs($user)->post($url, $data)->getStatusCode())->toBe(419, $url);
    }

    expect(communicationState())->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C30 en dev les e-mails partent sur le mailer log', function () {
    $env = file_get_contents(base_path('.env'));

    expect($env)->toMatch('/^MAIL_MAILER=log$/m')
        ->and(config('mail.mailers.log.transport'))->toBe('log');
});
