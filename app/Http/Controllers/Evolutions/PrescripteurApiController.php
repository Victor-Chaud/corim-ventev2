<?php

namespace App\Http\Controllers\Evolutions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgrammesLots\ProgrammeFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PrescripteurApiController extends Controller
{
    private const STATUTS = ['' => 'libre', 'opt' => 'option', 'res' => 'réservé', 'act' => 'vendu'];

    public const TABLES = ['appartements', 'projet', 'acces', 'acces_lot', 'prospects', 'd_prospects', 'r_prospects', 'user', 'privileges'];

    public static function flush(): void
    {
        Cache::forever('api-prescripteur:version', bin2hex(random_bytes(8)));
    }

    private static function cle(int $userId, string $type): string
    {
        return 'api-prescripteur:'.Cache::get('api-prescripteur:version', '0').":$userId:$type";
    }

    public function lots(Request $request): Response
    {
        return $this->repondre($request, function (int $userId) use ($request) {
            $rows = Cache::remember(self::cle($userId, 'lots'), 3600, fn () => $this->chargerLots($userId));

            return array_values(array_filter($rows, fn (array $lot) => $this->filtre($request, $lot)));
        });
    }

    public function denonciations(Request $request): Response
    {
        return $this->repondre($request, fn (int $userId) => Cache::remember(self::cle($userId, 'denonciations'), 3600, fn () => $this->chargerDenonciations($userId)));
    }

    private function repondre(Request $request, callable $donnees): Response
    {
        $cle = $request->bearerToken() ?? $request->query('cle');
        $row = is_string($cle) && $cle !== '' ? DB::table('api_keys')
            ->join('user', 'api_keys.user_id', '=', 'user.id')
            ->join('privileges', 'user.id', '=', 'privileges.iduser')
            ->where('api_keys.hash', hash('sha256', $cle))->whereNull('api_keys.revoked_at')
            ->where('privileges.acces', 0)->where('user.statut', 1)
            ->first(['api_keys.id', 'api_keys.user_id']) : null;
        if ($row === null) {
            return response('Clé API absente, inconnue ou révoquée.', 401, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        $limite = 'api-prescripteur:'.$row->id;
        if (RateLimiter::tooManyAttempts($limite, 60)) {
            return response('Trop de requêtes : 60 appels par heure au maximum.', 429, [
                'Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => (string) RateLimiter::availableIn($limite),
            ]);
        }
        RateLimiter::hit($limite, 3600);
        DB::table('api_keys')->where('id', $row->id)->update(['last_used_at' => now()]);

        $userId = (int) $row->user_id;
        $rows = $donnees($userId);

        return match ($this->format($request)) {
            'json' => response(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'private, no-cache',
            ]),
            'csv' => response($this->csv($rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-cache',
                'Content-Disposition' => 'inline; filename="'.($request->is('api/v1/lots') ? 'lots' : 'denonciations').'.csv"',
            ]),
            default => response()->view('evolutions.api_page', [
                'lots' => $request->is('api/v1/lots') ? $rows : null,
                'denonciations' => Cache::remember(self::cle($userId, 'denonciations'), 3600, fn () => $this->chargerDenonciations($userId)),
                'nom' => html_entity_decode((string) DB::table('user')->where('id', $userId)->value('name'), ENT_QUOTES),
            ], 200, ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']),
        };
    }

    private function format(Request $request): string
    {
        $format = $request->query('format');
        if (in_array($format, ['json', 'csv', 'html'], true)) {
            return $format;
        }

        return str_contains((string) $request->header('Accept'), 'application/json') ? 'json' : 'html';
    }

    private function chargerLots(int $userId): array
    {
        $texte = fn (mixed $value) => html_entity_decode((string) $value, ENT_QUOTES);
        $disk = ProgrammeFiles::disk();

        return DB::table('appartements')
            ->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->where('projet.archive', 0)
            ->whereIn('projet.projet_id', DB::table('acces')->where('iduser', $userId)->select('idprojet'))
            ->whereIn('appartements.id', DB::table('acces_lot')->where('iduser', $userId)->select('idlot'))
            ->orderBy('projet.projet_titre')->orderBy('appartements.id')
            ->get(['appartements.*', 'projet.projet_titre', 'projet.projet_titre_key', 'projet.projet_lieu', 'projet.livraison', 'projet.projet_description'])
            ->map(function (object $lot) use ($texte, $disk) {
                $key = (string) $lot->projet_titre_key;
                $plan = ProgrammeFiles::planName((string) $lot->numero);
                $thumb = ProgrammeFiles::safeName($key) ? ProgrammeFiles::thumb($key) : '';

                return [
                    'programme' => $texte($lot->projet_titre),
                    'ville' => $texte($lot->projet_lieu),
                    'livraison_programme' => $texte($lot->livraison),
                    'description_programme' => $texte($lot->projet_description),
                    'vignette' => $thumb !== '' ? url($thumb) : null,
                    'reference' => $texte($lot->numero),
                    'type' => $texte($lot->type),
                    'categorie' => $texte($lot->categorie),
                    'etage' => $texte($lot->etage),
                    'surface' => $texte($lot->superficie),
                    'prix' => $this->nombre((string) $lot->prix),
                    'prix_m2' => $this->nombre((string) $lot->prix_m2),
                    'rdc' => $texte($lot->RDC),
                    'jardin' => $texte($lot->jardin),
                    'terrasse' => $texte($lot->terrasse),
                    'stationnement' => $texte($lot->stationnement),
                    'cellier' => $texte($lot->cellier),
                    'exposition' => $texte($lot->exposition),
                    'accession' => $texte($lot->accession),
                    'livraison' => $texte($lot->date),
                    'statut' => self::STATUTS[(string) $lot->statut] ?? $texte($lot->statut),
                    'plan' => ProgrammeFiles::safeName($key) && ProgrammeFiles::safeName($plan) && $disk->fileExists($key.'/plans/'.$plan)
                        ? url(ProgrammeFiles::url($key, 'plans', $plan)) : null,
                ];
            })->all();
    }

    private function chargerDenonciations(int $userId): array
    {
        $rows = [];
        foreach (['d_prospects' => 'en attente', 'prospects' => 'validée', 'r_prospects' => 'refusée'] as $table => $statut) {
            $query = DB::table($table)->join('projet', "$table.idprojet", '=', 'projet.projet_id')
                ->where("$table.iduser", $userId)
                ->select("$table.nomprospect", "$table.cpprospect", "$table.villeprospect", "$table.date", 'projet.projet_titre', 'projet.projet_lieu')
                ->when($table === 'r_prospects', fn ($q) => $q->addSelect('r_prospects.date_refus'));
            foreach ($query->get() as $row) {
                $rows[] = [
                    'statut' => $statut,
                    'acquereur' => html_entity_decode((string) $row->nomprospect, ENT_QUOTES),
                    'code_postal' => html_entity_decode((string) $row->cpprospect, ENT_QUOTES),
                    'ville' => html_entity_decode((string) $row->villeprospect, ENT_QUOTES),
                    'programme' => html_entity_decode((string) $row->projet_titre, ENT_QUOTES),
                    'ville_programme' => html_entity_decode((string) $row->projet_lieu, ENT_QUOTES),
                    'date_demande' => (string) $row->date,
                    'date_refus' => isset($row->date_refus) ? (string) $row->date_refus : null,
                ];
            }
        }
        usort($rows, fn (array $a, array $b) => strcmp($b['date_demande'], $a['date_demande']));

        return $rows;
    }

    private function filtre(Request $request, array $lot): bool
    {
        $contient = function (string $param, string $valeur) use ($request): bool {
            $filtre = $request->query($param);
            if (! is_string($filtre) || $filtre === '') {
                return true;
            }

            return str_contains(Str::lower(Str::ascii($valeur)), Str::lower(Str::ascii($filtre)));
        };
        $min = $request->query('minBudget');
        $max = $request->query('maxBudget');

        return $contient('programme', $lot['programme']) && $contient('type', $lot['type']) && $contient('ville', $lot['ville'])
            && (! is_numeric($min) || ($lot['prix'] !== null && $lot['prix'] >= (float) $min))
            && (! is_numeric($max) || ($lot['prix'] !== null && $lot['prix'] <= (float) $max));
    }

    private function nombre(string $prix): int|float|null
    {
        $valeur = str_replace([' ', "\u{a0}", "\u{202f}", '€', ','], ['', '', '', '', '.'], html_entity_decode($prix, ENT_QUOTES));

        return is_numeric($valeur) ? $valeur + 0 : null;
    }

    public static function cellule(string $value): string
    {
        $debut = ltrim($value, " \u{a0}");
        if (is_numeric($debut) || preg_match('/^[=+\-@|\t\r]/', $debut) !== 1) {
            return $value;
        }

        return "'".$value;
    }

    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fwrite($handle, "\xEF\xBB\xBF");
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]), ';', '"', '');
        }
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => self::cellule((string) $value), $row), ';', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
