<?php

namespace App\Mail\Auth;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccesMail extends Mailable
{
    public function __construct(public string $sujet, public string $intro, public string $pseudo, public string $motDePasse) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address((string) config('mail.from.address'), 'Corim promotion'), subject: $this->sujet);
    }

    public function content(): Content
    {
        $html = $this->body();

        return new Content(text: 'auth-users-privileges.mail.texte', with: ['texte' => strip_tags($html)], htmlString: $html);
    }

    private function body(): string
    {
        return view('auth-users-privileges.mail.acces', [
            'base' => rtrim((string) config('app.url'), '/').'/',
            'intro' => $this->intro,
            'pseudo' => $this->pseudo,
            'password' => $this->motDePasse,
        ])->render();
    }
}
