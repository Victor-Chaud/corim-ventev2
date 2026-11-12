<?php

namespace App\Mail\Auth;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetLinkMail extends Mailable
{
    public function __construct(public string $pseudo, public string $lien) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address((string) config('mail.from.address'), 'Corim promotion'), subject: '[Corim promotion] - Réinitialisation de votre mot de passe');
    }

    public function content(): Content
    {
        $html = view('auth-users-privileges.mail.reinitialisation', [
            'base' => rtrim((string) config('app.url'), '/').'/',
            'pseudo' => $this->pseudo,
            'lien' => $this->lien,
        ])->render();

        return new Content(text: 'auth-users-privileges.mail.texte', with: ['texte' => strip_tags($html)."\n\n".$this->lien], htmlString: $html);
    }
}
