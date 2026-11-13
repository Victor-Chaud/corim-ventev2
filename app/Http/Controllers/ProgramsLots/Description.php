<?php

namespace App\Http\Controllers\ProgramsLots;

use Illuminate\Support\HtmlString;

class Description
{
    public static function clean(string $text): string
    {
        return (string) preg_replace(
            ['/\\\\+r\\\\+n/', '/\\\\+n/', '/\\\\+r/', '/\\\\+([\'"]|&#039;|&quot;)/', '/\\\\{2,}/'],
            ["\n", "\n", '', '$1', ''],
            $text,
        );
    }

    public static function html(string $stored): HtmlString
    {
        $parts = preg_split('#(https?://[^\s<>"\']*[^\s<>"\'.,;:!?)])#i', html_entity_decode($stored, ENT_QUOTES), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $html = '';
        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 1 ? '<a href="'.e($part).'" target="_blank" rel="noopener">'.e($part).'</a>' : e($part);
        }

        return new HtmlString(nl2br($html));
    }
}
