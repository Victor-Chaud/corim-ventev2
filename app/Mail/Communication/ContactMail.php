<?php

namespace App\Mail\Communication;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMail extends Mailable
{
    public const HTML = '<table width="500"><tr><td align="left"><img src="https://corim-vente.com/medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Vous avez reçu un nouveau message<br /><br />Pour y accéder, vous pouvez cliquer <a href="https://corim-vente.com/messagerie">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77</td></tr></table>';

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Corim promotion] - Vous avez reçu un nouveau message');
    }

    public function content(): Content
    {
        return new Content(text: 'communication.mail.texte', with: ['texte' => strip_tags(self::HTML)], htmlString: self::HTML);
    }
}
