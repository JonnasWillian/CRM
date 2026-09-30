<?php

namespace App\Http\Controllers;

use App\Models\arquivo as ArquivoModel;
use App\Models\ProjetoAnexo;
use App\Services\Arquivos\ArquivoService;

/**
 * A única porta de saída do conteúdo de um anexo.
 *
 * Em routes/web.php e não em api.php: é aberto por um <a href> comum, que
 * carrega a sessão web. O binding usa o TenantScope (arquivo de outro tenant
 * = 404) e exclui a lixeira (soft-deleted = 404); a policy decide o resto,
 * com a mesma regra de quem pode ver o lead.
 */
class DownloadDeArquivoController extends Controller
{
    public function __construct(private readonly ArquivoService $arquivos)
    {
    }

    public function lead(ArquivoModel $arquivo)
    {
        $this->authorize('view', $arquivo);

        return $this->arquivos->baixar($arquivo->local, $arquivo->nome);
    }

    public function projeto(ProjetoAnexo $projetoAnexo)
    {
        $this->authorize('view', $projetoAnexo);

        return $this->arquivos->baixar($projetoAnexo->local, $projetoAnexo->nome);
    }
}
