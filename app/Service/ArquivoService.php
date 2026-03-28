<?php

namespace App\Service;

class ArquivoService {
    public function salveFile($arquivo){
        // Salva o arquivo e retorna o caminho local, mas o indicado séria um serviço de storage para registro e backUp dos arquivos
        $fileLocal = $arquivo->store('arquivos', 'public');

        return $fileLocal;
    }
}