<?php

namespace Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Core\Models\AccessRequest;

class NewAccessRequestMail extends Mailable
{
    public function __construct(public AccessRequest $accessRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouvelle demande d\'accès');
    }

    public function content(): Content
    {
        return new Content(markdown: 'core::mail.new-access-request');
    }
}
