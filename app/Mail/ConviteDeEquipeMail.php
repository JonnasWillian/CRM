<?php

namespace App\Mail;

use App\Models\Convite;
use App\Support\TextoSeguro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Convite para a equipe. O token viaja só aqui (no banco fica o hash), por
 * isso é propriedade pública da mensagem e não do convite.
 */
class ConviteDeEquipeMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Convite $convite,
        public readonly string $token,
        public readonly string $empresa,
        public readonly string $convidadoPor,
    ) {}

    public function envelope(): Envelope
    {
        // Nomes escritos pelo cliente: sem link nem quebra de linha no assunto.
        return new Envelope(subject: TextoSeguro::paraAssunto($this->convidadoPor).' convidou você para o CRM da '.TextoSeguro::paraAssunto($this->empresa));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.convite', with: [
            'url' => route('convites.show', $this->token),
            // A view usa estes, não as propriedades públicas: Markdown do cliente sai literal.
            'empresaSegura' => TextoSeguro::semMarkdown($this->empresa),
            'convidadoPorSeguro' => TextoSeguro::semMarkdown($this->convidadoPor),
            'papel' => ucfirst($this->convite->papel),
            'expira' => $this->convite->expira_em->format('d/m/Y'),
        ]);
    }
}
