<?php

namespace App\Mail;

use App\Models\Assinatura;
use App\Models\ChamadoDeSuporte;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Chamado aberto pelo assistente de ajuda, para a caixa do suporte.
 *
 * O texto do cliente só entra no corpo, escapado, numa view Blade comum (não
 * Markdown: link e imagem escritos pelo cliente não viram link nem imagem).
 * O assunto só tem valores do sistema. Responder o e-mail fala com o cliente
 * (Reply-To no e-mail de contato que ele confirmou).
 */
class ChamadoDeSuporteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ChamadoDeSuporte $chamado) {}

    public function envelope(): Envelope
    {
        $d = $this->dados();

        return new Envelope(
            subject: "[Suporte #{$this->chamado->id}] {$d['tipo']}: {$d['area']}",
            replyTo: [new Address($this->chamado->email_contato, $d['nome'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.suporte', text: 'mail.suporte-texto', with: $this->dados());
    }

    /** @return array<string, mixed> */
    public function dados(): array
    {
        $c = $this->chamado;
        $user = User::find($c->user_id);
        $assinatura = Assinatura::where('tenant_id', $c->tenant_id)->first();

        return [
            'protocolo' => $c->id,
            'nome' => $user?->name ?? 'Usuário removido',
            'emailDaConta' => $user?->email ?? '',
            'emailDeContato' => $c->email_contato,
            'empresa' => Tenant::whereKey($c->tenant_id)->value('nome') ?? '',
            'plano' => $assinatura ? (($assinatura->planoConfig()['nome'] ?? $assinatura->plano ?? '').' ('.$assinatura->status.')') : '',
            'tipo' => config("suporte.tipos.{$c->tipo}") ?? $c->tipo,
            'area' => config("suporte.areas.{$c->area}") ?? $c->area,
            'impacto' => $c->impacto ? (config("suporte.impactos.{$c->impacto}") ?? $c->impacto) : null,
            'nota' => $c->nota,
            'mensagem' => $c->mensagem,
            'pagina' => $c->pagina,
            'navegador' => $c->navegador,
            'quando' => $c->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
        ];
    }
}
