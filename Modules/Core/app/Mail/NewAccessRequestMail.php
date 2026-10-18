<?php

namespace Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Core\Models\AccessRequest;

class NewAccessRequestMail extends Mailable
{
    public function __construct(public AccessRequest $accessRequest) {}

    /**
     * Neutralise la syntaxe Markdown d'une valeur saisie par le public
     * (liens, emphase, titres…) : elle s'affiche telle quelle dans l'e-mail.
     * `<`, `>` et `&` sont déjà échappés par Blade.
     */
    public static function md(?string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);

        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', $value);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouvelle demande d\'accès');
    }

    public function content(): Content
    {
        return new Content(markdown: 'core::mail.new-access-request');
    }
}
