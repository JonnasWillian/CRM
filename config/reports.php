<?php

return [
    // Segundos que o resumo de métricas fica em cache por tenant/agente/período.
    // A invalidação por versão (MetricasCache) cobre o caso comum; o TTL é a
    // rede de segurança.
    'metricas_ttl' => (int) env('REPORTS_METRICAS_TTL', 300),
];
