<?php

namespace App\Mail\Communication;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HtmlMail extends Mailable
{
    public function __construct(public string $sujet, public string $corps) {}

    public static function queueTo(array $emails, string $sujet, string $corps): void
    {
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SendHtmlMail::dispatch($email, $sujet, $corps);
            }
        }
    }

    public function envelope(): Envelope
    {
        $address = (string) config('mail.from.address');

        return new Envelope(
            from: new Address($address, 'Corim Promotion'),
            replyTo: [new Address($address)],
            subject: $this->sujet,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->corps);
    }
}
