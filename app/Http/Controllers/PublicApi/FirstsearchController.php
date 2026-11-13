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

    private function programs(Builder $programs, callable $lots): mixed
    {
        $data = [];
        $count = 0;
        foreach ($programs->get() as $program) {
            $data['errors'] = 1;
            $result = $lots($program);
            if ($result !== []) {
                $data['program'][$count] = ['titre' => (string) $program->projet_titre, 'lieu' => (string) $program->projet_lieu, 'result' => $result];
            }
            $count++;
        }

        return $data ?: ['errors' => self::NO_RESULT_HTTP];
    }

    private function programsOf(Builder $lots): Builder
    {
        return $lots->select('projet.projet_titre', 'projet.projet_id', 'projet.projet_lieu')
            ->groupBy('projet.projet_titre', 'projet.projet_id', 'projet.projet_lieu')
            ->orderByRaw('MIN(appartements.prix)');
    }

    private function light(mixed $program): mixed
    {
        $lots = $this->lots()->where('appartements.statut', '')
            ->when((int) $program !== 0, fn (Builder $q) => $q->where('appartements.lot', (int) $program));

        return $this->programs($this->programsOf($lots), fn (object $p) => $this->rows(
            $this->lots()->where('appartements.lot', (int) $p->projet_id)->where('appartements.statut', '')
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }

    private function withref(mixed $program, mixed $ref): mixed
    {
        if ($ref === null) {
            return ['errors' => self::NO_RESULT_HTTP];
        }

        $lots = $this->lots()->where('appartements.numero', $this->str($ref))
            ->when((int) $program !== 0, fn (Builder $q) => $q->where('appartements.lot', (int) $program));

        return $this->programs($this->programsOf($lots), fn (object $p) => $this->rows(
            $this->lots()->where('appartements.lot', (int) $p->projet_id)->where('appartements.numero', $this->str($ref))
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }

    private function app(Request $request): mixed
    {
        $get = $request->query();
        $program = empty($get['projet']) ? '0' : $get['projet'];
        $typology = empty($get['typologie']) ? '0' : $get['typologie'];

        $query = $this->lots(true)->where('appartements.statut', '')->where('appartements.lot', $this->str($program))
            ->when($typology != '0', fn (Builder $q) => $this->typology($q, $typology));
        $rows = $this->rows($this->budget($query, $this->normalize($get['budget'] ?? null))
            ->orderBy('projet.projet_titre')->orderBy('appartements.prix'));

        return $rows === [] ? ['errors' => '<b>Aucun résultat pour cette recherche.</b>'] : ['errors' => 1, 'program' => $rows];
    }

    private function search(Request $request): mixed
    {
        $get = $request->query();
        $geographic = (int) $this->normalize($get['geographique'] ?? null);
        $delivery = $this->normalize($get['livraison'] ?? null);
        $typology = empty($get['typologie']) ? 0 : $get['typologie'];
        $budget = $this->normalize($get['budget'] ?? null);
        $type = $this->normalize($get['type'] ?? null);
        $program = $this->normalize($get['programmes'] ?? null);

        $common = fn (Builder $q) => $this->budget($q
            ->when($geographic === 1, fn (Builder $q) => $q->where('appartements.ville', 'MONTPELLIER'))
            ->when($geographic === 3, fn (Builder $q) => $q->where('appartements.ville', '!=', 'MONTPELLIER'))
            ->when((int) $typology !== 0, fn (Builder $q) => $this->typology($q, $typology)), $budget);
        $dates = fn (Builder $q) => $q->whereIn('appartements.date', array_map(fn ($t) => $t.'T'.$this->str($delivery), [1, 2, 3, 4]));
        $category = fn (Builder $q) => $q->where('appartements.categorie', 'like', '%'.$this->str($type).'%');

        $programs = DB::table('projet')->join('appartements', 'appartements.lot', '=', 'projet.projet_id')
            ->distinct()->select('projet.projet_id', 'projet.projet_titre', 'projet.projet_lieu')
            ->where('appartements.statut', '')
            ->when((int) $type !== 0, $category)
            ->when((int) $delivery !== 0, $dates)
            ->when((int) $program !== 0, fn (Builder $q) => $q->where('projet.projet_id', $this->str($program)))
            ->orderBy('projet.projet_titre');
        $common($programs);

        return $this->programs($programs, fn (object $p) => $this->rows(
            $common($this->lots()->where('appartements.statut', '')->where('appartements.lot', (int) $p->projet_id))
                ->when($type != '0', $category)
                ->when($delivery != '0', $dates)
                ->orderBy('projet.projet_titre')->orderBy('appartements.prix')
        ));
    }
}
