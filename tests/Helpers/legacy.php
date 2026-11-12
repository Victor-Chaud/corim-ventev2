<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mime\Email;

const CORIM_BASE = 'https://corim-vente.com/';

const CORIM_TO = 'contact@corim-promotion.com';

const CORIM_SIGNATURE = 'Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Mob : 06 12 36 06 94';

function legacyMailBase(): void
{
    config(['app.url' => rtrim(CORIM_BASE, '/')]);
}

function mailNotification(string $phrase): string
{
    return '<table width="500"><tr><td align="left"><img src="'.CORIM_BASE.'medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>'.$phrase.'<br /><br />Pour y accéder, vous pouvez cliquer <a href="'.CORIM_BASE.'messagerie">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">'.CORIM_SIGNATURE.'</td></tr></table>';
}

function mailPrescripteur(string $phrase, string $sms, string $titre, string $ligne2): string
{
    $font = 'font-family:Arial,Helvetica,sans-serif;font-size:14px';
    $signature = nl2br(htmlspecialchars(str_replace('<br />', "\n", CORIM_SIGNATURE), ENT_QUOTES), false);

    return '<table width="500" style="'.$font.';line-height:1.5;color:#333333"><tr><td align="left"><img src="'.CORIM_BASE.'medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td style="'.$font.'">Bonjour,<br /><br />'.$phrase.'<br /><br />'.nl2br(htmlspecialchars($sms, ENT_QUOTES), false).'</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td style="'.$font.'">Programme concerné : '.$titre.'</td></tr><tr><td style="'.$font.'">'.$ligne2.'</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left" style="'.$font.'">'.$signature.'</td></tr></table>';
}

function expectLegacyMail(Email $mail, string $to, string $subject, string $body): void
{
    $html = trim((string) $mail->getHtmlBody());
    expect($mail->getSubject())->toBe($subject)
        ->and(array_map(fn ($address) => $address->getAddress(), $mail->getTo()))->toBe([$to])
        ->and($mail->getFrom()[0]->getName())->toBe('Corim promotion')
        ->and($mail->getFrom()[0]->getAddress())->toBe((string) config('mail.from.address'))
        ->and($html)->toBe($body)
        ->and(trim((string) $mail->getTextBody()))->toBe(trim(strip_tags($body)));
}

function pageText(TestResponse $response): string
{
    return html_entity_decode((string) $response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function expectInOrder(string $haystack, array $needles): void
{
    $offset = 0;
    foreach ($needles as $needle) {
        $position = mb_strpos($haystack, (string) $needle, $offset);
        expect($position)->not->toBeFalse("« $needle » absent ou hors ordre");
        $offset = (int) $position + mb_strlen((string) $needle);
    }
}

function tablesState(array $tables): string
{
    return md5(json_encode(array_map(fn ($table) => DB::table($table)->get()->all(), $tables)));
}

function decoded(?string $value): string
{
    return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function jsonAction(?User $user, string $action, array $data = [])
{
    app('auth')->forgetGuards();
    $test = $user ? test()->actingAs($user) : test();

    return $test->post("/index.php/json/$action/", $data);
}

function expectRouteExists(TestResponse $response, string $label): void
{
    expect($response->getStatusCode())->not->toBeIn([404, 405], "$label : route absente");
}

function corimAdmin(): User
{
    return User::find(1) ?? makeAdmin(['id' => 1, 'pseudo' => 'corim', 'name' => 'CORIM Admin', 'email' => 'corim@exemple.test']);
}
