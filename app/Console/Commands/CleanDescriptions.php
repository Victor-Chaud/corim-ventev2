<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProgramsLots\Description;
use Illuminate\Console\Attributes\Description as CommandDescription;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('programs:clean-descriptions {--apply : Enregistre les descriptions nettoyées au lieu de seulement les lister}')]
#[CommandDescription('Retire les \\ accumulés et transforme les \\n littéraux des descriptions de programmes')]
class CleanDescriptions extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $count = 0;
        foreach (DB::table('projet')->orderBy('projet_id')->get(['projet_id', 'projet_titre', 'projet_description']) as $program) {
            $before = (string) $program->projet_description;
            $after = Description::clean($before);
            if ($after === $before) {
                continue;
            }
            $count++;
            $this->line($program->projet_id.' '.$program->projet_titre);
            $this->line('  avant : '.$before);
            $this->line('  après : '.$after);
            if ($apply) {
                DB::table('projet')->where('projet_id', $program->projet_id)->update(['projet_description' => $after]);
            }
        }
        $this->info($apply ? "$count description(s) nettoyée(s)." : "$count description(s) à nettoyer. Relancer avec --apply pour enregistrer.");

        return self::SUCCESS;
    }
}
