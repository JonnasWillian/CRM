<?php

use Illuminate\Support\Facades\Schedule;

// Retenção do activity log (config/activitylog.php). O comando é próprio
// porque o do pacote não sabe percorrer tenants — ver LimparActivityLog.
Schedule::command('activity:limpar')->daily();

// Arquivos de importação: só o necessário para baixar os erros na semana seguinte.
Schedule::command('importacoes:limpar')->daily();

// Cobrança: fatura de renovação 7 dias antes e avisos de vencimento, carência
// e suspensão. Idempotente (avisos_de_assinatura). Ver docs/comercial/plano-de-cobranca-e-assinaturas.md.
Schedule::command('assinaturas:rotina')->dailyAt('08:00')->timezone('America/Sao_Paulo');

// Pagamentos pelo Asaas cujo aviso não chegou (servidor fora do ar, fila de avisos pausada).
// Sem ASAAS_API_KEY, não faz nada.
Schedule::command('asaas:conciliar')->hourly();
