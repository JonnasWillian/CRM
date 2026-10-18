<?php

namespace App\Mail;

use App\Models\Convite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Convite para a equipe. O token viaja só aqui (no banco fica o hash), por
 * isso é propriedade pública da mensagem e não do convite.
 */
class ConviteDeEquipeMail extends Mailable implements ShouldQueue
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
        return new Envelope(subject: "{$this->convidadoPor} convidou você para o CRM da {$this->empresa}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.convite', with: [
            'url' => route('convites.show', $this->token),
            'papel' => ucfirst($this->convite->papel),
            'expira' => $this->convite->expira_em->format('d/m/Y'),
        ]);
    }
}
