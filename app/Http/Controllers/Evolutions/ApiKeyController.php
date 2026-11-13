<?php

namespace App\Http\Controllers\Evolutions;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public static function active(int $userId): ?object
    {
        return DB::table('api_keys')->where('user_id', $userId)->whereNull('revoked_at')->orderByDesc('id')->first();
    }

    public function generate(int $id): RedirectResponse
    {
        $client = $this->client($id);
        $redirect = redirect('/gestion_promoteurs/modifier/'.$id);
        if ((string) $client->acces !== '0') {
            return $redirect->with('api_key_error', 'Aucune clé API ne peut être attribuée à un compte admin.');
        }
        $key = Str::random(48);
        DB::transaction(function () use ($id, $key) {
            $this->revokeAll($id);
            DB::table('api_keys')->insert(['user_id' => $id, 'hash' => hash('sha256', $key), 'created_at' => now()]);
        });

        return $redirect->with('api_key', $key)->with('api_key_message', 'Une nouvelle clé API a été générée. L\'ancien lien ne fonctionne plus.');
    }

    public function revoke(int $id): RedirectResponse
    {
        $this->client($id);
        $this->revokeAll($id);

        return redirect('/gestion_promoteurs/modifier/'.$id)->with('api_key_message', 'La clé API a été révoquée.');
    }

    public function documentation(): View
    {
        return view('evolutions.api_documentation', ['base' => url('/api/v1')]);
    }

    private function revokeAll(int $id): void
    {
        DB::table('api_keys')->where('user_id', $id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function client(int $id): object
    {
        $client = DB::table('user')->join('privileges', 'user.id', '=', 'privileges.iduser')->select('user.*', 'privileges.acces')->where('user.id', $id)->first();
        abort_if($client === null, 404);

        return $client;
    }
}
