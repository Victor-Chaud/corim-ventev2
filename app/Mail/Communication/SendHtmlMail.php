<?php

namespace App\Mail\Communication;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

class SendHtmlMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $email, public string $sujet, public string $corps) {}

    public function middleware(): array
    {
        return [new RateLimited('emailing')];
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new HtmlMail($this->sujet, $this->corps));
    }
}
