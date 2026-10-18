<?php

use Illuminate\Support\Facades\Schedule;

// Retenção do activity log (config/activitylog.php). O comando é próprio
// porque o do pacote não sabe percorrer tenants — ver LimparActivityLog.
Schedule::command('activity:limpar')->daily();

// Arquivos de importação: só o necessário para baixar os erros na semana seguinte.
Schedule::command('importacoes:limpar')->daily();
