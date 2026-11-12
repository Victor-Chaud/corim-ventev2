<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FlashInfoController extends Controller
{
    private const TITLE = 'Corim promotion - ';

    public function index(): View
    {
        return view('communication.flash_info', [
            'title' => self::TITLE.'Flash Info pour Corim Promotion',
            'infos' => DB::table('news')->orderByDesc('date')->get(),
        ]);
    }

    private function values(Request $request): array
    {
        $values = [];
        foreach (['string1', 'string2', 'string3', 'string4', 'thumb', 'url'] as $field) {
            $values[$field] = (string) $request->input($field, '');
        }

        return $values;
    }

    public function add(Request $request): View
    {
        $valid = null;
        $error = null;
        if ($request->isMethod('post')) {
            $values = $this->values($request);
            if ($values['thumb'] !== '') {
                DB::table('news')->insert($values + ['date' => time()]);
                $valid = 'Le flash info a été ajouté';
            } else {
                $error = 'La vignette est obligatoire';
            }
        }

        return view('communication.flash_info_add', [
            'title' => self::TITLE.'Ajouter un Flash Info pour Corim Promotion',
            'valid' => $valid,
            'error' => $error,
            'programmes' => MessageController::programmes(),
        ]);
    }

    public function edit(Request $request, string $id): View|RedirectResponse
    {
        $id = (int) $id;
        if (! DB::table('news')->where('id', $id)->exists()) {
            return redirect('/');
        }

        $valid = null;
        $error = null;
        if ($request->isMethod('post')) {
            $values = $this->values($request);
            if ($values['thumb'] !== '' && (string) $request->input('id', '') !== '') {
                DB::table('news')->where('id', $id)->update($values + ['date' => time()]);
                $valid = 'Le flash info a été mis à jour';
            } else {
                $error = 'La vignette est obligatoire';
            }
        }

        return view('communication.flash_info_edit', [
            'title' => self::TITLE.'Éditer un Flash Info pour Corim Promotion',
            'valid' => $valid,
            'error' => $error,
            'info' => DB::table('news')->where('id', $id)->first(),
            'programmes' => MessageController::programmes(),
        ]);
    }

    public function delete(string $id): RedirectResponse
    {
        $deleted = DB::table('news')->where('id', (int) $id)->delete();

        return redirect($deleted === 1 ? '/flash_info' : '/');
    }
}
