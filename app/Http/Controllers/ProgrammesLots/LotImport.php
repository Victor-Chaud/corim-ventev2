<?php

namespace App\Http\Controllers\ProgrammesLots;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LotImport
{
    public const HEADERS = [
        'Référence' => 'numero', 'Type' => 'type', 'Catégorie' => 'categorie', 'Prix' => 'prix', 'Prix m²' => 'prix_m2',
        'Étage' => 'etage', 'RDC' => 'RDC', 'Jardin' => 'jardin', 'Terrasse' => 'terrasse', 'Superficie' => 'superficie',
        'Stationnement' => 'stationnement', 'Exposition' => 'exposition', 'Description' => 'description', 'Plan' => 'plan',
        'Cellier' => 'cellier', 'Accession' => 'accession', 'Date de livraison' => 'date',
    ];

    public const POSITIONS = [
        'date', 'type', 'categorie', 'prix', 'prix_m2', 'etage', 'RDC', 'jardin', 'terrasse', 'superficie',
        'stationnement', 'exposition', 'description', 'plan', 'numero', 'cellier', 'accession',
    ];

    private const ALIASES = ['numero' => 'numero', 'plandarchitecte' => 'plan', 'livraison' => 'date'];

    public static function key(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(html_entity_decode($value, ENT_QUOTES))));
    }

    public static function template(): string
    {
        return "\xEF\xBB\xBF".implode(';', array_keys(self::HEADERS))."\r\n";
    }

    public static function rows(string $content): array
    {
        $rows = LotsController::csvRows($content);
        $map = self::header($rows[0] ?? []);
        if ($map === null) {
            $map = self::POSITIONS;
        } else {
            array_shift($rows);
        }

        return array_map(function (array $row) use ($map) {
            $values = [];
            foreach ($map as $index => $field) {
                $values[$field] = (string) ($row[$index] ?? '');
            }

            return $values;
        }, $rows);
    }

    public static function planMap(string $content): array
    {
        $map = [];
        foreach (self::rows($content) as $row) {
            $plan = self::key(pathinfo($row['plan'] ?? '', PATHINFO_FILENAME));
            if ($plan !== '' && ($row['numero'] ?? '') !== '') {
                $map[$plan] ??= $row['numero'];
            }
        }

        return $map;
    }

    public static function lotForPlan(string $fileName, Collection $lots, array $planMap = []): ?object
    {
        $base = self::key(pathinfo($fileName, PATHINFO_FILENAME));
        $candidates = array_filter([
            isset($planMap[$base]) ? self::key((string) $planMap[$base]) : '',
            $base,
            (string) preg_replace('/^plan/', '', $base),
        ]);
        foreach ($candidates as $candidate) {
            $lot = $lots->first(fn ($lot) => self::key((string) $lot->numero) === $candidate);
            if ($lot !== null) {
                return $lot;
            }
        }

        return null;
    }

    public static function planReference(string $fileName): string
    {
        return (string) preg_replace('/^plan[\s\p{P}]*/iu', '', pathinfo($fileName, PATHINFO_FILENAME));
    }

    private static function header(array $row): ?array
    {
        $known = [];
        foreach (self::HEADERS as $label => $field) {
            $known[self::key($label)] = $field;
        }
        $known += self::ALIASES;
        $map = [];
        foreach ($row as $index => $cell) {
            $field = $known[self::key((string) $cell)] ?? null;
            if ($field !== null && ! in_array($field, $map, true)) {
                $map[$index] = $field;
            }
        }

        return in_array('numero', $map, true) && count($map) >= 3 ? $map : null;
    }
}
