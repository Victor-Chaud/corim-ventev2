<?php

namespace App\Mail\Prescribers;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccessMail extends Mailable
{
    public function __construct(public string $pseudo, public ?string $link = null, public bool $modification = false, public bool $passwordChanged = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->modification ? '[Corim promotion] - Votre accès à la plateforme CORIM' : '[Corim promotion] - Votre accès à la plateforme',
        );
    }

    public function content(): Content
    {
        $html = view('prescribers.mail.access', [
            'modification' => $this->modification,
            'pseudo' => $this->pseudo,
            'link' => $this->link,
            'passwordChanged' => $this->passwordChanged,
        ])->render();

        return new Content(text: 'prescribers.mail.text', with: ['text' => strip_tags($html)], htmlString: $html);
    }
}
