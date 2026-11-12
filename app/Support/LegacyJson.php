<?php

namespace App\Support;

use Illuminate\Http\Response;

class LegacyJson
{
    public static function respond(mixed $data, ?string $callback = null, string $contentType = 'text/html; charset=UTF-8'): Response
    {
        $json = json_encode($data);

        if ($callback !== null && preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]{0,63}$/', $callback)) {
            $json = $callback.'('.$json.');';
        }

        return response((string) $json, 200, ['Content-Type' => $contentType]);
    }
}
