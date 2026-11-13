<?php

namespace App\Http\Controllers\ProgramsLots;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramsLots\ProgramRequest;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProgramController extends Controller
{
    private const THUMB_ERROR = 'L\'image doit être au format .gif, .jpg, .png et doit être inférieur à 5M';

    private const REQUIRED = 'Tous les champs sont requis';

    public function add(ProgramRequest $request): View
    {
        $data = ['title' => 'Corim promotion - Ajouter un programme'];

        if ($request->isMethod('post')) {
            $title = (string) $request->input('titre');
            $thumb = $request->file('thumb');
            $filled = $title !== '' && (string) $request->input('livraison') !== '' && (string) $request->input('ville') !== '' && (string) $request->input('description') !== '';

            if (! $filled || ! $thumb instanceof UploadedFile) {
                $data['error'] = self::REQUIRED;
            } elseif (! ProgramFiles::accepts($thumb, ['gif', 'jpg', 'png'], 5120)) {
                $data['error'] = self::THUMB_ERROR;
            } elseif (DB::table('projet')->where('projet_titre_key', md5($title))->exists()) {
                $data['error'] = 'Ce programme existe déjà';
            } else {
                $key = md5($title);
                ProgramFiles::makeDirectories($key);
                if (! ProgramFiles::saveThumb($thumb, $key)) {
                    $data['error'] = self::THUMB_ERROR;
                } else {
                    DB::table('projet')->insert([
                        'projet_titre' => $title,
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

        return view('programs-lots.add_program', $data);
    }

    public function edit(ProgramRequest $request, string $key): View
    {
        $program = ProgramFiles::program($key);
        $data = ['title' => 'Corim promotion - Modifier un programme'];

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action');
            if ($action === 'project') {
                $this->updateProgram($request, $program, $data);
            } elseif ($action === 'uploader') {
                $this->uploadFiles($request, $key, $data);
            }
        }

        $data['GetProgram'] = DB::table('projet')->where('projet_id', $program->projet_id)->first();
        foreach (['Get_Docs' => 'documents', 'Get_Visuals' => 'visuels', 'Get_Plans' => 'plans'] as $name => $dir) {
            $data[$name] = array_map(fn ($file) => ['url' => ProgramFiles::url($key, $dir, $file), 'name' => $file], ProgramFiles::files($key, $dir));
        }
        $disk = ProgramFiles::disk();
        $data['thumb'] = collect(['jpg', 'png', 'gif'])->first(fn ($ext) => $disk->fileExists($key.'/thumb/thumb.'.$ext));

        return view('programs-lots.edit_program', $data);
    }

    private function updateProgram(ProgramRequest $request, object $program, array &$data): void
    {
        $values = [];
        foreach (['titre', 'livraison', 'ville', 'description', 'dept'] as $field) {
            $values[$field] = (string) $request->input($field);
        }
        if (in_array('', $values, true)) {
            $data['error'] = self::REQUIRED;

            return;
        }

        $key = $program->projet_titre_key;
        $thumb = $request->file('thumb');
        if ($thumb instanceof UploadedFile) {
            if (ProgramFiles::accepts($thumb, ['gif', 'jpg', 'png'], 10240) && @imagecreatefromstring((string) file_get_contents($thumb->getRealPath())) !== false) {
                ProgramFiles::disk()->delete(ProgramFiles::disk()->files($key.'/thumb'));
                ProgramFiles::saveThumb($thumb, $key);
            } else {
                $data['error'] = self::THUMB_ERROR;
            }
        }

        DB::transaction(function () use ($program, $values) {
            DB::table('projet')->where('projet_id', $program->projet_id)->update([
                'projet_titre' => $values['titre'],
                'projet_lieu' => $values['ville'],
                'projet_description' => $values['description'],
                'dept' => $values['dept'],
                'livraison' => $values['livraison'],
            ]);
            DB::table('appartements')->where('lot', (string) $program->projet_id)->update([
                'ville' => $values['ville'],
                'dept' => (int) $values['dept'],
                'date' => $values['livraison'],
            ]);
        });
        $data['message_valid'] = 'Le programme a bien été modifié';
    }

    private function uploadFiles(ProgramRequest $request, string $key, array &$data): void
    {
        $files = array_filter((array) $request->file('filer'), fn ($file) => $file instanceof UploadedFile);
        if ($files === []) {
            return;
        }
        $type = (string) $request->input('type');
        if (! isset(ProgramFiles::UPLOAD_TYPES[$type])) {
            $data['error'] = 'Veuillez choisir un type de fichier';

            return;
        }
        foreach ($files as $file) {
            if (ProgramFiles::accepts($file, ProgramFiles::UPLOAD_TYPES[$type])) {
                ProgramFiles::disk()->putFileAs($key.'/'.$type, $file, $file->getClientOriginalName());
            } else {
                $data['error'] = 'Le format de fichier n\'est pas reconnu';
            }
        }
    }

    public function show(string $key): View|RedirectResponse
    {
        $program = ProgramFiles::program($key);
        $user = auth()->user();
        if (! $user instanceof User || ! Access::canSeeProgram($user, (int) $program->projet_id)) {
            return redirect('/');
        }
        $isAdmin = $user->isAdmin();
        $counts = DB::table('appartements')->where('lot', (string) $program->projet_id)
            ->selectRaw("SUM(statut = 'opt') AS nb_option, SUM(statut = 'res') AS nb_resa, SUM(statut = 'act') AS nb_act")
            ->first();

        return view('programs-lots.program', [
            'title' => 'Corim promotion - Programme',
            'program' => $program,
            'label' => strtoupper(html_entity_decode($program->projet_titre.' - '.$program->projet_lieu, ENT_QUOTES)),
            'image' => ProgramFiles::thumb($key),
            'Get_Visuals' => array_map(fn ($file) => ['url' => ProgramFiles::url($key, 'visuels', $file), 'name' => $file], ProgramFiles::files($key, 'visuels')),
            'Get_Docs' => array_map(fn ($file) => ['url' => ProgramFiles::url($key, 'documents', $file), 'name' => $file], ProgramFiles::files($key, 'documents')),
            'Get_Slides' => array_map(fn ($file) => ProgramFiles::url($key, 'diapo', $file), ProgramFiles::files($key, 'diapo')),
            'CountHisOptions' => (int) ($counts->nb_option ?? 0),
            'CountHisReservations' => (int) ($counts->nb_resa ?? 0),
            'CountHisDeeds' => (int) ($counts->nb_act ?? 0),
            'isAdmin' => $isAdmin,
            'sessionId' => $user->getAuthIdentifier(),
            'Prescribers' => $isAdmin ? DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')
                ->where('privileges.acces', 0)->orderBy('name')->get(['user.id', 'user.name']) : collect(),
        ]);
    }
}
