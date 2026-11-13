<?php

namespace App\Http\Controllers\ProgramsLots;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramsLots\LotRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LotsController extends Controller
{
    public const TYPES = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'Studio', 'Local', 'Bureau', 'Garage'];

    public const CATEGORIES = ['Maison', 'Villa', 'Appartement', 'Local', 'Terrain', 'Immeuble', 'Stationnement'];

    public function manage(string $key): View
    {
        $program = ProgramFiles::program($key);

        return view('programs-lots.manage_lots', [
            'title' => 'Corim promotion - Gérer les lots',
            'GetProgram' => $program,
            'GetLots' => DB::table('appartements')->where('lot', (string) $program->projet_id)->orderBy('id')->get(),
        ]);
    }

    public function edit(LotRequest $request, string $key, int $idlot): View
    {
        $program = ProgramFiles::program($key);
        $lot = DB::table('appartements')->where('id', $idlot)->where('lot', (string) $program->projet_id)->first();
        abort_if($lot === null, 404);
        $data = ['title' => 'Corim promotion - Modifier le lot'];

        if ($request->isMethod('post')) {
            $input = fn (string $field) => (string) $request->input($field);
            $required = ['name', 'ville', 'type', 'cat', 'prix', 'superficie', 'stationnement', 'exposition', 'numero'];
            if (collect($required)->every(fn ($field) => $input($field) !== '')) {
                $this->savePlan($request->file('plan'), $program->projet_titre_key, $input('numero'));
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

        $data['GetProgram'] = $program;
        $data['GetLot'] = DB::table('appartements')->where('id', $lot->id)->first();
        $data['types'] = self::TYPES;
        $data['categories'] = self::CATEGORIES;

        return view('programs-lots.edit_lot', $data);
    }

    public function add(LotRequest $request, string $key): View
    {
        $program = ProgramFiles::program($key);
        $data = ['title' => 'Corim promotion - Ajouter un lot'];

        if ($request->isMethod('post')) {
            $input = fn (string $field) => (string) $request->input($field);
            $number = $input('numero');
            if ($input('name') !== '' && $input('date') !== '' && $input('type') !== '' && $input('cat') !== '' && $number !== '' && $number !== '0') {
                $options = array_count_values(array_filter((array) $request->input('option'), 'is_string'));
                $this->savePlan($request->file('plan'), $program->projet_titre_key, $number);
                $this->insertLot($program, [
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
                    'numero' => $number,
                    'cellier' => $input('cellier'),
                    'accession' => $input('accession'),
                ]);
                $data['success'] = 'L\'appartement a bien été ajouté.';
            } else {
                $data['error'] = 'Tous les champs sont requis';
            }
        }

        $data['program'] = $program;

        return view('programs-lots.add_apartment', $data);
    }

    public function addBulk(Request $request, string $key): View
    {
        $program = ProgramFiles::program($key);
        $csvPath = $key.'/import.csv';
        $disk = ProgramFiles::disk();
        $data = ['title' => 'Corim promotion - Ajouter lot en masse'];

        if ($request->isMethod('post')) {
            if ($request->has('csv_delete')) {
                $disk->delete($csvPath);
            } else {
                $file = $request->file('csv_upload');
                $csv = (string) $request->input('csv');
                if ($file instanceof UploadedFile) {
                    if (ProgramFiles::accepts($file, ['csv'])) {
                        $disk->putFileAs($key, $file, 'import.csv');
                        $csv = '1';
                    } else {
                        $data['error'] = 'Le format de fichier CSV n\'est pas reconnu';
                        $csv = '';
                    }
                }
                if ($csv === '1' && $disk->fileExists($csvPath)) {
                    [$added, $updated] = $this->import($program, $key, LotImport::rows((string) $disk->get($csvPath)));
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

        $data['program'] = $program;
        $data['csvRows'] = null;
        if ($disk->fileExists($csvPath)) {
            $numbers = $this->numbers($program);
            $data['csvRows'] = array_map(fn (array $row) => $row + [
                'action' => ($row['numero'] ?? '') !== '' && isset($numbers[self::encode($row['numero'])]) ? 'mise à jour' : 'ajout',
            ], LotImport::rows((string) $disk->get($csvPath)));
            $data['columns'] = LotImport::POSITIONS;
        }

        return view('programs-lots.add_apartments', $data);
    }

    public function template(): Response
    {
        return response(LotImport::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-lots.csv"',
        ]);
    }

    private function import(object $program, string $key, array $rows): array
    {
        $numbers = $this->numbers($program);
        $added = 0;
        $updated = 0;
        foreach ($rows as $values) {
            $plan = $values['plan'] ?? '';
            unset($values['plan']);
            $number = $values['numero'] ?? '';
            if ($number !== '' && isset($numbers[self::encode($number)])) {
                DB::table('appartements')->where('lot', (string) $program->projet_id)->where('numero', self::encode($number))
                    ->update(array_map(fn (string $value) => self::encode($value), $values));
                $updated++;
            } else {
                $defaults = array_fill_keys(array_diff(LotImport::POSITIONS, ['plan']), '');
                if ($this->insertLot($program, $values + $defaults)) {
                    $added++;
                    $numbers[self::encode($number)] = true;
                }
            }
            $this->copyPlan($key, $plan, $number);
        }

        return [$added, $updated];
    }

    private function copyPlan(string $key, string $plan, string $number): void
    {
        $disk = ProgramFiles::disk();
        $target = ProgramFiles::planName(self::encode($number));
        if ($plan === '' || $number === '' || $plan === $target || ! ProgramFiles::safeName($plan) || ! ProgramFiles::safeName($target)) {
            return;
        }
        if ($disk->fileExists($key.'/plans/'.$plan)) {
            $disk->copy($key.'/plans/'.$plan, $key.'/plans/'.$target);
        }
    }

    private function numbers(object $program): array
    {
        return array_fill_keys(DB::table('appartements')->where('lot', (string) $program->projet_id)->pluck('numero')->map(fn ($n) => (string) $n)->all(), true);
    }

    private static function encode(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    public function uploadCsv(Request $request, string $key): Response
    {
        ProgramFiles::program($key);
        $file = $request->file('Filedata');
        if (! $file instanceof UploadedFile || ! ProgramFiles::accepts($file, ['csv'])) {
            return response('Invalid file type.');
        }
        ProgramFiles::disk()->putFileAs($key, $file, 'import.csv');

        return response('1');
    }

    public function addPlans(Request $request, string $key): View
    {
        $program = ProgramFiles::program($key);
        $data = ['title' => 'Corim promotion - Ajouter les plans'];

        if ($request->isMethod('post')) {
            $failed = [];
            foreach (array_filter((array) $request->file('plan_upload'), fn ($file) => $file instanceof UploadedFile) as $file) {
                $error = $this->storePlan($file, $program);
                if ($error !== null) {
                    $failed[] = $error;
                }
            }
            if ($failed !== []) {
                $data['error'] = implode(' ', $failed);
            }
        }

        $disk = ProgramFiles::disk();
        $data['program'] = $program;
        $data['lots'] = DB::table('appartements')->where('lot', (string) $program->projet_id)->get(['id', 'numero'])
            ->map(function ($lot) use ($disk, $key) {
                $lot->plan = ProgramFiles::planName((string) $lot->numero);
                $lot->exists = ProgramFiles::safeName($lot->plan) && $disk->fileExists($key.'/plans/'.$lot->plan);

                return $lot;
            });

        return view('programs-lots.add_plans', $data);
    }

    public function uploadPlan(Request $request, string $key): Response
    {
        $program = ProgramFiles::program($key);
        $file = $request->file('Filedata');
        if (! $file instanceof UploadedFile) {
            return response('Invalid file type.');
        }

        return response($this->storePlan($file, $program) ?? '1');
    }

    private function storePlan(UploadedFile $file, object $program): ?string
    {
        $name = $file->getClientOriginalName();
        if (! ProgramFiles::accepts($file, ['pdf'])) {
            return $name.' échoué.';
        }
        $key = (string) $program->projet_titre_key;
        $disk = ProgramFiles::disk();
        $csv = $key.'/import.csv';
        $planMap = $disk->fileExists($csv) ? LotImport::planMap((string) $disk->get($csv)) : [];
        $lots = DB::table('appartements')->where('lot', (string) $program->projet_id)->get(['id', 'numero']);
        $lot = LotImport::lotForPlan($name, $lots, $planMap);
        if ($lot === null) {
            return $name.' : aucun lot '.LotImport::planReference($name).' dans ce programme';
        }
        $target = ProgramFiles::planName((string) $lot->numero);

        return ProgramFiles::safeName($target) && $disk->putFileAs($key.'/plans', $file, $target) !== false ? null : $name.' échoué.';
    }

    private function savePlan(mixed $file, string $key, string $number): void
    {
        $name = ProgramFiles::planName($number);
        if ($file instanceof UploadedFile && ProgramFiles::safeName($name) && ProgramFiles::accepts($file, ['pdf'])) {
            ProgramFiles::disk()->putFileAs($key.'/plans', $file, $name);
        }
    }

    private function insertLot(object $program, array $values): bool
    {
        return DB::table('appartements')->insert(array_map(fn (string $value) => htmlspecialchars($value, ENT_QUOTES), $values) + [
            'lot' => (string) $program->projet_id,
            'dept' => (int) $program->dept,
            'ville' => htmlspecialchars(html_entity_decode((string) $program->projet_lieu, ENT_QUOTES), ENT_QUOTES),
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
