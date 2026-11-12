<?php

namespace App\Http\Controllers\ProgrammesLots;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgrammesLots\LotRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LotsController extends Controller
{
    public const TYPES = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'Studio', 'Local', 'Bureau', 'Garage'];

    public const CATEGORIES = ['Maison', 'Villa', 'Appartement', 'Local', 'Terrain', 'Immeuble', 'Stationnement'];

    public function gerer(string $key): View
    {
        $projet = ProgrammeFiles::projet($key);

        return view('programmes-lots.gerer_lots', [
            'title' => 'Corim promotion - Gérer les lots',
            'GetProjet' => $projet,
            'GetLots' => DB::table('appartements')->where('lot', (string) $projet->projet_id)->orderBy('id')->get(),
        ]);
    }

    public function modifier(LotRequest $request, string $key, int $idlot): View
    {
        $projet = ProgrammeFiles::projet($key);
        $lot = DB::table('appartements')->where('id', $idlot)->where('lot', (string) $projet->projet_id)->first();
        abort_if($lot === null, 404);
        $data = ['title' => 'Corim promotion - Modifier le lot'];

        if ($request->isMethod('post')) {
            $input = fn (string $field) => (string) $request->input($field);
            $required = ['name', 'ville', 'type', 'cat', 'prix', 'superficie', 'stationnement', 'exposition', 'numero'];
            if (collect($required)->every(fn ($field) => $input($field) !== '')) {
                $this->savePlan($request->file('plan'), $projet->projet_titre_key, $input('numero'));
                DB::table('appartements')->where('id', $lot->id)->update([
                    'date' => $input('date'),
                    'type' => $input('type'),
                    'categorie' => $input('cat'),
                    'prix' => $input('prix'),
                    'prix_m2' => $input('prix_m2'),
                    'etage' => $input('etage'),
                    'RDC' => $input('rdc'),
                    'jardin' => $input('jardin'),
                    'terrasse' => $input('terrasse'),
                    'superficie' => $input('superficie'),
                    'stationnement' => $input('stationnement'),
                    'exposition' => $input('exposition'),
                    'description' => $input('description'),
                    'numero' => $input('numero'),
                    'cellier' => $input('cellier'),
                    'accession' => $input('accession'),
                ]);
                $data['success'] = 'Le lot a bien été modifié.';
            } else {
                $data['success'] = 'Tous les champs sont requis';
            }
        }

        $data['GetProjet'] = $projet;
        $data['GetLot'] = DB::table('appartements')->where('id', $lot->id)->first();
        $data['types'] = self::TYPES;
        $data['categories'] = self::CATEGORIES;

        return view('programmes-lots.modifier_lot', $data);
    }

    public function ajouter(LotRequest $request, string $key): View
    {
        $projet = ProgrammeFiles::projet($key);
        $data = ['title' => 'Corim promotion - Ajouter un lot'];

        if ($request->isMethod('post')) {
            $input = fn (string $field) => (string) $request->input($field);
            $numero = $input('numero');
            if ($input('name') !== '' && $input('date') !== '' && $input('type') !== '' && $input('cat') !== '' && $numero !== '' && $numero !== '0') {
                $options = array_count_values(array_filter((array) $request->input('option'), 'is_string'));
                $this->savePlan($request->file('plan'), $projet->projet_titre_key, $numero);
                $this->insertLot($projet, [
                    'date' => $input('date'),
                    'type' => $input('type'),
                    'categorie' => $input('cat'),
                    'prix' => $input('prix'),
                    'prix_m2' => $input('prix_m2'),
                    'etage' => (string) ($options['etage'] ?? 0),
                    'RDC' => (string) ($options['RDC'] ?? 0),
                    'jardin' => (string) ($options['jardin'] ?? 0),
                    'terrasse' => (string) ($options['terrasse'] ?? 0),
                    'superficie' => $input('superficie'),
                    'stationnement' => $input('stationnement'),
                    'exposition' => $input('exposition'),
                    'description' => $input('description'),
                    'numero' => $numero,
                    'cellier' => $input('cellier'),
                    'accession' => $input('accession'),
                ]);
                $data['success'] = 'L\'appartement a bien été ajouté.';
            } else {
                $data['error'] = 'Tous les champs sont requis';
            }
        }

        $data['programme'] = $projet;

        return view('programmes-lots.ajouter_appartement', $data);
    }

    public function ajouterEnMasse(Request $request, string $key): View
    {
        $projet = ProgrammeFiles::projet($key);
        $csvPath = $key.'/import.csv';
        $disk = ProgrammeFiles::disk();
        $data = ['title' => 'Corim promotion - Ajouter lot en masse'];

        if ($request->isMethod('post')) {
            if ($request->has('csv_delete')) {
                $disk->delete($csvPath);
            } else {
                $file = $request->file('csv_upload');
                $csv = (string) $request->input('csv');
                if ($file instanceof UploadedFile) {
                    if (ProgrammeFiles::accepts($file, ['csv'])) {
                        $disk->putFileAs($key, $file, 'import.csv');
                        $csv = '1';
                    } else {
                        $data['error'] = 'Le format de fichier CSV n\'est pas reconnu';
                        $csv = '';
                    }
                }
                if ($csv === '1' && $disk->fileExists($csvPath)) {
                    [$added, $updated] = $this->importer($projet, $key, LotImport::rows((string) $disk->get($csvPath)));
                    if ($added + $updated === 0) {
                        $data['error'] = 'Problème lors de l\'enregistrement';
                    } else {
                        $data['success'] = match (true) {
                            $added === 1 => '1 lot ont été ajouté',
                            $added > 1 => $added.' lots ont été ajoutés',
                            default => 'Aucun lot ajouté',
                        }.($updated > 0 ? ', '.$updated.' '.($updated > 1 ? 'lots mis' : 'lot mis').' à jour' : '');
                    }
                } elseif (! isset($data['error'])) {
                    $data['error'] = 'Aucun fichier CSV n\'est disponible sur le serveur.';
                }
            }
        }

        $data['programme'] = $projet;
        $data['csvRows'] = null;
        if ($disk->fileExists($csvPath)) {
            $numeros = $this->numeros($projet);
            $data['csvRows'] = array_map(fn (array $row) => $row + [
                'action' => ($row['numero'] ?? '') !== '' && isset($numeros[self::encode($row['numero'])]) ? 'mise à jour' : 'ajout',
            ], LotImport::rows((string) $disk->get($csvPath)));
            $data['columns'] = LotImport::POSITIONS;
        }

        return view('programmes-lots.ajouter_appartements', $data);
    }

    public function modele(): Response
    {
        return response(LotImport::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-lots.csv"',
        ]);
    }

    private function importer(object $projet, string $key, array $rows): array
    {
        $numeros = $this->numeros($projet);
        $added = 0;
        $updated = 0;
        foreach ($rows as $values) {
            $plan = $values['plan'] ?? '';
            unset($values['plan']);
            $numero = $values['numero'] ?? '';
            if ($numero !== '' && isset($numeros[self::encode($numero)])) {
                DB::table('appartements')->where('lot', (string) $projet->projet_id)->where('numero', self::encode($numero))
                    ->update(array_map(fn (string $value) => self::encode($value), $values));
                $updated++;
            } else {
                $defaults = array_fill_keys(array_diff(LotImport::POSITIONS, ['plan']), '');
                if ($this->insertLot($projet, $values + $defaults)) {
                    $added++;
                    $numeros[self::encode($numero)] = true;
                }
            }
            $this->copyPlan($key, $plan, $numero);
        }

        return [$added, $updated];
    }

    private function copyPlan(string $key, string $plan, string $numero): void
    {
        $disk = ProgrammeFiles::disk();
        $target = ProgrammeFiles::planName(self::encode($numero));
        if ($plan === '' || $numero === '' || $plan === $target || ! ProgrammeFiles::safeName($plan) || ! ProgrammeFiles::safeName($target)) {
            return;
        }
        if ($disk->fileExists($key.'/plans/'.$plan)) {
            $disk->copy($key.'/plans/'.$plan, $key.'/plans/'.$target);
        }
    }

    private function numeros(object $projet): array
    {
        return array_fill_keys(DB::table('appartements')->where('lot', (string) $projet->projet_id)->pluck('numero')->map(fn ($n) => (string) $n)->all(), true);
    }

    private static function encode(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    public function uploadCsv(Request $request, string $key): Response
    {
        ProgrammeFiles::projet($key);
        $file = $request->file('Filedata');
        if (! $file instanceof UploadedFile || ! ProgrammeFiles::accepts($file, ['csv'])) {
            return response('Invalid file type.');
        }
        ProgrammeFiles::disk()->putFileAs($key, $file, 'import.csv');

        return response('1');
    }

    public function ajouterPlans(Request $request, string $key): View
    {
        $projet = ProgrammeFiles::projet($key);
        $data = ['title' => 'Corim promotion - Ajouter les plans'];

        if ($request->isMethod('post')) {
            $failed = [];
            foreach (array_filter((array) $request->file('plan_upload'), fn ($file) => $file instanceof UploadedFile) as $file) {
                $error = $this->storePlan($file, $projet);
                if ($error !== null) {
                    $failed[] = $error;
                }
            }
            if ($failed !== []) {
                $data['error'] = implode(' ', $failed);
            }
        }

        $disk = ProgrammeFiles::disk();
        $data['programme'] = $projet;
        $data['lots'] = DB::table('appartements')->where('lot', (string) $projet->projet_id)->get(['id', 'numero'])
            ->map(function ($lot) use ($disk, $key) {
                $lot->plan = ProgrammeFiles::planName((string) $lot->numero);
                $lot->exists = ProgrammeFiles::safeName($lot->plan) && $disk->fileExists($key.'/plans/'.$lot->plan);

                return $lot;
            });

        return view('programmes-lots.ajouter_plan', $data);
    }

    public function uploadPlan(Request $request, string $key): Response
    {
        $projet = ProgrammeFiles::projet($key);
        $file = $request->file('Filedata');
        if (! $file instanceof UploadedFile) {
            return response('Invalid file type.');
        }

        return response($this->storePlan($file, $projet) ?? '1');
    }

    private function storePlan(UploadedFile $file, object $projet): ?string
    {
        $name = $file->getClientOriginalName();
        if (! ProgrammeFiles::accepts($file, ['pdf'])) {
            return $name.' échoué.';
        }
        $key = (string) $projet->projet_titre_key;
        $disk = ProgrammeFiles::disk();
        $csv = $key.'/import.csv';
        $planMap = $disk->fileExists($csv) ? LotImport::planMap((string) $disk->get($csv)) : [];
        $lots = DB::table('appartements')->where('lot', (string) $projet->projet_id)->get(['id', 'numero']);
        $lot = LotImport::lotForPlan($name, $lots, $planMap);
        if ($lot === null) {
            return $name.' : aucun lot '.LotImport::planReference($name).' dans ce programme';
        }
        $target = ProgrammeFiles::planName((string) $lot->numero);

        return ProgrammeFiles::safeName($target) && $disk->putFileAs($key.'/plans', $file, $target) !== false ? null : $name.' échoué.';
    }

    private function savePlan(mixed $file, string $key, string $numero): void
    {
        $name = ProgrammeFiles::planName($numero);
        if ($file instanceof UploadedFile && ProgrammeFiles::safeName($name) && ProgrammeFiles::accepts($file, ['pdf'])) {
            ProgrammeFiles::disk()->putFileAs($key.'/plans', $file, $name);
        }
    }

    private function insertLot(object $projet, array $values): bool
    {
        return DB::table('appartements')->insert(array_map(fn (string $value) => htmlspecialchars($value, ENT_QUOTES), $values) + [
            'lot' => (string) $projet->projet_id,
            'dept' => (int) $projet->dept,
            'ville' => htmlspecialchars(html_entity_decode((string) $projet->projet_lieu, ENT_QUOTES), ENT_QUOTES),
            'statut' => '',
            'client' => '',
            'vendeur' => null,
        ]);
    }

    public static function csvRows(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = array_map('strval', $row);
            }
        }
        fclose($handle);

        return $rows;
    }
}
