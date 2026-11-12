<?php

use Illuminate\Support\Facades\Hash;

const UNKNOWN_EMAIL = '{"errors":1,"response":"Votre email n\'est pas reconnu."}';

function forgotten(string $email)
{
    return test()->post('/index.php/json/CheckIfExist', ['action' => 'forgottenpassword', 'email' => $email]);
}

function newAccount($session, int $id, string $password)
{
    return withSessionOf($session)->post('/index.php/json/NewAccountSetting', ['idClient' => $id, 'pwdClient' => $password]);
}

const RESET_SENT = "Si l'adresse %s correspond à un compte, un lien pour choisir votre nouveau mot de passe vient de lui être envoyé. Ce lien est valable une heure.";

function resetLink(): string
{
    $mails = sentMails();
    preg_match('#href="https?://[^/"]+(/connexion/reinitialisation/[^"]+)"#', (string) end($mails)->getHtmlBody(), $m);

    return html_entity_decode($m[1] ?? '');
}

it('C20 mot de passe oublié avec un e-mail connu : message, puis lien signé par mail, mot de passe changé seulement depuis ce lien', function () {
    $user = makeCommercial(['email' => 'connu@exemple.test']);

    $check = forgotten('connu@exemple.test');
    $check->assertOk();
    expect($check->json())->toBe(['errors' => 0, 'email' => 'connu@exemple.test', 'response' => 'Merci de confirmer votre demande de réinitialisation.']);

    $response = newAccount($check, $user->id, 'k3x9p2qa');
    expect($response->json())->toBe(['errors' => 0, 'response' => sprintf(RESET_SENT, 'connu@exemple.test')])
        ->and(DB::table('user')->where('id', $user->id)->value('password'))->toBe($user->password);

    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe('[Corim promotion] - Réinitialisation de votre mot de passe')
        ->and($mails[0]->getTo()[0]->getAddress())->toBe('connu@exemple.test')
        ->and((string) $mails[0]->getHtmlBody())->toContain($user->pseudo)->not->toContain('k3x9p2qa');

    $link = resetLink();
    app('auth')->forgetGuards();
    $this->get($link)->assertOk()->assertSee('Votre nouveau mot de passe');
    $this->post($link, ['password' => 'court', 'password_confirmation' => 'court'])->assertOk()->assertSee('Le mot de passe doit contenir au moins 8 caractères.');
    $this->post($link, ['password' => 'n0uveau-pass', 'password_confirmation' => 'autre-pass'])->assertOk()->assertSee('Les deux mots de passe ne sont pas identiques.');
    $this->post($link, ['password' => 'n0uveau-pass', 'password_confirmation' => 'n0uveau-pass'])->assertOk()->assertSee('Votre nouveau mot de passe est enregistré.');

    $hash = DB::table('user')->where('id', $user->id)->value('password');
    expect(Hash::check('n0uveau-pass', $hash))->toBeTrue();

    $this->post($link, ['password' => 'encore-autre', 'password_confirmation' => 'encore-autre'])->assertForbidden()->assertSee("Ce lien de réinitialisation n'est plus valide.", false);
    expect(DB::table('user')->where('id', $user->id)->value('password'))->toBe($hash);
});

it('C20b le lien de réinitialisation expire, refuse une signature modifiée et un seul mail part par période', function () {
    $user = makeCommercial(['email' => 'connu@exemple.test']);

    newAccount(forgotten('connu@exemple.test'), $user->id, 'x');
    newAccount(forgotten('connu@exemple.test'), $user->id, 'x');
    expect(sentMails())->toHaveCount(1);

    $link = resetLink();
    app('auth')->forgetGuards();
    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=00', $link))->assertForbidden();
    $this->get(preg_replace('#/reinitialisation/\d+/#', '/reinitialisation/'.($user->id + 1).'/', $link))->assertForbidden();

    $this->travel(61)->minutes();
    $this->get($link)->assertForbidden();
});

it('C21 mot de passe oublié : même réponse que l\'e-mail soit connu, inconnu ou en double, mail seulement pour un compte unique', function () {
    $known = makeCommercial(['email' => 'connu@exemple.test']);
    makeCommercial(['email' => 'double@exemple.test']);
    makeCommercial(['email' => 'double@exemple.test']);

    foreach (['connu@exemple.test', 'inconnu@exemple.test', 'double@exemple.test'] as $email) {
        $check = forgotten($email);
        expect($check->json())->toBe(['errors' => 0, 'email' => $email, 'response' => 'Merci de confirmer votre demande de réinitialisation.'])
            ->and(newAccount($check, 0, 'x')->json())->toBe(['errors' => 0, 'response' => sprintf(RESET_SENT, $email)]);
    }

    expect(sentMails())->toHaveCount(1)
        ->and(sentMails()[0]->getTo()[0]->getAddress())->toBe($known->email);
});

it('C22 la question secrète est conservée sans exposer le hash et limitée en débit', function () {
    $user = makeCommercial(['email' => 'q@exemple.test', 'question' => 'Couleur preferee ?', 'reponse' => 'bleu']);

    $question = $this->post('/index.php/json/CheckQuestion', ['action' => 'question', 'email' => 'q@exemple.test']);
    expect($question->json())->toBe(['errors' => 0, 'response' => 'Couleur preferee ?', 'email' => 'q@exemple.test'])
        ->and($this->post('/index.php/json/CheckQuestion', ['action' => 'question', 'email' => 'inconnu@exemple.test'])->getContent())->toBe(UNKNOWN_EMAIL);

    $wrong = $this->post('/index.php/json/CheckAnswer', ['your_response' => 'rouge', 'your_email' => 'q@exemple.test']);
    expect($wrong->json())->toBe(['errors' => 1, 'response' => 'La réponse à votre question secrète est incorrect.']);

    $right = $this->post('/index.php/json/CheckAnswer', ['your_response' => 'bleu', 'your_email' => 'q@exemple.test']);
    expect($right->json('errors'))->toBe(0)
        ->and($right->getContent())->not->toContain($user->password)
        ->and($right->getContent())->not->toContain(legacyTestHash('secret'));

    $before = DB::table('user')->where('id', $user->id)->value('password');
    expect(newAccount($right, $user->id, 'n0uveau1')->json('errors'))->toBe(0)
        ->and(DB::table('user')->where('id', $user->id)->value('password'))->toBe($before)
        ->and(sentMails())->toHaveCount(1)
        ->and(sentMails()[0]->getTo()[0]->getAddress())->toBe('q@exemple.test');

    app('auth')->forgetGuards();
    $limited = false;
    for ($i = 0; $i < 50 && ! $limited; $i++) {
        $attempt = $this->post('/index.php/json/CheckAnswer', ['your_response' => "faux$i", 'your_email' => 'q@exemple.test']);
        $limited = $attempt->getStatusCode() === 429;
    }
    expect($limited)->toBeTrue();
});

it('C23 NewAccountSetting n\'écrit jamais en base et n\'envoie le lien qu\'au compte validé en session ; GenerateNewAccountSetting reste sans effet', function () {
    $victim = makeCommercial(['email' => 'victime@exemple.test']);
    $attacker = makeCommercial(['email' => 'pirate@exemple.test']);
    $victimHash = $victim->password;

    $anonymous = $this->get('/connexion');
    expect(newAccount($anonymous, $victim->id, 'pirate01')->json('errors'))->toBe(1);

    $check = forgotten('pirate@exemple.test');
    newAccount($check, $victim->id, 'pirate01');
    expect(DB::table('user')->where('id', $victim->id)->value('password'))->toBe($victimHash)
        ->and(collect(sentMails())->map(fn ($m) => $m->getTo()[0]->getAddress())->all())->toBe(['pirate@exemple.test']);

    $generate = $this->get("/index.php/json/GenerateNewAccountSetting/$victim->id");
    $generate->assertOk();
    expect($generate->getContent())->toBe('')
        ->and(DB::table('user')->where('id', $victim->id)->value('password'))->toBe($victimHash)
        ->and($attacker->fresh()->password)->toBe($attacker->password);
});
