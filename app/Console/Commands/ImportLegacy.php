<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('legacy:import')]
#[Description('Copie les données de la base Kohana (connexion legacy) vers la nouvelle base')]
class ImportLegacy extends Command
{
    public const TABLES = [
        'user', 'privileges', 'acces', 'acces_lot', 'projet', 'appartements', 'clients',
        'd_options', 'd_reservations', 'options', 'reservations', 'ventes',
        'prospects', 'd_prospects', 'r_prospects', 'mp', 'emailing', 'news', 'agenda', 'docs', 'villes',
    ];

    public function handle(): int
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
            $columns = array_values(array_intersect(Schema::getColumnListing($table), Schema::connection('legacy')->getColumnListing($table)));
            $count = 0;

            foreach (DB::connection('legacy')->table($table)->select($columns)->cursor()->chunk(500) as $rows) {
                DB::table($table)->insert($rows->map(fn ($row) => (array) $row)->all());
                $count += $rows->count();
            }

            $this->line(sprintf('%-16s %6d', $table, $count));
        }

        Schema::enableForeignKeyConstraints();

        return self::SUCCESS;
    }
}
