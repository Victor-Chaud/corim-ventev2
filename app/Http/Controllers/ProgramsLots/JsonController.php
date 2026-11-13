<?php

namespace App\Http\Controllers\ProgramsLots;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LegacyJson;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class JsonController extends Controller
{
    private const COLUMNS = [
        'id' => 'id', 'date' => 'date', 'dept' => 'dept', 'type' => 'type', 'cat' => 'categorie', 'categorie' => 'categorie',
        'prix' => 'prix', 'prix_m2' => 'prix_m2', 'etage' => 'etage', 'rdc' => 'RDC', 'RDC' => 'RDC', 'jardin' => 'jardin',
        'terrasse' => 'terrasse', 'superficie' => 'superficie', 'stationnement' => 'stationnement', 'exposition' => 'exposition',
        'numero' => 'numero', 'cellier' => 'cellier', 'accession' => 'accession',
    ];

    private function errors(bool $success): Response
    {
        return LegacyJson::respond(['errors' => $success ? 1 : 0]);
    }

    public function deleteFile(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }

        return $this->errors(ProgramFiles::deleteFromUrl((string) $request->input('url'), ['documents', 'visuels', 'plans', 'diapo']));
    }

    public function deleteSlide(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }

        return $this->errors(ProgramFiles::deleteFromUrl((string) $request->input('key'), ['diapo']));
    }

    public function archive(Request $request): Response
    {
        return $this->setArchive($request, 1);
    }

    public function unarchive(Request $request): Response
    {
        return $this->setArchive($request, 0);
    }

    private function setArchive(Request $request, int $archive): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $count = DB::table('projet')->where('projet_titre_key', (string) $request->input('key'))
            ->where('archive', '!=', $archive)->update(['archive' => $archive]);

        return $this->errors($count === 1);
    }

    public function deleteLot(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $idlot = (string) $request->input('idlot');
        $programId = (string) $request->input('idprojet');
        if (! ctype_digit($idlot) || ! ctype_digit($programId)) {
            return $this->errors(false);
        }

        $deleted = DB::transaction(function () use ($idlot, $programId) {
            $count = DB::table('appartements')->where('id', (int) $idlot)->where('lot', $programId)->delete();
            if ($count === 1) {
                DB::table('d_options')->where('iddlot', (int) $idlot)->where('idprojet', (int) $programId)->delete();
                DB::table('d_reservations')->where('iddlot', (int) $idlot)->where('idprojet', (int) $programId)->delete();
                DB::table('options')->where('idlot', (int) $idlot)->where('idprojet', (int) $programId)->delete();
                DB::table('reservations')->where('idlot', (int) $idlot)->where('idprojet', (int) $programId)->delete();
            }

            return $count;
        });

        return $this->errors($deleted === 1);
    }

    public function deleteAllInProject(Request $request): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $project = (string) $request->input('project');
        if (! ctype_digit($project)) {
            return $this->errors(false);
        }

        $deleted = DB::transaction(function () use ($project) {
            $ids = DB::table('appartements')->where('lot', $project)->pluck('id');
            DB::table('acces_lot')->whereIn('idlot', $ids)->delete();

            return DB::table('appartements')->where('lot', $project)->delete();
        });

        return $this->errors($deleted >= 1);
    }

    public function datagrid(Request $request, string $programId): Response
    {
        if (! $request->isMethod('post')) {
            return response('');
        }
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $page = (string) $request->input('page', '1');
        $rp = max(1, (int) $request->input('rp', 100));
        $sortname = (string) $request->input('sortname', 'id');
        $sortorder = strtolower((string) $request->input('sortorder')) === 'desc' ? 'desc' : 'asc';
        $qtype = self::COLUMNS[(string) $request->input('qtype')] ?? null;
        $query = (string) $request->input('query');

        $base = DB::table('appartements')->where('lot', $programId)
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->whereIn('id', DB::table('acces_lot')->where('iduser', $user->id)->select('idlot')))
            ->when($qtype !== null && $query !== '', fn (Builder $q) => $q->where($qtype, 'like', '%'.addcslashes($query, '%_\\').'%'));

        $total = (clone $base)->count();
        $rows = $base
            ->when($sortname === 'numero', fn (Builder $q) => $q->orderByRaw("LPAD(numero,2,'00') ".$sortorder), fn (Builder $q) => $q->orderBy(self::COLUMNS[$sortname] ?? 'id', $sortorder))
            ->offset((max(1, (int) $page) - 1) * $rp)->limit($rp)->get();

        $key = (string) DB::table('projet')->where('projet_id', (int) $programId)->value('projet_titre_key');
        $disk = ProgramFiles::disk();
        $data = ['page' => $page, 'total' => (string) $total, 'rows' => []];
        foreach ($rows as $row) {
            $plan = ProgramFiles::planName((string) $row->numero);
            $actions = $key !== '' && ProgramFiles::safeName($plan) && $disk->fileExists($key.'/plans/'.$plan)
                ? '<a href="'.e(ProgramFiles::url($key, 'plans', $plan)).'" target="_blank" class="btn btn-info btn-xs btn-block">Plan</a>'
                : '-';
            $date = e((string) $row->date, false);
            if (in_array($row->statut, ['opt', 'res', 'act'], true)) {
                $date = '<span class="'.$row->statut.'">'.$date.'</span>';
            }
            $data['rows'][] = [
                'id' => (string) $row->id,
                'cell' => [$date, ...array_map(fn ($value) => e((string) $value, false), [$row->type, $row->categorie, $row->prix, $row->prix_m2, $row->etage, $row->RDC, $row->jardin, $row->terrasse, $row->superficie.' m²', $row->stationnement, $row->exposition]), $actions, ...array_map(fn ($value) => e((string) $value, false), [$row->numero, $row->cellier, $row->accession])],
            ];
        }

        return LegacyJson::respond($data);
    }
}
