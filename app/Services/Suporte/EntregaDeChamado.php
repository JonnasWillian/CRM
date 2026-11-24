<?php

namespace App\Services\Suporte;

use App\Mail\ChamadoDeSuporteMail;
use App\Models\ChamadoDeSuporte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Leva o chamado à caixa do suporte, pelo canal de config/suporte.php:
 *
 *  - `mail`: o e-mail do sistema (precisa de SMTP e da fila rodando);
 *  - `formsubmit`: o serviço formsubmit.co entrega o e-mail, sem SMTP nem
 *    fila. A chamada sai do servidor, então o destino não aparece no
 *    navegador. Os dados do chamado passam por esse serviço.
 *
 * Falha na entrega não derruba o envio da pessoa: o chamado já está gravado
 * (entregue_em fica nulo) e `php artisan suporte:reenviar` tenta de novo.
 */
class EntregaDeChamado
{
    public function entregar(ChamadoDeSuporte $chamado): bool
    {
        $destino = (string) config('suporte.email');
        if ($destino === '') {
            return false;
        }

        try {
            if (config('suporte.canal') === 'formsubmit') {
                $this->peloFormSubmit($chamado);
            } else {
                Mail::to($destino)->send(new ChamadoDeSuporteMail($chamado));
            }
        } catch (Throwable $e) {
            Log::error("Suporte: chamado #{$chamado->id} não foi entregue.", ['erro' => $e->getMessage()]);

            return false;
        }

        $chamado->forceFill(['entregue_em' => Carbon::now()])->save();

        return true;
    }

    private function peloFormSubmit(ChamadoDeSuporte $chamado): void
    {
        $d = (new ChamadoDeSuporteMail($chamado))->dados();
        $destino = (string) (config('suporte.formsubmit_destino') ?: config('suporte.email'));

        $resposta = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(10)
            ->withHeaders(['Origin' => rtrim((string) config('app.url'), '/'), 'Referer' => rtrim((string) config('app.url'), '/').'/'])
            ->post('https://formsubmit.co/ajax/'.str_replace('%40', '@', rawurlencode($destino)), array_filter([
                '_subject' => "[Suporte #{$d['protocolo']}] {$d['tipo']}: {$d['area']}",
                '_replyto' => $d['emailDeContato'],
                '_template' => 'table',
                '_captcha' => 'false',
                'name' => $d['nome'],
                'email' => $d['emailDeContato'],
                'E-mail da conta' => $d['emailDaConta'],
                'Empresa' => $d['empresa'],
                'Plano' => $d['plano'],
                'Assunto' => $d['tipo'],
                'Área' => $d['area'],
                'Impacto' => $d['impacto'],
                'Nota (0 a 10)' => $d['nota'] === null ? null : (string) $d['nota'],
                'Relato' => $d['mensagem'],
                'Página' => $d['pagina'],
                'Navegador' => $d['navegador'],
                'Quando' => $d['quando'],
            ], fn ($v) => $v !== null && $v !== ''));

        // O FormSubmit responde 200 com success "false" quando recusa (ex.: caixa ainda não ativada).
        if ($resposta->failed() || in_array($resposta->json('success'), [false, 'false'], true)) {
            throw new \RuntimeException('FormSubmit: HTTP '.$resposta->status().' '.mb_substr((string) $resposta->json('message', $resposta->body()), 0, 200));
        }
    }
}
