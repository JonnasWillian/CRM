<?php

namespace App\Http\Resources;

use App\Models\EstagioHistorico;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma linha do activity log no formato que o TimelinePanel lê.
 *
 * Os campos saem primeiro de `properties` (gravados pelo observer no
 * momento do evento) e, na falta, do `subject` — que é o caso das linhas do
 * backfill, cujas properties são só {backfill: true}. Nomes de estágio vêm do
 * mapa que o controller carrega em lote, nunca uma consulta por linha.
 *
 * Subject na lixeira não é carregado (config activitylog
 * subject_returns_soft_deleted_models = false): o evento aparece com o
 * rótulo do tipo e sem o nome, o que é aceitável para registro antigo. A
 * descrição de anotação vem só do subject (ver toArray).
 */
class AtividadeResource extends JsonResource
{
    private const COM_DESCRICAO = ['anotacao', 'projeto_anotacao'];
    private const DE_ESTAGIO = ['status_alterado', 'funil_alterado'];
    private const DE_ARQUIVO = ['arquivo', 'arquivo_removido', 'projeto_anexo', 'projeto_anexo_removido'];

    /** @param  array<int,string>  $nomesDeEstagio  id => descrição, withTrashed */
    public function __construct($resource, private readonly array $nomesDeEstagio = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $propriedades = collect($this->properties);
        $subject = $this->subject;
        $tipo = $this->event;

        $campos = [
            'id' => $this->id,
            'tipo' => $tipo,
            'data' => $this->created_at?->toIso8601String(),
            'nome' => $propriedades->get('nome') ?? $this->nomeDoSubject($tipo, $subject),
            'titulo' => $propriedades->get('titulo') ?? ($subject instanceof Tarefa ? $subject->titulo : null),
            'projeto_nome' => $propriedades->get('projeto_nome') ?? $this->projetoDoSubject($subject),
        ];

        // Descrição de anotação é a exceção à regra "properties primeiro": a
        // anotação é editável e a edição não gera evento, então o texto
        // gravado na criação envelhece. Vale o subject vivo; sem subject
        // (anotação na lixeira) a descrição é omitida — mostrar o texto de
        // properties exibiria como vigente algo que o usuário excluiu.
        if (in_array($tipo, self::COM_DESCRICAO, true)) {
            $campos['descricao'] = $subject?->getAttribute('descricao');
        }

        if (in_array($tipo, self::DE_ESTAGIO, true)) {
            $campos['estagio_anterior'] = $this->nomeDoEstagio($propriedades, $subject, 'anterior');
            $campos['estagio_novo'] = $this->nomeDoEstagio($propriedades, $subject, 'novo');
        }

        return array_filter($campos, fn ($valor) => $valor !== null);
    }

    /**
     * `nome` de arquivo e anexo é nullable. O observer grava 'Arquivo sem
     * nome' nesse caso, e o timeline antigo mostrava o mesmo; a linha do
     * backfill só tem o subject e precisa do mesmo rótulo.
     */
    private function nomeDoSubject(?string $tipo, $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        $nome = $subject->getAttribute('nome');

        return in_array($tipo, self::DE_ARQUIVO, true) ? ($nome ?: 'Arquivo sem nome') : $nome;
    }

    private function projetoDoSubject($subject): ?string
    {
        return $subject instanceof ProjetoAnotacao || $subject instanceof ProjetoAnexo
            ? $subject->projeto?->nome
            : null;
    }

    /**
     * Três gerações de linha: com o nome gravado (observer a partir desta
     * tarefa), com o id em `estagio_{lado}_id` (observer anterior) ou
     * `tag_id_{lado}` (antes do rename tags -> estagios), e do backfill, em
     * que o id está no próprio EstagioHistorico do subject.
     */
    public static function idsDeEstagio($atividade): array
    {
        $p = collect($atividade->properties);
        $s = $atividade->subject;

        return array_filter([
            $p->get('estagio_anterior_id') ?? $p->get('tag_id_anterior') ?? ($s instanceof EstagioHistorico ? $s->estagio_anterior_id : null),
            $p->get('estagio_novo_id') ?? $p->get('tag_id_novo') ?? ($s instanceof EstagioHistorico ? $s->estagio_novo_id : null),
        ]);
    }

    private function nomeDoEstagio($propriedades, $subject, string $lado): string
    {
        if ($nome = $propriedades->get("estagio_{$lado}")) {
            return $nome;
        }

        $id = $propriedades->get("estagio_{$lado}_id")
            ?? $propriedades->get("tag_id_{$lado}")
            ?? ($subject instanceof EstagioHistorico ? $subject->{"estagio_{$lado}_id"} : null);

        return $this->nomesDeEstagio[$id] ?? '—';
    }
}
