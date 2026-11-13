<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

const TEST_LEGACY_SALT = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';

const FIXTURES_DIR = __DIR__.'/Fixtures/legacy-api';

const M1_TEXT = '<b>Aucun résultat pour cette recherche.</b><br /><br />Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href="http://corim-promotion.com/contactez-nous/"><b>ici</b></a>';

const OLD_MEDIAS = __DIR__.'/../../OLD/medias';

foreach (glob(__DIR__.'/Helpers/*.php') as $helper) {
    require_once $helper;
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        config(['auth.legacy_salt' => TEST_LEGACY_SALT]);
    })
    ->in('Feature');

function loadLegacy(string ...$tables): void
{
    Schema::disableForeignKeyConstraints();
    foreach ($tables as $table) {
        $columns = array_values(array_intersect(Schema::getColumnListing($table), Schema::connection('legacy')->getColumnListing($table)));
        DB::connection('legacy')->table($table)->select($columns)->orderBy($columns[0])
            ->chunk(500, function ($rows) use ($table) {
                DB::table($table)->insert($rows->map(fn ($row) => (array) $row)->all());
            });
    }
    Schema::enableForeignKeyConstraints();
}

function legacyFixture(string $name): array
{
    foreach (file(FIXTURES_DIR.'/manifest.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$fixture, $status, $path] = explode("\t", $line);
        if ($fixture === $name) {
            preg_match('/^Content-Type: (.+?)\r?$/mi', file_get_contents(FIXTURES_DIR."/$name.headers"), $type);

            return [
                'status' => (int) $status,
                'path' => $path,
                'body' => file_get_contents(FIXTURES_DIR."/$name.body"),
                'type' => $type[1],
            ];
        }
    }
    throw new RuntimeException("Fixture inconnue : $name");
}

function legacyManifest(): array
{
    return array_map(
        fn ($line) => explode("\t", $line),
        file(FIXTURES_DIR.'/manifest.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
}

function sortTies(array $rows): array
{
    $out = [];
    $run = [];
    foreach ($rows as $row) {
        if ($run && $run[0]['prix'] !== $row['prix']) {
            usort($run, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
            array_push($out, ...$run);
            $run = [];
        }
        $run[] = $row;
    }
    usort($run, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
    array_push($out, ...$run);

    return $out;
}

function assertSameModuloTies(string $expectedJson, string $actualJson): void
{
    $expected = json_decode($expectedJson, true);
    $actual = json_decode($actualJson, true);
    expect($actual)->toBeArray();
    expect(array_column($actual, 'prix'))->toBe(array_column($expected, 'prix'))
        ->and(json_encode(sortTies($actual)))->toBe(json_encode(sortTies($expected)));
}

function jsonp(string $callback, mixed $value): string
{
    return $callback.'('.json_encode($value).');';
}

function jsonpUnwrap(string $body, string $callback): string
{
    expect($body)->toStartWith($callback.'(')->toEndWith(');');

    return substr($body, strlen($callback) + 1, -2);
}

function responseBody(TestResponse $response): string
{
    $base = $response->baseResponse;
    if ($base instanceof BinaryFileResponse) {
        return file_get_contents($base->getFile()->getPathname());
    }
    if ($base instanceof StreamedResponse) {
        return $response->streamedContent();
    }

    return (string) $base->getContent();
}

function legacyTestHash(string $password): string
{
    return hash('sha256', TEST_LEGACY_SALT.htmlspecialchars($password, ENT_QUOTES));
}

function makeUser(int $access, array $attributes = []): User
{
    static $n = 0;
    $n++;
    $id = DB::table('user')->insertGetId(array_merge([
        'pseudo' => "pseudo$n",
        'name' => "Utilisateur Test $n",
        'password' => legacyTestHash('secret'),
        'email' => "user$n@exemple.test",
        'question' => '',
        'reponse' => '',
        'statut' => 1,
    ], $attributes));
    DB::table('privileges')->insert(['iduser' => $id, 'acces' => $access]);

    return User::findOrFail($id);
}

function makeAdmin(array $attributes = []): User
{
    return makeUser(1, $attributes);
}

function makeCommercial(array $attributes = []): User
{
    return makeUser(0, $attributes);
}

function makeProgram(array $attributes = []): object
{
    static $n = 0;
    $n++;
    $title = $attributes['projet_titre'] ?? "PROGRAMME TEST $n";
    $id = DB::table('projet')->insertGetId(array_merge([
        'projet_titre' => $title,
        'projet_titre_key' => md5($title),
        'projet_lieu' => 'MONTPELLIER',
        'projet_image' => '',
        'projet_nb_appartements' => 0,
        'projet_description' => 'Description test',
        'archive' => 0,
        'dept' => '34',
        'livraison' => '2T2028',
    ], $attributes), 'projet_id');

    return DB::table('projet')->where('projet_id', $id)->first();
}

function makeLot(int $programId, array $attributes = []): object
{
    static $n = 0;
    $n++;
    $id = DB::table('appartements')->insertGetId(array_merge([
        'lot' => (string) $programId,
        'date' => '2T2028',
        'dept' => 34,
        'ville' => 'MONTPELLIER',
        'type' => 'T2',
        'categorie' => 'Appartement',
        'prix' => '185 000',
        'prix_m2' => '',
        'etage' => 'R1',
        'RDC' => '',
        'jardin' => '',
        'terrasse' => '10,5',
        'superficie' => '45,20',
        'stationnement' => '1',
        'exposition' => 'S',
        'description' => '',
        'statut' => '',
        'client' => '',
        'vendeur' => null,
        'numero' => "T$n",
        'cellier' => '',
        'accession' => 'Libre',
    ], $attributes));

    return DB::table('appartements')->where('id', $id)->first();
}

function sentMails(): array
{
    return Mail::mailer('array')->getSymfonyTransport()->messages()
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->all();
}

function sessionCookieFrom(TestResponse $response): string
{
    $cookie = $response->getCookie(config('session.cookie'));

    return (string) $cookie?->getValue();
}

function withSessionOf($response)
{
    app('auth')->forgetGuards();

    return test()->withCookie(config('session.cookie'), sessionCookieFrom($response));
}

function withoutTestingCsrfBypass(): void
{
    app()['env'] = 'local';
}
