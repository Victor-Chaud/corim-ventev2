<?php

namespace App\Mail\Workflow;

use App\Http\Controllers\Evolutions\AutoMessagesController;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\HtmlString;

class WorkflowMail extends Mailable
{
    public function __construct(public string $mailSubject, public string $body) {}

    public static function corim(string $subject, string $phrase): self
    {
        return new self($subject, view('workflow.mail.corim', [
            'base' => self::base(),
            'phrase' => new HtmlString($phrase),
        ])->render());
    }

    public static function prescriber(string $subject, string $sms, string $program, string $label, string $value): self
    {
        return new self($subject, view('workflow.mail.prescriber', [
            'base' => self::base(),
            'phrase' => new HtmlString($subject.'.'),
            'sms' => AutoMessagesController::html($sms),
            'signature' => AutoMessagesController::html(AutoMessagesController::text('signature')),
            'program' => html_entity_decode($program, ENT_QUOTES),
            'label' => $label,
            'value' => html_entity_decode($value, ENT_QUOTES),
        ])->render());
    }

    private static function base(): string
    {
        return rtrim((string) config('app.url'), '/').'/';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Corim promotion] - '.$this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(text: 'workflow.mail.text', with: ['text' => strip_tags($this->body)], htmlString: $this->body);
    }
}
