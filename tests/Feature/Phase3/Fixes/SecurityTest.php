<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('S1 l\'API publique porte une CSP sandbox, sans nosniff, et refuse un url contenant < ou >', function () {
    foreach (['/index.php/search?action=email&email=x&url=%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E' => 'MyCallback3', '/firstsearch?action=email&email=ok%40exemple.test&url=a%3Eb' => 'MyFirstCallback3'] as $url => $callback) {
        $response = $this->get($url);
        $response->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")->assertHeaderMissing('X-Content-Type-Options');
        expect($response->getContent())->toBe($callback.'({"errors":"Votre adresse email n\'est pas valide","data":null});');
    }
    expect(DB::table('docs')->count())->toBe(0);

    $this->get('/index.php/firstsearch?action=nextjscorim')->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
});

it('S2 au-delà de 20 échecs de connexion depuis une même adresse, même un bon couple est refusé', function () {
    $user = makeCommercial();
    for ($i = 0; $i < 20; $i++) {
        $this->post('/connexion', ['action' => 'connexion', 'identifiant' => "inconnu$i", 'password' => 'faux']);
    }

    $this->post('/connexion', ['action' => 'connexion', 'identifiant' => $user->pseudo, 'password' => 'secret'])
        ->assertOk()->assertSee('Identifiant et/ou mot de passe non reconnu');
    $this->assertGuest();
});

it('S3 /medias sert les documents Office en pièce jointe avec leur type', function () {
    Storage::fake('programmes');
    Storage::disk('programmes')->put('cle/documents/notice.docx', 'x');

    $response = $this->get('/medias/programmes/cle/documents/notice.docx');
    $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('S5 un hash legacy enveloppé dans bcrypt reste accepté puis est réhaché', function () {
    $user = makeCommercial();
    $this->artisan('passwords:wrap-legacy', ['--apply' => true])->assertSuccessful();

    $wrapped = (string) DB::table('user')->where('id', $user->id)->value('password');
    expect($wrapped)->toStartWith(User::WRAPPED_PREFIX)
        ->and(Hash::check(legacyTestHash('secret'), substr($wrapped, strlen(User::WRAPPED_PREFIX))))->toBeTrue();

    $fresh = User::findOrFail($user->id);
    expect($fresh->checkPassword('faux'))->toBeFalse()
        ->and($fresh->checkPassword('secret'))->toBeTrue()
        ->and(Hash::check('secret', (string) DB::table('user')->where('id', $user->id)->value('password')))->toBeTrue();
});
