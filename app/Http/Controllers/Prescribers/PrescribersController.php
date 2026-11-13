<?php

namespace App\Http\Controllers\Prescribers;

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Controller;
use App\Mail\Prescribers\AccessMail;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PrescribersController extends Controller
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

        $programs = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get()
            ->each(function (object $program) {
                $files = glob(storage_path('app/medias/programmes/'.basename((string) $program->projet_titre_key).'/thumb/*')) ?: [];
                $program->thumb = $files ? '/medias/programmes/'.$program->projet_titre_key.'/thumb/'.basename($files[0]) : '';
            })
            ->values();

        return view('prescribers.prescriber_management', [
            'title' => self::TITLE,
            'clients' => $users->get(),
            'programs' => $programs,
            'lots' => DB::table('appartements')->orderBy('id')->get()->groupBy(fn (object $lot) => (string) $lot->lot),
            'status' => '',
            'post' => $request->isMethod('post') ? $request->post() : [],
        ]);
    }

    public function active(): View
    {
        return $this->byStatus(1, 'actifs');
    }

    public function inactive(): View
    {
        return $this->byStatus(0, 'inactifs');
    }

    private function byStatus(int $statusValue, string $status): View
    {
        return view('prescribers.prescriber_management', [
            'title' => self::TITLE,
            'clients' => $this->users()->where('user.statut', $statusValue)->get(),
            'programs' => collect(),
            'lots' => collect(),
            'status' => $status,
            'post' => [],
        ]);
    }

    public function edit(Request $request, ?string $id = null): View|RedirectResponse
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
            $statusValue = (string) $request->input('f_edit_promoteur_statut', '');

            if ($pseudo === '' || $name === '' || $email === '' || ! in_array($statusValue, ['0', '1'], true)) {
                $error = 'Attention, les champs Identifiant, Nom, E-mail et  Statut sont obligatoires';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Attention, l\'e-mail renseigné ne correspond pas à un e-mail valide';
            } else {
                $values = ['pseudo' => $pseudo, 'name' => $name, 'email' => $email, 'statut' => (int) $statusValue];
                if ($pwd !== '') {
                    $values['password'] = Hash::make($pwd);
                }
                DB::table('user')->where('id', $id)->update($values);
                Mail::to($email)->send(new AccessMail($pseudo, null, true, $pwd !== ''));
                $success = 'Le compte a été modifié';
            }
        }

        return view('prescribers.edit_prescriber', [
            'title' => self::TITLE.' - Modifier le compte',
            'client' => $this->users()->where('user.id', $id)->first(),
            'error' => $error,
            'success' => $success,
        ]);
    }

    public function sendResetLink(int $id, PasswordController $passwords): RedirectResponse
    {
        $user = $this->users()->where('user.id', $id)->first();
        if (! $user) {
            return redirect('/');
        }

        $redirect = redirect('/gestion_promoteurs/modifier/'.$id);
        $email = html_entity_decode((string) $user->email, ENT_QUOTES);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $redirect->with('reset_link_error', 'Aucun lien envoyé : ce compte n\'a pas d\'e-mail valide.');
        }
        if (! $passwords->sendLink($user)) {
            return $redirect->with('reset_link_error', 'L\'e-mail n\'a pas pu être envoyé. Veuillez réessayer.');
        }

        return $redirect->with('reset_link_message', 'Un lien de réinitialisation a été envoyé à '.$email.'. Il est valable une heure.');
    }
}
