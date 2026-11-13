<?php

use Illuminate\Support\Facades\Storage;

it('C31 les plans et les URL de docs présents en prod restent servis aux mêmes chemins', function () {
    $plans = glob(OLD_MEDIAS.'/programmes/*/plans/plan-*.pdf');
    expect($plans)->not->toBeEmpty();

    foreach (array_slice($plans, 0, 50) as $file) {
        $path = substr($file, strlen(OLD_MEDIAS));
        $response = $this->get('/medias'.$path);
        expect($response->getStatusCode())->toBe(200, $path)
            ->and(responseBody($response))->toBe(file_get_contents($file));
    }

    $urls = DB::connection('legacy')->table('docs')->where('url', 'like', '%corim-vente.com/medias/%')->distinct()->limit(200)->pluck('url');
    foreach ($urls as $url) {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_file(OLD_MEDIAS.substr(rawurldecode($path), strlen('/medias')))) {
            expect($this->get($path)->getStatusCode())->toBe(200, $path);
        }
    }
});

it('C32 aucun fichier déposé dans medias/programmes n\'est exécuté comme PHP', function () {
    Storage::fake('programmes');
    $key = makeProgram()->projet_titre_key;
    $source = '<?php echo "EXEC"."UTE"; ?>';
    Storage::disk('programmes')->put("$key/plans/plan-OK.pdf", '%PDF-1.4');
    $ok = $this->get("/medias/programmes/$key/plans/plan-OK.pdf");
    expect($ok->getStatusCode())->toBe(200)->and(responseBody($ok))->toBe('%PDF-1.4');
    foreach (['documents/x.php', 'visuels/x.phtml', 'plans/plan-x.php', 'diapo/x.php5'] as $file) {
        Storage::disk('programmes')->put("$key/$file", $source);
        $response = $this->get("/medias/programmes/$key/$file");
        expect(responseBody($response))->not->toContain('EXECUTE');
        if ($response->getStatusCode() === 200) {
            expect($response->headers->get('Content-Type'))->not->toContain('text/html');
        }
    }
});
