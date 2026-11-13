<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const FAILED = 'Identifiant et/ou mot de passe non reconnu';

    public function index(Request $request): View|RedirectResponse
    {
        $view = view('auth-users-privileges.login', ['title' => 'Corim Promotion - Connexion à votre espace']);

        if (! $request->isMethod('post') || $request->input('action') !== 'connexion') {
            return $view;
        }

        $username = (string) $request->input('identifiant', '');
        $password = (string) $request->input('password', '');

        $messages = [
            'identifiant' => match (true) {
                $username === '' => "L'identifiant ne peut rester vide.",
                ! preg_match('/^[\pL\pN\pZ\p{Pc}\p{Pd}\p{Po}]++$/u', $username) => "L'identifiant ne doit pas contenir de caractères spéciaux.",
                default => '',
            },
            'password' => $password === '' ? 'Le mot de passe ne peut rester vide.' : '',
        ];

        if (implode('', $messages) !== '') {
            return $view->with('messages', $messages);
        }

        $key = 'login:'.mb_strtolower($username).'|'.$request->ip();
        $ipKey = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return $view->with('messages', $messages + ['control' => self::FAILED]);
        }

        $matches = User::query()
            ->where('pseudo', $username)
            ->where('statut', 1)
            ->whereExists(fn ($q) => $q->from('privileges')->whereColumn('privileges.iduser', 'user.id'))
            ->get()
            ->filter(fn (User $user) => $user->checkPassword($password));

        $user = $matches->first();

        if ($matches->count() !== 1 || ! $user instanceof User) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);

            return $view->with('messages', $messages + ['control' => self::FAILED]);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }
}
