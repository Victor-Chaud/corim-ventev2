<?php

use Illuminate\Support\Facades\DB;

const CORIM_FAX_SIGNATURE = 'Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77';

const COMMUNICATION_TABLES = ['mp', 'emailing', 'news', 'agenda', 'docs', 'clients'];

function makeMessage(int $from, int $to, array $attributes = []): object
{
    static $n = 0;
    $n++;
    $id = DB::table('mp')->insertGetId(array_merge([
        'idemetteur' => $from,
        'idreceveur' => $to,
        'sujet' => "Sujet test $n",
        'message' => "Corps du message $n",
        'date' => '2026-03-04 09:15:00',
        'lu' => 0,
        'cle' => sha1("cle-test-$n"),
        'parent' => 0,
    ], $attributes));

    return DB::table('mp')->find($id);
}

function makeNews(array $attributes = []): object
{
    static $n = 0;
    $n++;
    $id = DB::table('news')->insertGetId(array_merge([
        'string1' => "Flash test $n",
        'string2' => "Sous-titre $n",
        'string3' => "<p>Info $n</p>",
        'string4' => "<p>Footer $n</p>",
        'thumb' => '19',
        'date' => 1767225600 + $n,
        'url' => "https://exemple.test/flash-$n",
    ], $attributes));

    return DB::table('news')->find($id);
}

function communicationState(): string
{
    return tablesState(COMMUNICATION_TABLES);
}

function contactMailBody(): string
{
    return '<table width="500"><tr><td align="left"><img src="'.CORIM_BASE.'medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Vous avez reçu un nouveau message<br /><br />Pour y accéder, vous pouvez cliquer <a href="'.CORIM_BASE.'messagerie">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">'.CORIM_FAX_SIGNATURE.'</td></tr></table>';
}

function compactHtml(string $html): string
{
    return preg_replace('/>\s+</', '><', trim($html));
}

function prescriberIds(): array
{
    return DB::table('user')->join('privileges', 'privileges.iduser', '=', 'user.id')
        ->where('privileges.acces', 0)->orderBy('user.id')->pluck('user.id')->map(fn ($id) => (int) $id)->all();
}
