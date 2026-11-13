<?php

namespace App\Http\Controllers\Evolutions;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class AutoMessagesController extends Controller
{
    public const MESSAGES = [
        'denonciation_validee' => 'Dénonciation validée',
        'denonciation_refusee' => 'Dénonciation refusée',
        'option_validee' => 'Option validée',
        'option_refusee' => 'Option refusée',
        'reservation_validee' => 'Réservation validée',
        'reservation_refusee' => 'Réservation refusée',
        'option_annulee' => 'Option annulée',
        'reservation_annulee' => 'Réservation annulée',
        'vente_annulee' => 'Vente annulée',
    ];

    public const SIGNATURE = "Immeuble \"Le Cristal\"\n1475 av.Albert Einstein\n34000  MONTPELLIER\nTél : 04 99 58 86 86\nMob : 06 12 36 06 94";

    public static function text(string $key): string
    {
        $text = DB::table('messages_auto')->where('cle', $key)->value('texte');

        return $text !== null ? (string) $text : ($key === 'signature' ? self::SIGNATURE : '');
    }

    public static function html(string $text): HtmlString
    {
        return new HtmlString(nl2br(e(str_replace("\r\n", "\n", $text)), false));
    }

    public function index(Request $request): View|RedirectResponse
    {
        $fields = self::MESSAGES + ['signature' => 'Signature des mails au prescripteur'];
        $success = null;
        if ($request->isMethod('post')) {
            $validator = Validator::make(
                $request->all(),
                array_fill_keys(array_keys($fields), ['nullable', 'string', 'max:5000']),
                ['string' => 'Le texte « :attribute » est invalide.', 'max' => 'Le texte « :attribute » ne doit pas dépasser :max caractères.'],
                $fields,
            );
            if ($validator->fails()) {
                return redirect('/messages_automatiques')->withErrors($validator)->withInput();
            }
            $values = $validator->validated();
            foreach (array_keys($fields) as $key) {
                DB::table('messages_auto')->updateOrInsert(['cle' => $key], ['texte' => (string) ($values[$key] ?? ''), 'updated_at' => now()]);
            }
            $success = 'Les messages automatiques ont été enregistrés.';
        }

        return view('evolutions.auto_messages', [
            'title' => 'Corim promotion - Messages automatiques',
            'fields' => $fields,
            'texts' => array_map(fn (string $key) => self::text($key), array_combine(array_keys($fields), array_keys($fields))),
            'success' => $success,
        ]);
    }
}
