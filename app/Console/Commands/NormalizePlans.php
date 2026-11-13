<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProgramsLots\LotImport;
use App\Http\Controllers\ProgramsLots\ProgramFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('plans:normalize {--apply : Renomme les fichiers au lieu de seulement les lister}')]
#[Description('Renomme en plan-{référence}.pdf les plans mal nommés qui correspondent à un lot du programme')]
class NormalizePlans extends Command
{
    public function handle(): int
    {
        $disk = ProgramFiles::disk();
        $apply = (bool) $this->option('apply');
        $count = 0;

        foreach (DB::table('projet')->orderBy('projet_id')->get(['projet_id', 'projet_titre_key']) as $program) {
            $key = (string) $program->projet_titre_key;
            if (! ProgramFiles::safeName($key) || ! $disk->directoryExists($key.'/plans')) {
                continue;
            }
            $lots = DB::table('appartements')->where('lot', (string) $program->projet_id)->get(['id', 'numero']);
            $expected = $lots->map(fn ($lot) => ProgramFiles::planName((string) $lot->numero))->all();
            foreach (ProgramFiles::files($key, 'plans') as $file) {
                if (in_array($file, $expected, true) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
                    continue;
                }
                $lot = LotImport::lotForPlan($file, $lots);
                if ($lot === null) {
                    $this->line("$key/plans/$file : aucun lot ".LotImport::planReference($file), null, 'v');

                    continue;
                }
                $target = ProgramFiles::planName((string) $lot->numero);
                if (! ProgramFiles::safeName($target) || $disk->fileExists($key.'/plans/'.$target)) {
                    $this->line("$key/plans/$file : $target existe déjà, ignoré");

                    continue;
                }
                $this->line("$key/plans/$file -> $target");
                $count++;
                if ($apply) {
                    $disk->move($key.'/plans/'.$file, $key.'/plans/'.$target);
                }
            }
        }

        $this->info($apply ? "$count plan(s) renommé(s)." : "$count plan(s) à renommer. Relancer avec --apply pour renommer.");

        return self::SUCCESS;
    }
}
