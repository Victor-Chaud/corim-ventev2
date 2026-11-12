<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Hidden(['password', 'question', 'reponse'])]
class User extends Authenticatable
{
    public const WRAPPED_PREFIX = 'legacy:';

    protected $table = 'user';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $rememberTokenName = '';

    public function acces(): string
    {
        return (string) DB::table('privileges')->where('iduser', $this->id)->value('acces');
    }

    public function isAdmin(): bool
    {
        return $this->acces() === '1';
    }

    public function isActive(): bool
    {
        return (int) $this->statut === 1;
    }

    public static function legacyHash(string $password): string
    {
        return hash('sha256', config('auth.legacy_salt').htmlspecialchars($password, ENT_QUOTES));
    }

    public function checkPassword(string $password): bool
    {
        if (self::isRawLegacyHash((string) $this->password)) {
            if (! hash_equals($this->password, self::legacyHash($password))) {
                return false;
            }
            $this->forceFill(['password' => Hash::make($password)])->save();

            return true;
        }

        if (! str_starts_with((string) $this->password, self::WRAPPED_PREFIX)) {
            return Hash::check($password, $this->password);
        }
        if (! Hash::check(self::legacyHash($password), substr($this->password, strlen(self::WRAPPED_PREFIX)))) {
            return false;
        }
        $this->forceFill(['password' => Hash::make($password)])->save();

        return true;
    }

    public static function isRawLegacyHash(string $hash): bool
    {
        return strlen($hash) === 64 && ctype_xdigit($hash);
    }
}
