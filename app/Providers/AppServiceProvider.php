<?php

namespace App\Providers;

use App\Http\Controllers\Evolutions\PrescripteurApiController;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const ADMIN_HIDDEN_SUBJECTS = [
        'Votre demande de réservation a été validée',
        'Vous avez une demande de réservation en attente',
        'Votre réservation a été annulée',
        'Votre option a été annulée',
        'Vous avez une dénonciation en attente',
        'Votre demande de dénonciation a été validée',
        'Votre demande de dénonciation a été refusée',
        "Votre demande d'option a été validée",
        "Votre demande d'option a été refusée",
        "Vous avez une demande d'option en attente",
        'Votre option a été passée en réservation',
        'Votre dossier a été acté',
    ];

    public function register(): void {}

    public function boot(): void
    {
        URL::forceRootUrl((string) config('app.url'));

        RateLimiter::for('emailing', fn () => Limit::perMinute((int) config('mail.emailing_per_minute')));

        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/^\s*(?:insert|update|delete)\b[^`]*`(?:'.implode('|', PrescripteurApiController::TABLES).')`/i', $query->sql)) {
                $query->connection->afterCommit(fn () => PrescripteurApiController::flush());
            }
        });

        if (! $this->app->isProduction() && ! in_array(config('mail.default'), ['log', 'array'], true)) {
            config(['mail.default' => 'log']);
        }

        View::composer('layouts.*', function ($view) {
            $user = auth()->user();
            $acces = $user instanceof User ? $user->acces() : '';

            $view->with([
                'segment1' => (string) request()->segment(1),
                'sessionAcces' => $acces,
                'toolbarActif' => session('SessionToolbar') === '1',
                'messageCount' => $user instanceof User ? self::messageCount($user->id, $acces === '1') : 0,
            ]);
        });
    }

    public static function messageCount(int $userId, bool $admin): int
    {
        $query = DB::table('mp')->where('idreceveur', $userId)->where('lu', 0);

        if (! $admin) {
            return $query->count();
        }

        return $query->join('user', 'mp.idemetteur', '=', 'user.id')
            ->whereNotIn('mp.sujet', self::ADMIN_HIDDEN_SUBJECTS)
            ->count('mp.sujet');
    }
}
