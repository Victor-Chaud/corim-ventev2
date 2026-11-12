<?php

namespace App\Mail\ClientsProspects;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DenonciationMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Corim promotion] - Vous avez une demande de dénonciation en attente');
    }

    public function content(): Content
    {
        $html = view('clients-prospects.mail.denonciation', ['base' => rtrim((string) config('app.url'), '/').'/'])->render();

        return new Content(text: 'clients-prospects.mail.texte', with: ['texte' => strip_tags($html)], htmlString: $html);
    }
}
