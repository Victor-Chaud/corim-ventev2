<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgrammesLots\ProgrammeFiles;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function welcome(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $acces = $user->acces();
        $filter = fn (Builder $query, string $column) => $query->when($acces === '0', fn (Builder $q) => $q->where($column, $user->id));

        $programmes = match ($acces) {
            '1' => DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get(),
            '0' => DB::table('acces')->join('projet', 'acces.idprojet', '=', 'projet.projet_id')
                ->where('acces.iduser', $user->id)
                ->select('acces.idprojet', 'projet.projet_id', 'projet.projet_titre', 'projet.projet_titre_key', 'projet.projet_lieu', 'projet.projet_image', 'projet.projet_nb_appartements', 'projet.dept')
                ->orderByDesc('projet.projet_id')->get(),
            default => collect(),
        };

        $valides = fn (string $table, string $id) => $filter(DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->join('appartements', "$table.idlot", '=', 'appartements.id')
            ->where("$table.statut", 1), "$table.iduser")->count("$table.$id");

        $demandes = fn (string $table, string $id, bool $lot = true) => $filter(DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->when($lot, fn (Builder $q) => $q->join('appartements', "$table.iddlot", '=', 'appartements.id')), "$table.iduser")->count("$table.$id");

        $counters = [
            ['ventes', $filter(DB::table('appartements')->join('projet', 'appartements.lot', '=', 'projet.projet_id')->join('ventes', 'appartements.id', '=', 'ventes.idlot')->where('appartements.statut', 'act')->where('ventes.statut', 1), 'appartements.vendeur')->count('appartements.id'), 'LOTS ACT&Eacute;', false],
            ['ask_options', $demandes('d_options', 'iddoption'), "DEMANDES D'OPTIONS", true],
            ['options', $valides('options', 'idoption'), 'OPTIONS', false],
            ['ask_reservations', $demandes('d_reservations', 'iddresa'), 'DEMANDES DE R&Eacute;SERVATIONS', true],
            ['reservations', $valides('reservations', 'idresa'), 'R&Eacute;SERVATIONS', false],
            ['ask_denonciations', $demandes('d_prospects', 'idprospect', false), 'DEMANDES DE D&Eacute;NONCIATIONS', true],
            ['denonciations', $filter(DB::table('prospects')->join('projet', 'prospects.idprojet', '=', 'projet.projet_id')->join('user', 'prospects.iduser', '=', 'user.id'), 'prospects.iduser')->count('prospects.idprospect'), 'D&Eacute;NONCIATIONS', false],
        ];

        return view('public.welcome', [
            'title' => 'Corim promotion - Tableau de bord',
            'programmes' => $programmes->map(function ($programme) {
                $key = (string) $programme->projet_titre_key;
                $programme->thumb = ProgrammeFiles::safeName($key) ? ProgrammeFiles::thumb($key) : '';

                return $programme;
            }),
            'counters' => $counters,
            'admin' => $acces === '1',
        ]);
    }

    public function cgu(): View
    {
        return view('public.cgu', ['title' => 'Conditions générales de vente']);
    }

    public function mentions(): View
    {
        return view('public.mentions', ['title' => '']);
    }

    public function rechercher(): never
    {
        abort(404);
    }

    public function app(): View
    {
        return view('public.app');
    }
}
