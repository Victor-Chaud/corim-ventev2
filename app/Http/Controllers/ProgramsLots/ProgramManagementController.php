<?php

namespace App\Http\Controllers\ProgramsLots;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProgramManagementController extends Controller
{
    public function index(): View
    {
        return view('programs-lots.program_management', [
            'title' => 'Corim Promotion - Gestion des programmes',
            'programs' => DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get(),
            'archives' => DB::table('projet')->where('archive', 1)->orderBy('projet_id')->get(),
        ]);
    }

    public function slideshow(Request $request, string $key): View
    {
        $program = ProgramFiles::program($key);

        if ($request->isMethod('post')) {
            $file = $request->file('diapo');
            if ($file instanceof UploadedFile && ProgramFiles::accepts($file, ['jpg', 'jpeg', 'png', 'gif'])) {
                ProgramFiles::disk()->putFileAs($key.'/diapo', $file, $file->getClientOriginalName());
            }
        }

        return view('programs-lots.slideshow', [
            'title' => 'Corim Promotion - Gestion du diaporama',
            'program' => $program,
            'image' => ProgramFiles::thumb($key),
            'slides' => array_map(fn ($file) => ProgramFiles::url($key, 'diapo', $file), ProgramFiles::files($key, 'diapo')),
        ]);
    }
}
