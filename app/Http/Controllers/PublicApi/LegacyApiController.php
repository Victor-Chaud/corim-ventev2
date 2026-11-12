<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

abstract class LegacyApiController extends Controller
{
    protected const NO_RESULT_HTTP = '<b>Aucun résultat pour cette recherche.</b><br /><br />Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href="http://corim-promotion.com/contactez-nous/"><b>ici</b></a>';

    protected const NO_RESULT_HTTPS = '<b>Aucun résultat pour cette recherche.</b><br /><br />Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href="https://corim-promotion.com/contactez-nous/"><b>ici</b></a>';

    protected const NO_DATA = 'Aucunes données envoyées';

    protected const BUDGETS = [
        1 => [null, '150 000'],
        2 => ['150 000', '200 000'],
        3 => ['200 000', '250 000'],
        4 => ['250 000', '300 000'],
        5 => ['300 000', null],
    ];

    protected function useRawQuery(Request $request): void
    {
        parse_str((string) $request->server('QUERY_STRING'), $get);
        $request->query->replace($get);
    }

    protected function cached(Request $request, mixed $action, Closure $data): mixed
    {
        if ($action === 'email') {
            return $data();
        }

        return Cache::remember('public-api:'.sha1($request->path().'?'.$request->server('QUERY_STRING')), 300, fn (): mixed => $data());
    }

    protected function secure(Response $response): Response
    {
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");

        return $response;
    }

    protected function str(mixed $value): string
    {
        return is_array($value) ? 'Array' : (string) $value;
    }

    protected function normalize(mixed $value): mixed
    {
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES) : $value;
    }

    protected function lots(bool $withKey = false): Builder
    {
        return DB::table('appartements')
            ->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->select(array_filter(['appartements.*', 'projet.projet_titre', $withKey ? 'projet.projet_titre_key' : null]));
    }

    protected function budget(Builder $query, mixed $budget): Builder
    {
        [$min, $max] = self::BUDGETS[(int) $budget] ?? [null, null];

        return $query
            ->when($min !== null, fn (Builder $q) => $q->where('appartements.prix', '>', $min))
            ->when($max !== null, fn (Builder $q) => $q->where('appartements.prix', '<=', $max));
    }

    protected function typologie(Builder $query, mixed $typologie): Builder
    {
        $list = is_array($typologie) ? array_map($this->str(...), $typologie) : [''];

        return $query->whereIn('appartements.type', $list);
    }

    protected function rows(Builder $query): mixed
    {
        return $query->get()->map(fn ($row) => array_map(fn ($v) => $v === null ? null : (string) $v, (array) $row))->values()->all();
    }

    protected function email(mixed $email, mixed $url): mixed
    {
        if (strpbrk((string) json_encode($url), '<>') !== false) {
            return ['errors' => "Votre adresse email n'est pas valide", 'data' => null];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['errors' => "Votre adresse email n'est pas valide", 'data' => $url];
        }

        if (! RateLimiter::attempt('public-api-docs:'.request()->ip(), 10, fn () => true)) {
            return ['errors' => 'Database error', 'data' => $url];
        }

        $inserted = DB::table('docs')->insert(['email' => $this->str($email), 'url' => $this->str($url), 'time' => (string) time()]);

        return ['errors' => $inserted ? 1 : 'Database error', 'data' => $url];
    }
}
