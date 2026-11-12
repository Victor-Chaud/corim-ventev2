<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Auth\AccesMail;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PrivilegeController extends Controller
{
    public function index(): View
    {
        $programmes = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get()
            ->each(function (object $programme) {
                $files = glob(storage_path('app/medias/programmes/'.basename((string) $programme->projet_titre_key).'/thumb/*')) ?: [];
                $programme->thumb = $files ? '/medias/programmes/'.$programme->projet_titre_key.'/thumb/'.basename($files[0]) : '';
            })
            ->values();

        return view('auth-users-privileges.gestion_privilege', [
            'title' => 'Corim promotion - Gestion des privilèges',
            'clients' => DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')->select('user.*', 'privileges.acces')->orderBy('name')->get(),
            'programmes' => $programmes,
            'lots' => DB::table('appartements')->orderBy('id')->get()->groupBy(fn (object $lot) => (string) $lot->lot),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($request->post() === []) {
            return response('');
        }

        $iduser = (int) $request->input('user');
        $projets = $request->has('projet') ? array_map('intval', (array) $request->input('projet')) : null;
        $lots = array_map('intval', (array) $request->input('lot', []));

        DB::transaction(function () use ($iduser, $projets, $lots) {
            DB::table('acces')->where('iduser', $iduser)->delete();
            DB::table('acces_lot')->where('iduser', $iduser)->delete();

            if ($projets !== null) {
                DB::table('acces')->insert(array_map(fn ($id) => ['iduser' => $iduser, 'idprojet' => $id], $projets));
                DB::table('acces_lot')->insertOrIgnore(array_map(fn ($id) => ['iduser' => $iduser, 'idlot' => $id], $lots));
            }
        });

        $user = $projets !== null ? DB::table('user')->where('id', $iduser)->first() : null;

        if ($user) {
            Mail::to((string) $user->email)->send(new AccesMail(
                '[Corim Promotion] - Votre accès à la plateforme CORIM',
                'Nous vous informons avoir actualisé les informations et fichiers sur le(s) programme(s) en ligne.',
                html_entity_decode((string) $user->pseudo, ENT_QUOTES),
                '',
            ));
        }

        return LegacyJson::respond(['errors' => 1]);
    }

    public function projets(Request $request): Response
    {
        return $this->ids($request, 'acces', 'idprojet');
    }

    public function lots(Request $request): Response
    {
        return $this->ids($request, 'acces_lot', 'idlot');
    }

    public function showToolbar(Request $request): Response
    {
        $request->session()->put('SessionToolbar', '1');

        return LegacyJson::respond(['errors' => 0]);
    }

    public function hideToolbar(Request $request): Response
    {
        $request->session()->forget('SessionToolbar');

        return LegacyJson::respond(['errors' => 0]);
    }

    private function ids(Request $request, string $table, string $column): Response
    {
        if ($request->post() === []) {
            return response('');
        }

        $rows = DB::table($table)->where('iduser', (int) $request->input('key'))->pluck($column)
            ->map(fn ($id) => [$column => (string) $id])->all();

        return LegacyJson::respond($rows ? ['errors' => 1, 'call' => $rows] : ['errors' => 2]);
    }
}
