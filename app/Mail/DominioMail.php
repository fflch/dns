<?php

namespace App\Mail;

use App\Models\Dominio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa todos os envolvidos (solicitante e admins) de cada ação: solicitado, aprovado ou rejeitado.
 */
class DominioMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $acao  'solicitado', 'aprovado' ou 'rejeitado'
     * @param  string  $quem  nome de quem fez a ação
     * @param  ?string  $mensagem  opcional ao aprovar, obrigatória ao rejeitar
     * @param  bool  $revogado  rejeitado depois de já estar aprovado
     */
    public function __construct(
        public Dominio $dominio,
        public string $acao,
        public string $quem,
        public ?string $mensagem = null,
        public bool $revogado = false,
    ) {}

    public function envelope(): Envelope
    {
        $fqdn = $this->dominio->fqdn();

        $assunto = match (true) {
            $this->acao == 'solicitado' => "nova solicitação de {$fqdn}",
            $this->revogado => "aprovação de {$fqdn} revogada",
            default => "{$fqdn} {$this->acao}",
        };

        return new Envelope(
            subject: "DNS FFLCH: {$assunto}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dominio',
        );
    }
}
