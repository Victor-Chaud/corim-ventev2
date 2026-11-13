<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('programmes'));

function evoCleanupCommand(): string
{
    $name = collect(array_keys(Artisan::all()))->first(fn ($n) => str_contains(strtolower($n), 'description'));
    expect($name)->not->toBeNull('commande artisan de nettoyage des descriptions absente');

    return (string) $name;
}

function evoDirtyDescriptions(): array
{
    $natura = makeProgram(['projet_titre' => 'NATURA EVO', 'projet_description' => 'Lien vers l'.str_repeat('\\', 7).'\'emplacement']);
    $hilaire = makeProgram(['projet_titre' => 'ST HILAIRE EVO', 'projet_description' => 'Premier paragraphe.'.str_repeat('\\', 8).'n'.str_repeat('\\', 8).'nSecond paragraphe.']);
    $clean = makeProgram(['projet_titre' => 'PROPRE EVO', 'projet_description' => "Déjà propre.\nAvec un retour."]);

    return [$natura, $hilaire, $clean];
}

function evoDescription(object $program): string
{
    return (string) DB::table('projet')->where('projet_id', $program->projet_id)->value('projet_description');
}

it('L7a la commande de nettoyage sans --apply ne modifie rien', function () {
    [$natura, $hilaire, $clean] = evoDirtyDescriptions();
    $before = DB::table('projet')->orderBy('projet_id')->get()->toJson();

    Artisan::call(evoCleanupCommand());

    expect(DB::table('projet')->orderBy('projet_id')->get()->toJson())->toBe($before);
});

it('L7a la commande avec --apply retire les \\ accumulés et transforme les \\n littéraux en retours à la ligne', function () {
    [$natura, $hilaire, $clean] = evoDirtyDescriptions();

    expect(Artisan::call(evoCleanupCommand(), ['--apply' => true]))->toBe(0);

    expect(evoDescription($natura))->toBe("Lien vers l'emplacement")
        ->and(evoDescription($hilaire))->toBe("Premier paragraphe.\n\nSecond paragraphe.")
        ->and(evoDescription($clean))->toBe("Déjà propre.\nAvec un retour.");

    Artisan::call(evoCleanupCommand(), ['--apply' => true]);
    expect(evoDescription($natura))->toBe("Lien vers l'emplacement")
        ->and(evoDescription($hilaire))->toBe("Premier paragraphe.\n\nSecond paragraphe.");
});

it('L7a la fiche programme affiche les retours à la ligne en <br> et les liens cliquables dans un nouvel onglet', function () {
    $program = makeProgram(['projet_description' => "Ligne une\nLigne deux\nPlan : https://www.google.com/maps/place/Montpellier et vidéo https://youtu.be/abc123"]);

    $content = (string) $this->actingAs(makeAdmin())->get("/programme/show/$program->projet_titre_key")->assertOk()->getContent();

    expect($content)->toMatch('#Ligne une\s*<br\s*/?>\s*Ligne deux#');
    foreach (['https://www.google.com/maps/place/Montpellier', 'https://youtu.be/abc123'] as $url) {
        expect(preg_match('#<a\s[^>]*href="'.preg_quote($url, '#').'"[^>]*>#', $content, $tag))->toBe(1, "lien $url absent");
        expect($tag[0])->toContain('target="_blank"')->toMatch('/rel="[^"]*noopener[^"]*"/');
    }
});

it('L7a la fiche programme échappe le HTML saisi dans la description', function () {
    $program = makeProgram(['projet_description' => "Intro <script>alert('xss')</script>\n<img src=x onerror=alert(2)> https://exemple.test/\"onmouseover=\"alert(3) javascript:alert(4)"]);

    $content = (string) $this->actingAs(makeAdmin())->get("/programme/show/$program->projet_titre_key")->assertOk()->getContent();

    expect($content)->not->toContain("<script>alert('xss')</script>")
        ->not->toContain('<img src=x')
        ->not->toContain('" onmouseover="')
        ->not->toContain('"onmouseover="alert(3)')
        ->not->toContain('href="javascript:alert')
        ->toContain('&lt;script&gt;');
});

it('L7a une description encodée à l\'ancienne s\'affiche sans double encodage', function () {
    $program = makeProgram(['projet_description' => 'Vue d&#039;angle &amp; jardin']);

    $response = $this->actingAs(makeAdmin())->get("/programme/show/$program->projet_titre_key")->assertOk();

    expect((string) $response->getContent())->not->toContain('&amp;#039;')->not->toContain('&amp;amp;')
        ->and(pageText($response))->toContain("Vue d'angle & jardin");
});
