<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function addPrescriber(array $overrides = [])
{
    return jsonAction(User::find(corimAdmin()->id), 'AddPromoteur', prescriberPayload($overrides));
}

it('C11 AddPromoteur avec un champ obligatoire vide renvoie errors 0 et le message, rien en base', function () {
    corimAdmin();
    $before = tablesState(['user', 'privileges']);

    foreach (['f_add_promoteur_pseudo', 'f_add_promoteur_name', 'f_add_promoteur_pwd', 'f_add_promoteur_email'] as $field) {
        expect(addPrescriber([$field => ''])->getContent())->toBe('{"errors":0,"message":"Merci de renseigner tous les champs obligatoires"}', $field);
    }

    expect(tablesState(['user', 'privileges']))->toBe($before)
        ->and(sentMails())->toBeEmpty();
});

it('C12 AddPromoteur avec un email invalide renvoie errors 2, rien en base', function () {
    corimAdmin();
    $before = tablesState(['user', 'privileges']);

    expect(addPrescriber(['f_add_promoteur_email' => 'pas-un-email'])->getContent())->toBe('{"errors":2,"message":"Adresse email non valide"}')
        ->and(tablesState(['user', 'privileges']))->toBe($before);
});

it('C13 AddPromoteur valide crée user (statut 1, mot de passe haché) et privileges avec le type saisi', function () {
    corimAdmin();

    expect(addPrescriber(['f_add_promoteur_statut' => '1'])->getContent())->toBe('{"errors":1,"message":"Cr\\u00e9ation r\\u00e9ussie"}');

    $user = DB::table('user')->where('pseudo', 'nouveau')->first();
    expect($user)->not->toBeNull()
        ->and(decoded($user->name))->toBe('NOUVEAU Compte')
        ->and($user->email)->toBe('nouveau@exemple.test')
        ->and((int) $user->statut)->toBe(1)
        ->and($user->password)->not->toBe('MotDePasse1')
        ->and(json_encode((array) $user))->not->toContain('MotDePasse1')
        ->and((int) DB::table('privileges')->where('iduser', $user->id)->value('acces'))->toBe(1);

    addPrescriber(['f_add_promoteur_pseudo' => 'commercial2', 'f_add_promoteur_email' => 'c2@exemple.test', 'f_add_promoteur_statut' => '0']);
    $c2 = DB::table('user')->where('pseudo', 'commercial2')->value('id');
    expect((int) DB::table('privileges')->where('iduser', $c2)->value('acces'))->toBe(0);
});

it('C14 AddPromoteur valide envoie l\'e-mail d\'ouverture de compte du legacy', function () {
    corimAdmin();

    addPrescriber();

    $mails = sentMails();
    expect($mails)->toHaveCount(1);
    $html = (string) $mails[0]->getHtmlBody();
    expect($html)->not->toContain('MotDePasse1')->not->toContain('Mot de passe :');
    preg_match('#href="([^"]*/connexion/reinitialisation/[^"]+)"#', $html, $m);
    expect($m)->not->toBeEmpty();
    expectLegacyMail($mails[0], 'nouveau@exemple.test', '[Corim promotion] - Votre accès à la plateforme',
        accessMail('enregistré', 'nouveau', 'Pour choisir votre mot de passe, vous pouvez cliquer <a href="'.$m[1].'">ici</a>. Ce lien est valable une heure et ne sert qu\'une fois. Passé ce délai, utilisez « Mot de passe oublié ? » sur la page de connexion.<br /><br />', 'Mob : 06 12 36 06 94'));

    $user = DB::table('user')->where('pseudo', 'nouveau')->first();
    app('auth')->forgetGuards();
    $this->post(html_entity_decode((string) parse_url($m[1], PHP_URL_PATH).'?'.parse_url($m[1], PHP_URL_QUERY)), ['password' => 'choisi-par-lui', 'password_confirmation' => 'choisi-par-lui'])
        ->assertOk()->assertSee('Votre nouveau mot de passe est enregistré.');
    expect(password_verify('choisi-par-lui', (string) DB::table('user')->where('id', $user->id)->value('password')))->toBeTrue();
});

it('C15 AddPromoteur avec un type hors 0|1 est refusé', function () {
    corimAdmin();
    $before = tablesState(['user', 'privileges']);

    foreach (['2', '-1', 'admin', '1 OR 1=1'] as $type) {
        $response = addPrescriber(['f_add_promoteur_statut' => $type]);
        expect($response->getStatusCode())->toBeIn([200, 422], $type)
            ->and($response->getContent())->not->toContain('"errors":1');
    }

    expect(tablesState(['user', 'privileges']))->toBe($before);
});

it('C16 AddPromoteur avec un identifiant déjà existant se comporte comme le legacy (création acceptée)', function () {
    corimAdmin();
    makeCommercial(['pseudo' => 'nouveau']);

    expect(addPrescriber(['f_add_promoteur_email' => 'autre@exemple.test'])->getContent())->toBe('{"errors":1,"message":"Cr\\u00e9ation r\\u00e9ussie"}')
        ->and(DB::table('user')->where('pseudo', 'nouveau')->count())->toBe(2);
});

it('C17 le compte créé peut se connecter avec le mot de passe saisi', function () {
    corimAdmin();
    addPrescriber(['f_add_promoteur_pwd' => "Pa'ss&<word>"]);

    expect(canLogin('nouveau', "Pa'ss&<word>"))->toBeTrue()
        ->and(canLogin('nouveau', 'mauvais'))->toBeFalse();
});
