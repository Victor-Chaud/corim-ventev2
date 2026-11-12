<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Signature('passwords:wrap-legacy {--apply : Remplace les hash au lieu de seulement les compter}')]
#[Description('Enveloppe dans bcrypt les hash sha256 legacy des comptes qui ne se sont pas encore reconnectés')]
class WrapLegacyPasswords extends Command
{
    public function handle(): int
    {
        $users = DB::table('user')->get(['id', 'password'])->filter(fn (object $u) => User::isRawLegacyHash((string) $u->password));

        if ($this->option('apply')) {
            foreach ($users as $user) {
                DB::table('user')->where('id', $user->id)->update(['password' => User::WRAPPED_PREFIX.Hash::make((string) $user->password)]);
            }
        }

        $this->info(($this->option('apply') ? 'Hash enveloppés : ' : 'Hash legacy à envelopper : ').$users->count());

        return self::SUCCESS;
    }
}
