<?php

namespace App\Http\Controllers\PublicApi;

use App\Support\LegacyJson;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FirstsearchController extends LegacyApiController
{
    private const CALLBACKS = ['light' => 'MyFirstCallback2', 'email' => 'MyFirstCallback3', 'withref' => 'MyFirstCallback4'];

    public function __invoke(Request $request): Response
    {
        $this->useRawQuery($request);
        $get = $request->query();
        $action = $get['action'] ?? null;

        if ($action === 'nextjscorim') {
            return $this->secure(LegacyJson::respond($this->cached($request, $action, fn () => $this->nextjscorim($request))));
        }

        $data = $this->cached($request, $action, fn () => match (true) {
            $get === [] => ['errors' => self::NO_DATA],
            $action === 'email' => $this->email($get['email'] ?? null, $get['url'] ?? null),
            $action === 'light' => $this->light($get['programmes'] ?? null),
            $action === 'withref' => $this->withref($get['programme'] ?? null, $get['ref'] ?? null),
            $action === 'app' => $this->app($request),
            default => $this->search($request),
        });

        $callback = is_string($action) ? (self::CALLBACKS[$action] ?? 'MyFirstCallback') : 'MyFirstCallback';

        return $this->secure(LegacyJson::respond($data, $callback, 'application/json'));
    }

    private function nextjscorim(Request $request): mixed
    {
        $get = $request->query();
        $filters = [
            'type' => ['appartements.type', 'like'],
            'city' => ['appartements.ville', 'like'],
            'residence' => ['projet.projet_titre', 'like'],
            'minBudget' => ['appartements.prix', '>='],
            'maxBudget' => ['appartements.prix', '<='],
        ];

        $query = $this->lots(true)->where('projet.archive', 0)->where('appartements.statut', '');
        foreach ($filters as $param => [$column, $operator]) {
            if (! empty($get[$param])) {
                $query->where($column, $operator, $this->str($get[$param]));
            }
        }

        return $this->rows($query->orderBy('appartements.prix'));
    }

    private function programs(Builder $programmes, callable $lots): mixed
    {
        $data = [];
        $count = 0;
        foreach ($programmes->get() as $programme) {
            $data['errors'] = 1;
            $result = $lots($programme);
            if ($result !== []) {
                $data['program'][$count] = ['titre' => (string) $programme->projet_titre, 'lieu' => (string) $programme->projet_lieu, 'result' => $result];
            }
            $count++;
        }

        return $data ?: ['errors' => self::NO_RESULT_HTTP];
    }

    private function programmesOf(Builder $lots): Builder
    {
        return $lots->select('projet.projet_titre', 'projet.projet_id', 'projet.projet_lieu')
            ->groupBy('projet.projet_titre', 'projet.projet_id', 'projet.projet_lieu')
            ->orderByRaw('MIN(appartements.prix)');
    }

    private function light(mixed $programme): mixed
    {
        $lots = $this->lots()->where('appartements.statut', '')
            ->when((int) $programme !== 0, fn (Builder $q) => $q->where('appartements.lot', (int) $programme));

        return $this->programs($this->programmesOf($lots), fn (object $p) => $this->rows(
            $this->lots()->where('appartements.lot', (int) $p->projet_id)->where('appartements.statut', '')
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }

    private function withref(mixed $programme, mixed $ref): mixed
    {
        if ($ref === null) {
            return ['errors' => self::NO_RESULT_HTTP];
        }

        $lots = $this->lots()->where('appartements.numero', $this->str($ref))
            ->when((int) $programme !== 0, fn (Builder $q) => $q->where('appartements.lot', (int) $programme));

        return $this->programs($this->programmesOf($lots), fn (object $p) => $this->rows(
            $this->lots()->where('appartements.lot', (int) $p->projet_id)->where('appartements.numero', $this->str($ref))
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }

    private function app(Request $request): mixed
    {
        $get = $request->query();
        $projet = empty($get['projet']) ? '0' : $get['projet'];
        $typologie = empty($get['typologie']) ? '0' : $get['typologie'];

        $query = $this->lots(true)->where('appartements.statut', '')->where('appartements.lot', $this->str($projet))
            ->when($typologie != '0', fn (Builder $q) => $this->typologie($q, $typologie));
        $rows = $this->rows($this->budget($query, $this->normalize($get['budget'] ?? null))
            ->orderBy('projet.projet_titre')->orderBy('appartements.prix'));

        return $rows === [] ? ['errors' => '<b>Aucun résultat pour cette recherche.</b>'] : ['errors' => 1, 'program' => $rows];
    }

    private function search(Request $request): mixed
    {
        $get = $request->query();
        $geographique = (int) $this->normalize($get['geographique'] ?? null);
        $livraison = $this->normalize($get['livraison'] ?? null);
        $typologie = empty($get['typologie']) ? 0 : $get['typologie'];
        $budget = $this->normalize($get['budget'] ?? null);
        $type = $this->normalize($get['type'] ?? null);
        $programme = $this->normalize($get['programmes'] ?? null);

        $common = fn (Builder $q) => $this->budget($q
            ->when($geographique === 1, fn (Builder $q) => $q->where('appartements.ville', 'MONTPELLIER'))
            ->when($geographique === 3, fn (Builder $q) => $q->where('appartements.ville', '!=', 'MONTPELLIER'))
            ->when((int) $typologie !== 0, fn (Builder $q) => $this->typologie($q, $typologie)), $budget);
        $dates = fn (Builder $q) => $q->whereIn('appartements.date', array_map(fn ($t) => $t.'T'.$this->str($livraison), [1, 2, 3, 4]));
        $categorie = fn (Builder $q) => $q->where('appartements.categorie', 'like', '%'.$this->str($type).'%');

        $programmes = DB::table('projet')->join('appartements', 'appartements.lot', '=', 'projet.projet_id')
            ->distinct()->select('projet.projet_id', 'projet.projet_titre', 'projet.projet_lieu')
            ->where('appartements.statut', '')
            ->when((int) $type !== 0, $categorie)
            ->when((int) $livraison !== 0, $dates)
            ->when((int) $programme !== 0, fn (Builder $q) => $q->where('projet.projet_id', $this->str($programme)))
            ->orderBy('projet.projet_titre');
        $common($programmes);

        return $this->programs($programmes, fn (object $p) => $this->rows(
            $common($this->lots()->where('appartements.statut', '')->where('appartements.lot', (int) $p->projet_id))
                ->when($type != '0', $categorie)
                ->when($livraison != '0', $dates)
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }
}
