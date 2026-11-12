<?php

namespace App\Mail\Promoteurs;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccesMail extends Mailable
{
    public function __construct(public string $pseudo, public string $motDePasse, public bool $modification = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->modification ? '[Corim promotion] - Votre accès à la plateforme CORIM' : '[Corim promotion] - Votre accès à la plateforme',
        );
    }

    public function content(): Content
    {
        $html = view('promoteurs.mail.acces', [
            'modification' => $this->modification,
            'pseudo' => $this->pseudo,
            'password' => $this->motDePasse,
        ])->render();

        return new Content(text: 'promoteurs.mail.texte', with: ['texte' => strip_tags($html)], htmlString: $html);
    }
}
