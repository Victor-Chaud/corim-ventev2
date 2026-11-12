<?php

it('C28 le schéma reprend les colonnes legacy sans pwdshow et accepte les données telles quelles', function () {
    $legacy = fn (string $table) => array_column(DB::connection('legacy')->select("SHOW COLUMNS FROM `$table`"), 'Field');

    expect(Schema::hasColumn('user', 'pwdshow'))->toBeFalse()
        ->and(Schema::getColumnListing('user'))->toBe(array_values(array_diff($legacy('user'), ['pwdshow'])));
    foreach (['privileges', 'acces', 'acces_lot'] as $table) {
        expect(Schema::getColumnListing($table))->toBe($legacy($table), $table);
    }

    DB::table('acces')->insert(['iduser' => 999999, 'idprojet' => 999999]);
    DB::table('acces_lot')->insert(['iduser' => 999999, 'idlot' => 999999]);
    DB::table('privileges')->insert(['iduser' => 999999, 'acces' => 0]);
    expect(DB::table('acces')->where('iduser', 999999)->count())->toBe(1);

    $this->actingAs(makeAdmin())->get('/gestion_privilege')->assertOk();
});
