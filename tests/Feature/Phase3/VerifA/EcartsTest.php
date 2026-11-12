<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('programmes');
    Mail::fake();
});

it('A1 un document Word ou Excel accepté à l\'envoi est ensuite téléchargeable depuis la fiche programme', function (string $name, string $mime) {
    $projet = makeProjet();
    $key = $projet->projet_titre_key;

    $this->actingAs(makeAdmin())->post("/modifier_programme/show/$key", [
        'action' => 'uploader', 'type' => 'documents', 'filer' => [UploadedFile::fake()->create($name, 10, $mime)],
    ])->assertOk();
    Storage::disk('programmes')->assertExists("$key/documents/$name");

    app('auth')->forgetGuards();
    $this->get("/medias/programmes/$key/documents/$name")->assertOk();
})->with([
    ['notice.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['grille.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['notice.doc', 'application/msword'],
    ['grille.xls', 'application/vnd.ms-excel'],
]);

it('A2 les pages HTML portent un en-tête Content-Security-Policy', function () {
    $this->get('/connexion')->assertHeader('Content-Security-Policy');
    $this->actingAs(makeAdmin())->get('/gestion_programmes')->assertHeader('Content-Security-Policy');
});

it('A3 l\'API publique nextjscorim sert une requête répétée depuis le cache sans relancer la requête SQL', function () {
    $projet = makeProjet(['projet_titre' => 'COCOON']);
    makeLot($projet->projet_id);
    $url = '/index.php/firstsearch?action=nextjscorim&residence=cocoon';
    $first = $this->get($url)->getContent();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $second = $this->get($url)->getContent();
    $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'appartements'))->count();
    DB::disableQueryLog();

    expect($second)->toBe($first)->and($queries)->toBe(0);
});

it('A4 json/CheckIfExist n\'expose jamais l\'identifiant du compte', function () {
    makeCommercial(['email' => 'connu@exemple.test']);

    $response = $this->post('/index.php/json/CheckIfExist', ['action' => 'forgottenpassword', 'email' => 'connu@exemple.test']);

    expect($response->json('errors'))->toBe(0)
        ->and($response->json())->not->toHaveKey('id');
});

it('A5 un anonyme qui connaît seulement l\'e-mail ne change pas le mot de passe du compte sans action de son titulaire', function () {
    $victim = makeCommercial(['email' => 'victime@exemple.test']);

    $check = $this->post('/index.php/json/CheckIfExist', ['action' => 'forgottenpassword', 'email' => 'victime@exemple.test']);
    withSessionOf($check)->post('/index.php/json/NewAccountSetting', ['idClient' => $victim->id, 'pwdClient' => 'x']);

    expect(User::findOrFail($victim->id)->checkPassword('secret'))->toBeTrue();
});

it('A6 public/.htaccess ne contient aucun commentaire', function () {
    $lines = file(base_path('public/.htaccess'), FILE_IGNORE_NEW_LINES);

    expect(array_values(array_filter($lines, fn ($line) => str_starts_with(ltrim($line), '#'))))->toBe([]);
});

it('A7 un chemin /medias contenant ../ encodé répond 404 et non 500', function () {
    $key = makeProjet()->projet_titre_key;

    foreach (["$key/plans/..%2F..%2F..%2F..%2F.env", '..%2F..%2F..%2F.env'] as $path) {
        expect($this->get("/medias/programmes/$path")->getStatusCode())->toBe(404, $path);
    }
});
