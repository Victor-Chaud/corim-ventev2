<?php

namespace App\Mail\Workflow;

use App\Http\Controllers\Evolutions\MessagesAutoController;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\HtmlString;

class WorkflowMail extends Mailable
{
    public function __construct(public string $sujet, public string $corps) {}

    public static function corim(string $sujet, string $phrase): self
    {
        return new self($sujet, view('workflow.mail.corim', [
            'base' => self::base(),
            'phrase' => new HtmlString($phrase),
        ])->render());
    }

    public static function prescripteur(string $sujet, string $sms, string $projet, string $label, string $valeur): self
    {
        return new self($sujet, view('workflow.mail.prescripteur', [
            'base' => self::base(),
            'phrase' => new HtmlString($sujet.'.'),
            'sms' => MessagesAutoController::html($sms),
            'signature' => MessagesAutoController::html(MessagesAutoController::texte('signature')),
            'projet' => html_entity_decode($projet, ENT_QUOTES),
            'label' => $label,
            'valeur' => html_entity_decode($valeur, ENT_QUOTES),
        ])->render());
    }

    private static function base(): string
    {
        return rtrim((string) config('app.url'), '/').'/';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Corim promotion] - '.$this->sujet);
    }

    public function content(): Content
    {
        return new Content(text: 'workflow.mail.texte', with: ['texte' => strip_tags($this->corps)], htmlString: $this->corps);
    }
}
