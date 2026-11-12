<?php

namespace App\Http\Controllers\ProgrammesLots;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GestionProgrammesController extends Controller
{
    public function index(): View
    {
        return view('programmes-lots.gestion_programme', [
            'title' => 'Corim Promotion - Gestion des programmes',
            'programmes' => DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get(),
            'archives' => DB::table('projet')->where('archive', 1)->orderBy('projet_id')->get(),
        ]);
    }

    public function diaporama(Request $request, string $key): View
    {
        $projet = ProgrammeFiles::projet($key);

        if ($request->isMethod('post')) {
            $file = $request->file('diapo');
            if ($file instanceof UploadedFile && ProgrammeFiles::accepts($file, ['jpg', 'jpeg', 'png', 'gif'])) {
                ProgrammeFiles::disk()->putFileAs($key.'/diapo', $file, $file->getClientOriginalName());
            }
        }

        return view('programmes-lots.gestion_diaporama', [
            'title' => 'Corim Promotion - Gestion du diaporama',
            'programme' => $projet,
            'image' => ProgrammeFiles::thumb($key),
            'diapos' => array_map(fn ($file) => ProgrammeFiles::url($key, 'diapo', $file), ProgrammeFiles::files($key, 'diapo')),
        ]);
    }
}
