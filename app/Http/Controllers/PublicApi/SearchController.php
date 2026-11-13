<?php

namespace App\Http\Controllers\PublicApi;

use App\Support\LegacyJson;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SearchController extends LegacyApiController
{
    private const CALLBACKS = ['light' => 'MyCallback2', 'email' => 'MyCallback3', 'withref' => 'MyFirstCallback4', 'allnews' => 'MyFirstCallbackNews'];

    public function news(Request $request): Response
    {
        $this->useRawQuery($request);
        $get = $request->query();
        $action = $get['action'] ?? null;

        $data = match (true) {
            $get === [] => ['errors' => self::NO_DATA],
            $action === 'allnews' => $this->allnews(),
            default => null,
        };

        return $this->respond($data, $action);
    }

    public function index(Request $request): Response
    {
        $this->useRawQuery($request);
        $get = $request->query();
        $action = $get['action'] ?? null;

        $data = $this->cached($request, $action, fn () => match (true) {
            $get === [] => ['errors' => self::NO_DATA],
            $action === 'email' => $this->email($get['email'] ?? null, $get['url'] ?? null),
            $action === 'light' => $this->found($this->lots()->where('appartements.statut', '')->orderBy('appartements.prix'), self::NO_RESULT_HTTPS),
            $action === 'withref' => $this->withref($get['programmes'] ?? null, $get['ref'] ?? null),
            default => $this->search($request),
        });

        return $this->respond($data, $action);
    }

    private function respond(mixed $data, mixed $action): Response
    {
        $callback = is_string($action) ? (self::CALLBACKS[$action] ?? 'MyCallback') : 'MyCallback';

        return $this->secure(LegacyJson::respond($data, $callback));
    }

    private function found(Builder $query, string $error): mixed
    {
        $rows = $this->rows($query);

        return $rows === [] ? ['errors' => $error] : ['errors' => 1, 'data' => $rows];
    }

    private function allnews(): mixed
    {
        $news = DB::table('news')->orderByDesc('date')->get()->map(fn (object $row) => array_map(
            fn (string $key) => htmlspecialchars_decode((string) $row->{$key}),
            array_combine($keys = ['id', 'string1', 'string2', 'string3', 'string4', 'url', 'thumb'], $keys)
        ))->all();

        return $news === [] ? ['errors' => '<b>Aucun flash info actuellement.</b>'] : ['errors' => 1, 'data' => $news];
    }

    private function withref(mixed $program, mixed $ref): mixed
    {
        if ($ref === null) {
            return ['errors' => self::NO_RESULT_HTTP];
        }

        return $this->found($this->lots()->where('appartements.numero', $this->str($ref))
            ->when((int) $program !== 0, fn (Builder $q) => $q->where('appartements.lot', (int) $program))
            ->orderBy('projet.projet_titre')->orderBy('appartements.prix'), self::NO_RESULT_HTTP);
    }

    private function search(Request $request): mixed
    {
        $get = $request->query();
        $geographic = (int) $this->normalize($get['geographique'] ?? null);
        $delivery = $this->normalize($get['livraison'] ?? null);
        $typology = empty($get['typologie']) ? '0' : $get['typologie'];
        $type = $this->normalize($get['type'] ?? null);

        $query = $this->lots()->where('appartements.statut', '')
            ->when($geographic === 1, fn (Builder $q) => $q->where('appartements.ville', 'MONTPELLIER'))
            ->when($geographic === 3, fn (Builder $q) => $q->where('appartements.ville', '!=', 'MONTPELLIER'))
            ->when($typology != '0', fn (Builder $q) => $this->typology($q, $typology))
            ->when($type != '0', fn (Builder $q) => $q->where('appartements.categorie', 'like', '%'.$this->str($type).'%'))
            ->when($delivery != '0', fn (Builder $q) => $q->where('appartements.date', $this->str($delivery)));

        return $this->found($this->budget($query, $this->normalize($get['budget'] ?? null))
            ->orderBy('projet.projet_titre')->orderBy('appartements.prix'), self::NO_RESULT_HTTPS);
    }
}
