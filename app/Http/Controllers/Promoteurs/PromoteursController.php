<?php

namespace App\Http\Controllers\Promoteurs;

use App\Http\Controllers\Controller;
use App\Mail\Promoteurs\AccesMail;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PromoteursController extends Controller
{
    private const TITLE = 'Corim promotion - Gestion des promoteurs';

    private function users(): Builder
    {
        return DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')->select('user.*', 'privileges.acces')->orderBy('name');
    }

    public function index(Request $request): View
    {
        $users = $this->users();
        $projects = array_values(array_map('intval', (array) $request->input('projects', [])));

        if ($request->isMethod('post') && $projects !== [] && $request->has('type')) {
            $users->whereIn('user.id', function (Builder $q) use ($request, $projects) {
                $q->select('iduser')->from('acces')->whereIn('idprojet', $projects)->groupBy('iduser');
                if ((int) $request->input('type') === 1) {
                    $q->havingRaw('COUNT(DISTINCT idprojet) = ?', [count(array_unique($projects))]);
                }
            });
        }

        $programmes = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get()
            ->each(function (object $programme) {
                $files = glob(storage_path('app/medias/programmes/'.basename((string) $programme->projet_titre_key).'/thumb/*')) ?: [];
                $programme->thumb = $files ? '/medias/programmes/'.$programme->projet_titre_key.'/thumb/'.basename($files[0]) : '';
            })
            ->values();

        return view('promoteurs.gestion_promoteurs', [
            'title' => self::TITLE,
            'clients' => $users->get(),
            'programmes' => $programmes,
            'lots' => DB::table('appartements')->orderBy('id')->get()->groupBy(fn (object $lot) => (string) $lot->lot),
            'status' => '',
            'post' => $request->isMethod('post') ? $request->post() : [],
        ]);
    }

    public function actifs(): View
    {
        return $this->byStatut(1, 'actifs');
    }

    public function inactifs(): View
    {
        return $this->byStatut(0, 'inactifs');
    }

    private function byStatut(int $statut, string $status): View
    {
        return view('promoteurs.gestion_promoteurs', [
            'title' => self::TITLE,
            'clients' => $this->users()->where('user.statut', $statut)->get(),
            'programmes' => collect(),
            'lots' => collect(),
            'status' => $status,
            'post' => [],
        ]);
    }

    public function modifier(Request $request, ?string $id = null): View|RedirectResponse
    {
        if ($id === null || $id === '') {
            return redirect('/');
        }

        $id = (int) $id;
        $exists = $this->users()->where('user.id', $id)->exists();
        $error = null;
        $success = null;

        if ($exists && $request->isMethod('post') && $request->post() !== []) {
            $pseudo = (string) $request->input('f_edit_promoteur_pseudo', '');
            $name = (string) $request->input('f_edit_promoteur_name', '');
            $pwd = (string) $request->input('f_edit_promoteur_pwd', '');
            $email = (string) $request->input('f_edit_promoteur_email', '');
            $statut = (string) $request->input('f_edit_promoteur_statut', '');

            if ($pseudo === '' || $name === '' || $email === '' || ! in_array($statut, ['0', '1'], true)) {
                $error = 'Attention, les champs Identifiant, Nom, E-mail et  Statut sont obligatoires';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Attention, l\'e-mail renseigné ne correspond pas à un e-mail valide';
            } else {
                $values = ['pseudo' => $pseudo, 'name' => $name, 'email' => $email, 'statut' => (int) $statut];
                if ($pwd !== '') {
                    $values['password'] = Hash::make($pwd);
                }
                DB::table('user')->where('id', $id)->update($values);
                Mail::to($email)->send(new AccesMail($pseudo, $pwd, true));
                $success = 'Le compte a été modifié';
            }
        }

        return view('promoteurs.modifier_promoteur', [
            'title' => self::TITLE.' - Modifier le compte',
            'client' => $this->users()->where('user.id', $id)->first(),
            'error' => $error,
            'success' => $success,
        ]);
    }
}
