<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa os envolvidos que um admin excluiu a solicitação.
 * Recebe só textos (não o model): quando a fila processar, o registro já não existe mais.
 */
class DominioExcluidoMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $fqdn,
        public string $ip,
        public string $status,
        public ?string $solicitante,
        public string $motivo,
        public string $quem,
        public ?string $mensagem = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "DNS FFLCH: {$this->fqdn} excluído",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dominio-excluido',
        );
    }
}
