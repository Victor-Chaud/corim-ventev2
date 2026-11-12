<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('C22 clients et prospects du snapshot sont repris à l\'identique', function () {
    $tables = ['clients' => 'idclient', 'prospects' => 'idprospect', 'd_prospects' => 'idprospect', 'r_prospects' => 'idprospect'];
    loadLegacy(...array_keys($tables));

    foreach ($tables as $table => $key) {
        $legacy = DB::connection('legacy')->table($table)->orderBy($key)->get()->map(fn ($row) => array_map('strval', (array) $row))->all();
        $new = DB::table($table)->select(Schema::connection('legacy')->getColumnListing($table))->orderBy($key)->get()->map(fn ($row) => array_map('strval', (array) $row))->all();
        expect(count($new))->toBe(count($legacy), $table)
            ->and(md5(json_encode($new)))->toBe(md5(json_encode($legacy)), $table);
    }
    expect(DB::table('clients')->count())->toBe(212)
        ->and(DB::table('prospects')->count())->toBeGreaterThanOrEqual(6668)
        ->and(DB::table('r_prospects')->count())->toBeGreaterThanOrEqual(1949);
});
