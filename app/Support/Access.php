<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Access
{
    public static function canSeeProgramme(User $user, int $projetId): bool
    {
        return $user->isAdmin() || DB::table('acces')->where('iduser', $user->id)->where('idprojet', $projetId)->exists();
    }

    public static function canSeeLot(User $user, int $lotId): bool
    {
        return $user->isAdmin() || DB::table('acces_lot')->where('iduser', $user->id)->where('idlot', $lotId)->exists();
    }
}
