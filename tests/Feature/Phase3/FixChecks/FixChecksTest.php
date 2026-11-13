<?php

use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function fixCheckApi(string $path)
{
    return test()->get('/index.php/'.$path);
}

function fixCheckSnapshot($response): array
{
    return [$response->getStatusCode(), $response->headers->get('Content-Type'), $response->getContent()];
}

it('VC1 chaque fixture donne la même réponse à froid et depuis le cache, avec CSP sandbox et sans nosniff', function () {
    loadLegacy('projet', 'appartements', 'news');
    $strict = ['nextjscorim_city', 'first_default_empty', 'first_default_prog', 'search_allnews'];

    foreach (legacyManifest() as [$name, $status, $path]) {
        $cold = fixCheckApi($path);
        $warm = fixCheckApi($path);
        $cold->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")->assertHeaderMissing('X-Content-Type-Options');
        $warm->assertHeaderMissing('X-Content-Type-Options');
        expect(fixCheckSnapshot($warm))->toBe(fixCheckSnapshot($cold));
        if ($status === '200') {
            expect($cold->headers->get('Content-Type'))->toBe(legacyFixture($name)['type']);
        }
        if (in_array($name, $strict, true)) {
            expect($warm->getContent())->toBe(legacyFixture($name)['body']);
        }
    }
});

it('VC2 le cache ne mélange pas les réponses de paramètres ou de points d\'entrée différents', function () {
    loadLegacy('projet', 'appartements', 'news');
    $paths = [
        'firstsearch?action=nextjscorim&residence=cocoon',
        'firstsearch?action=nextjscorim',
        'firstsearch?action=nextjscorim&type=T2',
        'firstsearch?action=nextjscorim&city=AGDE',
        'firstsearch?action=light&programmes=41',
        'search?action=light&programmes=41',
        'search/index?action=light&programmes=41',
        'firstsearch?action=withref&programme=41&ref=B31',
        'search?action=withref&programmes=41&ref=B31',
    ];

    $fresh = [];
    foreach ($paths as $path) {
        Cache::flush();
        $fresh[$path] = fixCheckSnapshot(fixCheckApi($path));
    }

    Cache::flush();
    foreach ([...$paths, ...array_reverse($paths)] as $path) {
        expect(fixCheckSnapshot(fixCheckApi($path)))->toBe($fresh[$path]);
    }
    expect(count(array_unique(array_map(fn ($s) => $s[2], $fresh))))->toBeGreaterThan(6);
});

it('VC3 une réponse en erreur n\'est pas mise en cache à la place de la réponse valide', function () {
    loadLegacy('projet', 'appartements');
    $fail = true;
    DB::listen(function ($query) use (&$fail) {
        if ($fail && str_contains($query->sql, 'appartements')) {
            throw new RuntimeException('panne simulée');
        }
    });

    $fixture = legacyFixture('nextjscorim_city');
    expect(fixCheckApi($fixture['path'])->getStatusCode())->toBe(500);

    $fail = false;
    $response = fixCheckApi($fixture['path']);
    $response->assertOk();
    expect($response->getContent())->toBe($fixture['body']);
});

it('VC4 action=email n\'est jamais servie depuis le cache et n\'écrit pas dans docs quand l\'url est refusée', function () {
    foreach (['search', 'firstsearch', 'search/index'] as $endpoint) {
        fixCheckApi($endpoint.'?action=email&email=a%40exemple.test&url=%3Cb%3E')->assertOk();
        fixCheckApi($endpoint.'?action=email&email=a%40exemple.test&url[]=%3Cscript%3E')->assertOk();
    }
    expect(DB::table('docs')->count())->toBe(0);

    fixCheckApi('search?action=email&email=a%40exemple.test&url=plan.pdf');
    fixCheckApi('search?action=email&email=a%40exemple.test&url=plan.pdf');
    expect(DB::table('docs')->count())->toBe(2);

    $refused = fixCheckApi('search?action=email&email=a%40exemple.test&url=%3Cb%3E');
    expect($refused->getContent())->toBe('MyCallback3({"errors":"Votre adresse email n\'est pas valide","data":null});')
        ->and(DB::table('docs')->count())->toBe(2);
});

it('VC5 la limite d\'écriture dans docs bloque l\'insertion au-delà de 10 par minute', function () {
    for ($i = 0; $i < 12; $i++) {
        fixCheckApi("search?action=email&email=a%40exemple.test&url=plan$i.pdf")->assertOk();
    }

    expect(DB::table('docs')->count())->toBe(10);
});

function fixCheckForgotten(string $email)
{
    return test()->post('/index.php/json/CheckIfExist', ['action' => 'forgottenpassword', 'email' => $email]);
}

function fixCheckNewAccount($session)
{
    return withSessionOf($session)->post('/index.php/json/NewAccountSetting', ['idClient' => 0, 'pwdClient' => 'x']);
}

function fixCheckLink(): string
{
    $mails = sentMails();
    preg_match('#href="https?://[^/"]+(/connexion/reinitialisation/[^"]+)"#', (string) end($mails)->getHtmlBody(), $m);

    return html_entity_decode($m[1] ?? '');
}

it('VC6 aucune énumération : un e-mail connu saisi avec une autre casse reçoit la même réponse qu\'un e-mail inconnu', function () {
    makeCommercial(['email' => 'connu@exemple.test']);

    $known = fixCheckNewAccount(fixCheckForgotten('CONNU@Exemple.test'));
    $unknown = fixCheckNewAccount(fixCheckForgotten('INCONNU@Exemple.test'));

    expect($known->getStatusCode())->toBe($unknown->getStatusCode())
        ->and(str_replace('CONNU', 'INCONNU', $known->getContent()))->toBe($unknown->getContent());
});

it('VC7 un lien correctement signé mais portant l\'empreinte d\'un autre compte est refusé', function () {
    $alice = makeCommercial(['email' => 'alice@exemple.test']);
    $bob = makeCommercial(['email' => 'bob@exemple.test']);

    fixCheckNewAccount(fixCheckForgotten('alice@exemple.test'));
    preg_match('#/reinitialisation/\d+/([0-9a-f]+)#', fixCheckLink(), $m);

    $forged = URL::temporarySignedRoute('password.reset-link', now()->addHour(), ['id' => $bob->id, 'empreinte' => $m[1]], false);
    app('auth')->forgetGuards();
    $this->get($forged)->assertForbidden();
    $this->post($forged, ['password' => 'pirate-123', 'password_confirmation' => 'pirate-123'])->assertForbidden();

    expect(DB::table('user')->where('id', $bob->id)->value('password'))->toBe($bob->password)
        ->and(DB::table('user')->where('id', $alice->id)->value('password'))->toBe($alice->password);
});

it('VC8 le mot de passe choisi n\'apparaît dans aucun log, même avec le mailer log', function () {
    config(['mail.default' => 'log']);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.json_encode($e->context);
    });
    $user = makeCommercial(['email' => 'connu@exemple.test']);

    fixCheckNewAccount(fixCheckForgotten('connu@exemple.test'));
    $log = implode("\n", $logged);
    preg_match('#(/connexion/reinitialisation/[^"\s]+)#', html_entity_decode($log), $m);
    expect($m)->not->toBeEmpty();

    app('auth')->forgetGuards();
    $link = $m[1];
    $this->post($link, ['password' => 'Choisi-1234', 'password_confirmation' => 'Autre-1234'])->assertOk();
    $this->post($link, ['password' => 'Choisi-1234', 'password_confirmation' => 'Choisi-1234'])->assertOk()->assertSee('Votre nouveau mot de passe est enregistré.');

    expect(implode("\n", $logged))->not->toContain('Choisi-1234')
        ->and(DB::table('user')->where('id', $user->id)->value('password'))->not->toBe($user->password);
});

it('VC9 la page de réinitialisation est limitée en fréquence', function () {
    $user = makeCommercial(['email' => 'connu@exemple.test']);
    fixCheckNewAccount(fixCheckForgotten('connu@exemple.test'));
    $link = fixCheckLink();
    app('auth')->forgetGuards();

    $codes = [];
    for ($i = 0; $i < 25; $i++) {
        $codes[] = $this->post($link, ['password' => 'a', 'password_confirmation' => 'b'])->getStatusCode();
    }

    expect($codes)->toContain(429);
    expect(DB::table('user')->where('id', $user->id)->value('password'))->toBe($user->password);
});

it('VC10 un hash legacy enveloppé ne devient pas lui-même un mot de passe valide', function () {
    $user = makeCommercial();
    $this->artisan('passwords:wrap-legacy', ['--apply' => true])->assertSuccessful();

    expect(User::findOrFail($user->id)->checkPassword(legacyTestHash('secret')))->toBeFalse();
});

it('VC11a /medias refuse la traversée de répertoire', function () {
    Storage::fake('programmes');
    Storage::disk('programmes')->put('cle/plans/plan-A003..pdf', '%PDF');

    $this->get('/medias/programmes/cle/plans/../plans/plan-A003..pdf')->assertNotFound();
    $this->get('/medias/programmes/cle/%2e%2e/%2e%2e/.env')->assertNotFound();
    $this->get('/medias/programmes/..%2f..%2f.env')->assertNotFound();
});

it('VC11b /medias sert les fichiers réels dont le nom contient deux points consécutifs', function () {
    Storage::fake('programmes');
    Storage::disk('programmes')->put('cle/plans/plan-A003..pdf', '%PDF');
    Storage::disk('programmes')->put('cle/documents/Notice 08.08.2019.D..pdf', '%PDF');

    $this->get('/medias/programmes/cle/plans/plan-A003..pdf')->assertOk();
    $this->get('/medias/programmes/cle/documents/Notice%2008.08.2019.D..pdf')->assertOk();
});

it('VC12 chaque gestionnaire on* et chaque URL javascript: des vues est autorisé par la CSP des pages sans CKEditor', function () {
    $sources = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with((string) $file, '.blade.php')) {
            continue;
        }
        $html = (string) file_get_contents((string) $file);
        preg_match_all('/\son[a-z]+\s*=\s*"([^"]*)"/i', $html, $handlers);
        preg_match_all('/href="(javascript:[^"]*)"/i', $html, $urls);
        foreach ([...$handlers[1], ...$urls[1]] as $source) {
            $sources[$source][] = str_replace(resource_path('views').'/', '', (string) $file);
        }
    }

    preg_match('/script-src [^;]+/', (string) $this->actingAs(makeAdmin())->get('/')->headers->get('Content-Security-Policy'), $directive);
    $csp = $directive[0];
    expect($csp)->not->toContain("'unsafe-inline'");

    $missing = [];
    foreach ($sources as $source => $files) {
        $hash = "'sha256-".base64_encode(hash('sha256', $source, true))."'";
        if (! str_contains($csp, $hash)) {
            $missing[] = $source.' ('.implode(', ', array_unique($files)).')';
        }
    }

    expect($missing)->toBe([]);
});

it('VC13 chaque script inline des vues porte le nonce, sauf sur les pages CKEditor', function () {
    $ckeditor = ['communication/flash_info_add.blade.php', 'communication/flash_info_edit.blade.php'];
    $missing = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with((string) $file, '.blade.php')) {
            continue;
        }
        $name = str_replace(resource_path('views').'/', '', (string) $file);
        preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', (string) file_get_contents((string) $file), $tags);
        foreach ($tags[0] as $tag) {
            if (! str_contains($tag, 'Vite::cspNonce()') && ! in_array($name, $ckeditor, true)) {
                $missing[] = $name.' '.$tag;
            }
        }
    }

    expect($missing)->toBe([]);

    $admin = makeAdmin();
    foreach (['/flash_info/add'] as $page) {
        expect((string) $this->actingAs($admin)->get($page)->headers->get('Content-Security-Policy'))->toContain("'unsafe-inline'");
    }
});
