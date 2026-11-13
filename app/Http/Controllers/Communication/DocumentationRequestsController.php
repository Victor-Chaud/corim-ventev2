<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentationRequestsController extends Controller
{
    public function index(): View
    {
        $titles = DB::table('projet')->pluck('projet_titre', 'projet_titre_key');
        $emails = DB::table('docs')->orderByDesc('time')->get()->map(function (object $doc) use ($titles) {
            $parts = explode('/', str_replace(['https://corim-vente.com/medias/programmes/', 'http://corim-vente.com/medias/programmes/'], '', (string) $doc->url));
            $doc->program = (string) ($titles[$parts[0]] ?? '') !== '' ? (string) $titles[$parts[0]] : $parts[0];
            $doc->plan = $parts[2] ?? '';

            return $doc;
        });

        return view('communication.emails', ['title' => 'Corim promotion - Emails provenant de Corim Promotion', 'emails' => $emails]);
    }
}
