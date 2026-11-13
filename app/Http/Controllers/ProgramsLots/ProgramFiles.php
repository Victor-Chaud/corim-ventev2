<?php

namespace App\Http\Controllers\ProgramsLots;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProgramFiles
{
    public const DIRS = ['thumb', 'diapo', 'documents', 'plans', 'visuels'];

    private const MIMES = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/plain', 'text/csv', 'text/x-csv', 'application/csv', 'application/vnd.ms-excel', 'application/x-empty', 'inode/x-empty'],
    ];

    public const UPLOAD_TYPES = [
        'visuels' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'],
        'plans' => ['pdf'],
    ];

    public static function disk(): Filesystem
    {
        return Storage::disk('programmes');
    }

    public static function program(string $key): object
    {
        $program = DB::table('projet')->where('projet_titre_key', $key)->first();
        abort_if($program === null || ! self::safeName($key), 404);

        return $program;
    }

    public static function safeName(string $name): bool
    {
        return $name !== '' && $name !== '.' && $name !== '..' && ! preg_match('#[/\\\\\x00]#', $name);
    }

    public static function accepts(?UploadedFile $file, array $extensions, ?int $maxKb = null): bool
    {
        if ($file === null || ! $file->isValid() || ! self::safeName($file->getClientOriginalName())) {
            return false;
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $extensions, true) || ($maxKb !== null && $file->getSize() > $maxKb * 1024)) {
            return false;
        }

        return in_array(strtolower((string) $file->getMimeType()), self::MIMES[$extension] ?? [], true);
    }

    public static function url(string $key, string $dir, string $file): string
    {
        return '/medias/programmes/'.$key.'/'.$dir.'/'.$file;
    }

    public static function files(string $key, string $dir): array
    {
        return array_map('basename', self::disk()->files($key.'/'.$dir));
    }

    public static function thumb(string $key): string
    {
        return implode('', array_map(fn ($file) => self::url($key, 'thumb', $file), self::files($key, 'thumb')));
    }

    public static function planName(string $number): string
    {
        return 'plan-'.str_replace(' ', '', $number).'.pdf';
    }

    public static function makeDirectories(string $key): void
    {
        foreach (self::DIRS as $dir) {
            self::disk()->makeDirectory($key.'/'.$dir);
        }
    }

    public static function saveThumb(UploadedFile $file, string $key): bool
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            return false;
        }
        $height = max(1, (int) round(imagesy($source) * 120 / imagesx($source)));
        $image = imagecreatetruecolor(120, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, 120, $height, imagesx($source), imagesy($source));

        $extension = strtolower($file->getClientOriginalExtension());
        ob_start();
        match (strtolower($extension)) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, 95),
        };

        return self::disk()->put($key.'/thumb/thumb.'.$extension, (string) ob_get_clean());
    }

    public static function deleteFromUrl(string $url, array $dirs): bool
    {
        if (! preg_match('#^/+medias/programmes/([^/\\\\]+)/([^/\\\\]+)/([^/\\\\]+)$#', $url, $parts)) {
            return false;
        }
        [, $key, $dir, $file] = $parts;
        if (! in_array($dir, $dirs, true) || ! self::safeName($key) || ! self::safeName($file)) {
            return false;
        }
        if (! DB::table('projet')->where('projet_titre_key', $key)->exists()) {
            return false;
        }
        $path = $key.'/'.$dir.'/'.$file;

        return self::disk()->fileExists($path) && self::disk()->delete($path);
    }
}
