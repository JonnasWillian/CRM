<?php

namespace App\Services\Arquivos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dono único do ciclo de vida de um anexo em disco: gravar, servir, remover.
 *
 * Disco `local` (storage/app/private), nunca `public`: o que está em
 * `public` é servido pelo servidor web em /storage sem passar pelo Laravel,
 * portanto sem login, sem tenant e sem policy.
 *
 * O caminho carrega tenant e dono (`tenants/{t}/leads/{id}/…`). Isso não é
 * controle de acesso — quem controla é a policy na rota de download —, mas
 * torna possível apagar, exportar ou auditar os arquivos de uma empresa ou de
 * um lead sem consultar o banco linha a linha (LGPD).
 */
class ArquivoService
{
    public const DISCO = 'local';

    public static function pastaDoLead(int $tenantId, int $leadId): string
    {
        return "tenants/{$tenantId}/leads/{$leadId}";
    }

    public static function pastaDoProjeto(int $tenantId, int $projetoId): string
    {
        return "tenants/{$tenantId}/projetos/{$projetoId}";
    }

    /**
     * Grava o arquivo e deixa `$registrar` criar a linha. Se a linha falhar, o
     * arquivo sai do disco antes de a exceção seguir — sem órfão.
     *
     * @param  callable(array{local:string,tamanho:int,mime:?string}): Model  $registrar
     */
    public function guardarComRegistro(UploadedFile $arquivo, string $pasta, callable $registrar): Model
    {
        // Extensão pelo conteúdo, não pelo nome que o cliente mandou.
        $nomeInterno = Str::uuid()->toString().'.'.($arquivo->guessExtension() ?? 'bin');
        $caminho = $arquivo->storeAs($pasta, $nomeInterno, self::DISCO);

        if ($caminho === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo.');
        }

        try {
            // Transação: se o observer (activity log) falhar depois do
            // INSERT, a linha é desfeita junto e o arquivo sai do disco
            // abaixo — nem linha sem log, nem arquivo sem linha.
            return DB::transaction(fn () => $registrar([
                'local' => $caminho,
                'tamanho' => $arquivo->getSize(),
                'mime' => $arquivo->getMimeType(),
            ]));
        } catch (\Throwable $erro) {
            $this->remover($caminho);

            throw $erro;
        }
    }

    public function remover(?string $caminho): void
    {
        if ($caminho) {
            Storage::disk(self::DISCO)->delete($caminho);
        }
    }

    /**
     * Sempre como anexo (nunca inline) e com nosniff: mesmo um arquivo que
     * passou pela PoliticaDeUpload não deve ser interpretado pelo navegador no
     * domínio da aplicação.
     */
    public function baixar(string $caminho, ?string $nome): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISCO)->exists($caminho), 404);

        return Storage::disk(self::DISCO)->download($caminho, $this->nomeDoDownload($caminho, $nome), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * O `nome` é texto livre do usuário ("Contrato 01/2026"), mas o
     * Content-Disposition não aceita `/`, `\` nem caracteres de controle — o
     * Symfony lança exceção e o download vira 500. Sanitizar aqui, na fonte,
     * cobre anexos de lead e de projeto e também os nomes já gravados.
     */
    private function nomeDoDownload(string $caminho, ?string $nome): string
    {
        $limpo = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '-', (string) $nome)) ?? '');

        // Review Focus 5: nome vazio geraria Content-Disposition inválido.
        if ($limpo === '') {
            return basename($caminho);
        }

        // Sem extensão o sistema operacional de quem baixa não sabe abrir o
        // arquivo; a do caminho interno vem do conteúdo (guessExtension).
        $extensao = pathinfo($caminho, PATHINFO_EXTENSION);
        if (pathinfo($limpo, PATHINFO_EXTENSION) === '' && $extensao !== '') {
            $limpo .= '.'.$extensao;
        }

        return $limpo;
    }
}
