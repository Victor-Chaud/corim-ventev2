<?php

use App\Http\Middleware\LogoutInactive;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('medias/{dir}/{path}', function (string $dir, string $path) {
    if (in_array('..', explode('/', str_replace('\\', '/', $path)), true)) {
        abort(404);
    }

    $disk = Storage::disk($dir);
    $base = realpath($disk->path(''));
    $file = realpath($disk->path($path));

    abort_unless($base && $file && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file), 404);

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $attachments = [
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    if (isset($attachments[$extension])) {
        return response()->download($file, basename($file), ['Content-Type' => $attachments[$extension]]);
    }

    abort_unless(in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif'], true), 404);

    return response()->file($file);
})->where(['dir' => 'programmes|upload|news', 'path' => '.*'])
    ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, LogoutInactive::class]);
