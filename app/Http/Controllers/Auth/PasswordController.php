<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Auth\ResetLinkMail;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

class PasswordController extends Controller
{
    private const UNKNOWN = "Votre email n'est pas reconnu.";

    public function checkIfExist(Request $request): Response
    {
        if (! $request->has(['action', 'email'])) {
            return response('');
        }

        $request->session()->forget('reset_user_id');
        $request->session()->put('reset_email', $this->normalize($request->input('email')));

        return LegacyJson::respond(['errors' => 0, 'email' => (string) $request->input('email'), 'response' => 'Merci de confirmer votre demande de réinitialisation.']);
    }

    public function checkQuestion(Request $request): Response
    {
        if (! $request->has(['action', 'email'])) {
            return response('');
        }

        $users = DB::table('user')->where('email', $this->normalize($request->input('email')))->get(['question', 'email']);

        if ($users->count() !== 1) {
            return LegacyJson::respond(['errors' => 1, 'response' => self::UNKNOWN]);
        }

        return LegacyJson::respond(['errors' => 0, 'response' => (string) $users[0]->question, 'email' => (string) $users[0]->email]);
    }

    public function checkAnswer(Request $request): Response
    {
        if (! $request->has(['your_response', 'your_email'])) {
            return response('');
        }

        $reponse = $this->normalize($request->input('your_response'));
        $users = $reponse === '' ? collect() : DB::table('user')
            ->where('email', $this->normalize($request->input('your_email')))
            ->where('reponse', $reponse)
            ->pluck('id');

        if ($users->count() !== 1) {
            return LegacyJson::respond(['errors' => 1, 'response' => 'La réponse à votre question secrète est incorrect.']);
        }

        $request->session()->put('reset_user_id', (int) $users->first());

        return LegacyJson::respond(['errors' => 0, 'id' => (string) $users->first(), 'pwd' => '']);
    }

    public function newAccountSetting(Request $request): Response
    {
        if (! $request->has(['idClient', 'pwdClient'])) {
            return response('');
        }

        $userId = $request->session()->pull('reset_user_id');
        $email = $request->session()->pull('reset_email');

        if ($userId === null && $email === null) {
            return LegacyJson::respond(['errors' => 1, 'response' => "Un problème est survenu lors du contrôle de votre compte, veuillez contacter l'administrateur du site."]);
        }

        $users = DB::table('user')->when($userId !== null, fn ($q) => $q->where('id', (int) $userId), fn ($q) => $q->where('email', (string) $email))->get();
        $user = $users->count() === 1 ? $users[0] : null;

        if ($user) {
            RateLimiter::attempt('password-reset:'.$user->id, 1, fn () => $this->sendLink($user), 600);
        }

        return LegacyJson::respond(['errors' => 0, 'response' => "Si l'adresse ".$email.' correspond à un compte, un lien pour choisir votre nouveau mot de passe vient de lui être envoyé. Ce lien est valable une heure.']);
    }

    public function reset(Request $request, string $id, string $empreinte): View|Response
    {
        $view = view('auth-users-privileges.reinitialisation', ['title' => 'Corim Promotion - Connexion à votre espace']);
        $user = $request->hasValidRelativeSignature() ? DB::table('user')->where('id', (int) $id)->first() : null;

        if (! $user || ! hash_equals($this->empreinte($user), $empreinte)) {
            return response($view->with('invalide', true), 403);
        }

        if (! $request->isMethod('post')) {
            return $view;
        }

        $password = (string) $request->input('password', '');
        $message = match (true) {
            $password === '' => 'Le mot de passe ne peut rester vide.',
            mb_strlen($password) < 8 => 'Le mot de passe doit contenir au moins 8 caractères.',
            $password !== (string) $request->input('password_confirmation', '') => 'Les deux mots de passe ne sont pas identiques.',
            default => '',
        };

        if ($message !== '') {
            return $view->with('message', $message);
        }

        DB::table('user')->where('id', $user->id)->update(['password' => Hash::make($password)]);

        return $view->with('termine', true);
    }

    private function sendLink(object $user): void
    {
        $lien = rtrim((string) config('app.url'), '/').URL::temporarySignedRoute(
            'password.reinitialisation',
            now()->addHour(),
            ['id' => $user->id, 'empreinte' => $this->empreinte($user)],
            false,
        );

        try {
            Mail::to((string) $user->email)->send(new ResetLinkMail(html_entity_decode((string) $user->pseudo, ENT_QUOTES), $lien));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function empreinte(object $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->password, (string) config('app.key'));
    }

    private function normalize(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES);
    }
}
