<?php

namespace App\Http\Controllers\ProgrammesLots;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgrammesLots\ProgrammeRequest;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProgrammeController extends Controller
{
    private const THUMB_ERROR = 'L\'image doit être au format .gif, .jpg, .png et doit être inférieur à 5M';

    private const REQUIRED = 'Tous les champs sont requis';

    public function ajouter(ProgrammeRequest $request): View
    {
        $data = ['title' => 'Corim promotion - Ajouter un programme'];

        if ($request->isMethod('post')) {
            $titre = (string) $request->input('titre');
            $thumb = $request->file('thumb');
            $filled = $titre !== '' && (string) $request->input('livraison') !== '' && (string) $request->input('ville') !== '' && (string) $request->input('description') !== '';

            if (! $filled || ! $thumb instanceof UploadedFile) {
                $data['error'] = self::REQUIRED;
            } elseif (! ProgrammeFiles::accepts($thumb, ['gif', 'jpg', 'png'], 5120)) {
                $data['error'] = self::THUMB_ERROR;
            } elseif (DB::table('projet')->where('projet_titre_key', md5($titre))->exists()) {
                $data['error'] = 'Ce programme existe déjà';
            } else {
                $key = md5($titre);
                ProgrammeFiles::makeDirectories($key);
                if (! ProgrammeFiles::saveThumb($thumb, $key)) {
                    $data['error'] = self::THUMB_ERROR;
                } else {
                    DB::table('projet')->insert([
                        'projet_titre' => $titre,
                        'projet_titre_key' => $key,
                        'projet_lieu' => (string) $request->input('ville'),
                        'projet_image' => '',
                        'archive' => 0,
                        'dept' => '',
                        'projet_nb_appartements' => 0,
                        'projet_description' => (string) $request->input('description'),
                        'livraison' => (string) $request->input('livraison'),
                    ]);
                    $data['message_valid'] = 'Le programme a été ajouté';
                }
            }
        }

        return view('programmes-lots.ajouter_programme', $data);
    }

    public function modifier(ProgrammeRequest $request, string $key): View
    {
        $projet = ProgrammeFiles::projet($key);
        $data = ['title' => 'Corim promotion - Modifier un programme'];

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action');
            if ($action === 'project') {
                $this->updateProgramme($request, $projet, $data);
            } elseif ($action === 'uploader') {
                $this->uploadFiles($request, $key, $data);
            }
        }

        $data['GetProgramme'] = DB::table('projet')->where('projet_id', $projet->projet_id)->first();
        foreach (['Get_Docs' => 'documents', 'Get_Visus' => 'visuels', 'Get_Plans' => 'plans'] as $name => $dir) {
            $data[$name] = array_map(fn ($file) => ['url' => ProgrammeFiles::url($key, $dir, $file), 'name' => $file], ProgrammeFiles::files($key, $dir));
        }
        $disk = ProgrammeFiles::disk();
        $data['thumb'] = collect(['jpg', 'png', 'gif'])->first(fn ($ext) => $disk->fileExists($key.'/thumb/thumb.'.$ext));

        return view('programmes-lots.modifier_programme', $data);
    }

    private function updateProgramme(ProgrammeRequest $request, object $projet, array &$data): void
    {
        $values = [];
        foreach (['titre', 'livraison', 'ville', 'description', 'dept'] as $field) {
            $values[$field] = (string) $request->input($field);
        }
        if (in_array('', $values, true)) {
            $data['error'] = self::REQUIRED;

            return;
        }

        $key = $projet->projet_titre_key;
        $thumb = $request->file('thumb');
        if ($thumb instanceof UploadedFile) {
            if (ProgrammeFiles::accepts($thumb, ['gif', 'jpg', 'png'], 10240) && @imagecreatefromstring((string) file_get_contents($thumb->getRealPath())) !== false) {
                ProgrammeFiles::disk()->delete(ProgrammeFiles::disk()->files($key.'/thumb'));
                ProgrammeFiles::saveThumb($thumb, $key);
            } else {
                $data['error'] = self::THUMB_ERROR;
            }
        }

        DB::transaction(function () use ($projet, $values) {
            DB::table('projet')->where('projet_id', $projet->projet_id)->update([
                'projet_titre' => $values['titre'],
                'projet_lieu' => $values['ville'],
                'projet_description' => $values['description'],
                'dept' => $values['dept'],
                'livraison' => $values['livraison'],
            ]);
            DB::table('appartements')->where('lot', (string) $projet->projet_id)->update([
                'ville' => $values['ville'],
                'dept' => (int) $values['dept'],
                'date' => $values['livraison'],
            ]);
        });
        $data['message_valid'] = 'Le programme a bien été modifié';
    }

    private function uploadFiles(ProgrammeRequest $request, string $key, array &$data): void
    {
        $files = array_filter((array) $request->file('filer'), fn ($file) => $file instanceof UploadedFile);
        if ($files === []) {
            return;
        }
        $type = (string) $request->input('type');
        if (! isset(ProgrammeFiles::UPLOAD_TYPES[$type])) {
            $data['error'] = 'Veuillez choisir un type de fichier';

            return;
        }
        foreach ($files as $file) {
            if (ProgrammeFiles::accepts($file, ProgrammeFiles::UPLOAD_TYPES[$type])) {
                ProgrammeFiles::disk()->putFileAs($key.'/'.$type, $file, $file->getClientOriginalName());
            } else {
                $data['error'] = 'Le format de fichier n\'est pas reconnu';
            }
        }
    }

    public function show(string $key): View|RedirectResponse
    {
        $projet = ProgrammeFiles::projet($key);
        $user = auth()->user();
        if (! $user instanceof User || ! Access::canSeeProgramme($user, (int) $projet->projet_id)) {
            return redirect('/');
        }
        $isAdmin = $user->isAdmin();
        $counts = DB::table('appartements')->where('lot', (string) $projet->projet_id)
            ->selectRaw("SUM(statut = 'opt') AS nb_option, SUM(statut = 'res') AS nb_resa, SUM(statut = 'act') AS nb_act")
            ->first();

        return view('programmes-lots.programme', [
            'title' => 'Corim promotion - Programme',
            'programme' => $projet,
            'libelle' => strtoupper(html_entity_decode($projet->projet_titre.' - '.$projet->projet_lieu, ENT_QUOTES)),
            'image' => ProgrammeFiles::thumb($key),
            'Get_Visus' => array_map(fn ($file) => ['url' => ProgrammeFiles::url($key, 'visuels', $file), 'name' => $file], ProgrammeFiles::files($key, 'visuels')),
            'Get_Docs' => array_map(fn ($file) => ['url' => ProgrammeFiles::url($key, 'documents', $file), 'name' => $file], ProgrammeFiles::files($key, 'documents')),
            'Get_Diapo' => array_map(fn ($file) => ProgrammeFiles::url($key, 'diapo', $file), ProgrammeFiles::files($key, 'diapo')),
            'CountHisOpt' => (int) ($counts->nb_option ?? 0),
            'CountHisResa' => (int) ($counts->nb_resa ?? 0),
            'CountHisAct' => (int) ($counts->nb_act ?? 0),
            'isAdmin' => $isAdmin,
            'sessionId' => $user->getAuthIdentifier(),
            'Prescripteurs' => $isAdmin ? DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')
                ->where('privileges.acces', 0)->orderBy('name')->get(['user.id', 'user.name']) : collect(),
        ]);
    }
}
