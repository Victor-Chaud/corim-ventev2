<?php

namespace App\Http\Controllers\ClientsProspects;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Evolutions\PrescripteurApiController;
use App\Support\LegacyJson;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ClientsController extends Controller
{
    public const FIELDS = ['date', 'source', 'projets', 'civilite', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget', 'observation', 'reponse'];

    private const SEARCHED = ['date', 'source', 'projets', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget'];

    private const EXPORTED = ['date', 'source', 'projets', 'civilite', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget'];

    private const CSV = 'import.csv';

    private const CSV_MIMES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv', 'application/octet-stream', 'application/x-empty', 'inode/x-empty'];

    private static function disk(): Filesystem
    {
        return Storage::disk('clients');
    }

    private static function title(string $suffix): string
    {
        return 'Corim promotion - Gestion des clients'.$suffix;
    }

    private static function encode(string $value): string
    {
        return htmlspecialchars(html_entity_decode($value, ENT_QUOTES), ENT_QUOTES);
    }

    private static function stored(array $values): array
    {
        return array_map(fn ($value) => self::encode((string) $value), $values);
    }

    private static function values(Request $request): array
    {
        return array_combine(self::FIELDS, array_map(fn ($field) => (string) $request->input($field, ''), self::FIELDS));
    }

    public function index(): View
    {
        return view('clients-prospects.clients', ['title' => self::title('')]);
    }

    public function ajouter(Request $request): View
    {
        $data = ['title' => self::title(' - Ajouter un client')];
        if ($request->isMethod('post')) {
            $values = self::values($request);
            if ($values['nom'] !== '') {
                if (DB::table('clients')->insert(self::stored($values))) {
                    $data['success'] = 'Le client '.$values['civilite'].' '.$values['nom'].' a été ajouté';
                }
            } else {
                $data['error'] = 'Veuillez renseigner le nom du nouveau client';
            }
        }

        return view('clients-prospects.ajouter_client', $data);
    }

    public function edition(Request $request, string $id): View
    {
        $data = ['title' => self::title(' - Édition d\'un client')];
        $query = fn () => DB::table('clients')->where('idclient', ctype_digit($id) ? (int) $id : 0);
        if ($request->isMethod('post') && $query()->exists()) {
            $values = self::values($request);
            if ($values['nom'] !== '') {
                $query()->update(self::stored($values));
                $data['success'] = 'Le client '.$values['civilite'].' '.$values['nom'].' a été modifié';
            } else {
                $data['error'] = 'Veuillez renseigner le nom du nouveau client';
            }
        }
        $data['client'] = $query()->first();

        return view('clients-prospects.edition_client', $data);
    }

    public function supprimer(string $id): View
    {
        $data = ['title' => self::title(' - Suppression d\'un client')];
        if (ctype_digit($id) && DB::table('clients')->where('idclient', (int) $id)->delete() === 1) {
            $data['success'] = 'Le client a été supprimé';
        }

        return view('clients-prospects.clients', $data);
    }

    public function telecharger(): Response
    {
        $output = '';
        foreach (DB::table('clients')->get(self::EXPORTED) as $i => $row) {
            if ($i === 0) {
                $output .= implode(';', self::EXPORTED)."\n";
            }
            $output .= implode(';', array_map(function ($value) {
                $clean = htmlspecialchars(str_replace(["\n", "\r", ',', '&amp;', '&#039;'], ' ', (string) $value), ENT_COMPAT | ENT_HTML401);

                return trim(mb_convert_encoding(PrescripteurApiController::cellule(trim($clean)), 'ISO-8859-1', 'UTF-8'));
            }, (array) $row))."\n";
        }

        return response($output, 200, [
            'Content-Disposition' => 'attachment; filename=export.csv',
            'Content-Type' => 'text/csv; charset=ISO-8859-1',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0, public',
            'Expires' => '0',
        ]);
    }

    public function upload(Request $request): Response
    {
        return response($this->store($request->file('Filedata')) ? '1' : 'Invalid file type.');
    }

    private function store(mixed $file): bool
    {
        if (! $file instanceof UploadedFile || ! $file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'csv'
            || ! in_array(strtolower((string) $file->getMimeType()), self::CSV_MIMES, true)) {
            return false;
        }

        return self::disk()->putFileAs('', $file, self::CSV) !== false;
    }

    public function ajouterEnMasse(Request $request): View
    {
        $data = ['title' => self::title(' - Ajouter des clients')];
        $disk = self::disk();
        if ($request->isMethod('post')) {
            $file = $request->file('csv_upload');
            if ($request->has('csv_delete')) {
                $disk->delete(self::CSV);
            } elseif ($file instanceof UploadedFile && ! $this->store($file)) {
                $data['error'] = 'Le format de fichier CSV n\'est pas reconnu';
            } elseif ($disk->exists(self::CSV) && ((string) $request->input('csv') === '1' || $file instanceof UploadedFile)) {
                $result = 0;
                foreach (self::rows((string) $disk->get(self::CSV)) as $row) {
                    $values = [];
                    foreach (self::FIELDS as $i => $field) {
                        $values[$field] = (string) ($row[$i] ?? '');
                    }
                    $result += DB::table('clients')->insert(self::stored($values)) ? 1 : 0;
                }
                if ($result === 1) {
                    $data['success'] = '1 client ont été ajouté';
                } elseif ($result > 1) {
                    $data['success'] = $result.' clients ont été ajoutés';
                } else {
                    $data['error'] = 'Problème lors de l\'enregistrement';
                }
            } else {
                $data['error'] = 'Aucun fichier CSV n\'est disponible sur le serveur.';
            }
        }
        $data['rows'] = $disk->exists(self::CSV) ? self::rows((string) $disk->get(self::CSV)) : null;

        return view('clients-prospects.ajouter_clients', $data);
    }

    public static function rows(string $content): array
    {
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
                $rows[] = array_map(fn ($cell) => (string) $cell, $row);
            }
        }
        fclose($handle);

        return $rows;
    }

    public function search(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $search = (string) $request->input('f_recherche_client_search', '');
        if ($search === '') {
            return LegacyJson::respond(['errors' => 0]);
        }
        $like = '%'.self::encode($search).'%';
        $rows = DB::table('clients')->where(function ($query) use ($like) {
            foreach (self::SEARCHED as $column) {
                $query->orWhere($column, 'like', $like);
            }
        })->get();
        if ($rows->isEmpty()) {
            return LegacyJson::respond(['errors' => 2]);
        }

        return LegacyJson::respond(['errors' => 1, 'call' => $rows->map(fn ($row) => array_map(
            fn ($value) => self::encode((string) $value),
            (array) $row
        ))->all()]);
    }
}
