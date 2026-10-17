<?php

/*
 * Parâmetros de listagem e de negócio dos leads. Números que antes estavam
 * cravados em classes (POR_PAGINA, 14 dias de "parado") moram aqui.
 */
return [
    'listagem' => [
        'por_pagina' => 25,
        'max_por_pagina' => 100,
    ],

    // Lead sem contato há mais que isto é marcado como "parado".
    'dias_estagnado' => 14,

    'kanban' => [
        'cards_por_coluna' => 30,
        'max_cards_por_coluna' => 100,
    ],

    'importacao' => [
        'max_linhas' => 20000,
        'max_erros_guardados' => 200,
        'reter_arquivo_dias' => 7,
    ],

    'transferencia' => [
        'max_ids_por_requisicao' => 200,
    ],
];
