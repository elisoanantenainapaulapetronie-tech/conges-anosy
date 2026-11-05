<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Message simple : sujet, texte, et un bouton facultatif (lien). */
class MessageAgent extends Mailable
{
    public function __construct(
        public string $sujet,
        public string $texte,
        public ?string $lien = null,
        public ?string $libelleLien = null,
        public string $nomAgent = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->sujet);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message');
    }
}
