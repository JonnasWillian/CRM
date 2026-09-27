<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RotaRegistrada;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Toda rota de escrita (em /api e em web.php/auth.php), e toda rota de
 * LEITURA com parâmetro de model (em /api e em web.php), autoriza
 * explicitamente, ou está isenta com motivo escrito.
 *
 * Por que esta forma para a ESCRITA, e não a inspeção de parâmetros de rota:
 * as oito falhas de autorização que este plano fechou entram por um campo do
 * CORPO da requisição — `usuario_id`, `projeto_id`, `user_id`. O route model
 * binding só enxerga a URL, então nenhuma delas apareceria numa varredura de
 * parâmetros. A pergunta que pega essa classe é outra: "este método decide
 * alguma coisa sem nunca perguntar se pode?"
 *
 * Um método de ESCRITA passa se fizer uma destas duas coisas:
 *   - chamar `$this->authorize(...)` no próprio corpo; ou
 *   - receber um FormRequest cujo `authorize()` faça algo além de `return true`.
 *
 * A LEITURA é diferente: uma rota GET com parâmetro de model resolve o
 * recurso pelo próprio binding — é exatamente o parâmetro da URL, não um
 * campo do corpo — então a varredura de parâmetro FAZ sentido aqui (ao
 * contrário da escrita). A nona porta que este teste ganhou nesta rodada,
 * `GET /leads/{usuario}`, era um closure em web.php sem `$this->authorize()`
 * nenhum: a autorização estava no MIDDLEWARE da rota (`->can('view',
 * 'usuario')`), não no corpo do método. Por isso o critério de leitura aceita
 * uma terceira forma:
 *   - chamar `$this->authorize(...)` (ou receber um FormRequest que autorize); ou
 *   - a rota declarar `->can(...)`, que vira middleware `can:...`.
 *
 * GET sem parâmetro de model é listagem/catálogo (visibleTo(), escopo de
 * tenant, etc.) e não entra nesta varredura — não é isenção, é fora do
 * critério.
 *
 * Qualquer outra rota (de escrita, ou de leitura com parâmetro de model)
 * precisa estar na lista de isentas correspondente, com o motivo ao lado. A
 * isenção é barata de escrever e cara de justificar — é esse o ponto.
 */
class TodaRotaDeEscritaAutorizaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rotas de escrita que legitimamente não autorizam por instância.
     * Cada linha precisa do motivo, e o motivo precisa sobreviver a uma
     * pergunta: "o que um agente mal-intencionado faria com isto?"
     */
    private const ISENTAS = [
        // Configuração do tenant: barradas por can:configuracoes.manage no
        // grupo de rotas. Não existe "meu funil" e "funil do colega" — existe
        // o funil da empresa.
        'POST api/funis',
        'PUT api/funis/{funil}',
        'DELETE api/funis/{funil}',
        'PATCH api/funis/reordenar',
        'PATCH api/funis/{funil}/padrao',
        'POST api/funis/{funil}/estagios',
        'PATCH api/funis/{funil}/estagios/reordenar',
        'PUT api/estagios/{estagio}',
        'DELETE api/estagios/{estagio}',
        'PATCH api/estagios/{estagio}/restaurar',
        'POST api/motivos-perda',
        'PUT api/motivos-perda/{motivo}',
        'DELETE api/motivos-perda/{motivo}',
        'PATCH api/motivos-perda/{motivo}/restaurar',
        'PATCH api/motivos-perda/reordenar',

        // Escrevem apenas no próprio usuário autenticado; não há recurso de
        // terceiro a proteger.
        'PATCH api/kanban/settings',

        // Leitura disfarçada de POST por convenção antiga do projeto: o corpo
        // carrega filtros, não identificador de dono. A visibilidade é
        // garantida por Usuario::scopeVisibleTo dentro do método.
        'POST api/pegarUsuarios',
        'POST api/kanban',
        'POST api/metricas',
        'POST api/tarefasPendentes',

        // Leitura disfarçada de POST, mas por outro motivo: são catálogo do
        // tenant (estágios e status), não dado de agente — todo mundo no
        // tenant vê a lista inteira, não existe "meu estágio" e "estágio do
        // colega". Não chamam Usuario::scopeVisibleTo; a proteção é o escopo
        // de tenant do próprio model (BelongsToTenant em Estagio e Statu).
        'POST api/estagios',
        'POST api/status',

        // Cria modelo de tarefa do próprio agente: user_id vem de auth(),
        // nunca do corpo.
        'POST api/tarefa-padroes',

        // POST api/usuarios NÃO está isenta: UsuarioRequest::authorize() faz
        // a rede passar por MÉRITO (o corpo do método vai além de `return
        // true`), então não precisa — e não deve — constar aqui. Uma isenção
        // cujo motivo é exatamente o que a rede deveria verificar se
        // auto-anula: reverter authorize() para `return true` derrubaria a
        // proteção real sem que este teste percebesse, porque o `in_array`
        // dá `continue` antes de inspecionar qualquer coisa.

        // ── web.php / auth.php (item 2 da rodada C: a varredura de escrita
        // passou a olhar também a árvore de rotas web, não só /api) ──
        //
        // Escrevem exclusivamente no PRÓPRIO usuário autenticado
        // ($request->user()), nunca num id vindo do corpo ou da URL — não há
        // recurso de terceiro para proteger. Conferido lendo cada método:
        'PATCH profile',   // ProfileController::update  -> $request->user()->fill(...)->save()
        'DELETE profile',  // ProfileController::destroy -> $request->user()->delete()
        'POST email/verification-notification', // EmailVerificationNotificationController::store -> $request->user()->sendEmailVerificationNotification()
        'POST confirm-password', // ConfirmablePasswordController::store -> valida a senha do próprio $request->user()
        'PUT password', // PasswordController::update -> $request->user()->update(['password' => ...])
        'POST logout', // AuthenticatedSessionController::destroy -> Auth::guard('web')->logout() da própria sessão

        // Rotas de autenticação padrão do Laravel (guest, ninguém logado
        // ainda): não agem sobre um id de terceiro escolhido pelo requisitante
        // — o alvo é sempre resolvido por credencial que só o dono tem (senha
        // no login, token assinado por e-mail no reset), não por id no corpo.
        'POST register', // RegisteredUserController::store -> cria tenant + usuário NOVOS para quem está pedindo
        'POST login', // AuthenticatedSessionController::store -> LoginRequest::authenticate() decide pela credencial, não por id
        'POST forgot-password', // PasswordResetLinkController::store -> só dispara e-mail para o dono do endereço informado
        'POST reset-password', // NewPasswordController::store -> Password::reset() exige o token assinado enviado ao e-mail do dono
    ];

    /**
     * Rotas de LEITURA (GET/HEAD) com parâmetro de model que legitimamente
     * não autorizam por instância. Hoje está vazia DE PROPÓSITO: as nove
     * rotas GET+model que existem no branch (oito em api.php, uma em
     * web.php) chamam `$this->authorize(...)` no corpo ou declaram
     * `->can(...)` na própria rota — nenhuma precisa de isenção. Cada entrada
     * futura precisa do mesmo padrão da lista de escrita: motivo verificado
     * lendo o método, não suposto.
     */
    private const ISENTAS_LEITURA = [
        //
    ];

    /**
     * Item 2 da rodada C: esta varredura filtrava `str_starts_with($rota->uri(),
     * 'api/')`, deixando toda escrita de web.php (e de auth.php, que web.php
     * inclui via `require`) fora da rede — a mesma assimetria que escondeu
     * `GET /leads/{usuario}` da varredura de leitura antes da rodada B. Hoje
     * nenhuma escrita web tem parâmetro de model nem dono no corpo (ver
     * ISENTAS acima, cada uma com o método lido e a razão ao lado), mas a
     * rede não deveria depender disso continuar sendo verdade sem se dar
     * conta.
     */
    public function test_toda_rota_de_escrita_autoriza_ou_esta_isenta(): void
    {
        $desprotegidas = [];

        foreach (Route::getRoutes() as $rota) {
            $metodos = array_intersect($rota->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
            if (! $metodos) {
                continue;
            }

            $assinatura = reset($metodos).' '.$rota->uri();
            if (in_array($assinatura, self::ISENTAS, true)) {
                continue;
            }

            $acao = $rota->getAction('uses');
            if (! is_string($acao) || ! str_contains($acao, '@')) {
                continue;
            }

            [$classe, $metodo] = explode('@', $acao);

            // Rota que aponta para método inexistente não é "sem autorização",
            // é pior: some da contagem e a rede passa verde mentindo. Acusa.
            if (! class_exists($classe) || ! method_exists($classe, $metodo)) {
                $desprotegidas[] = $assinatura.'  ->  acao inexistente: '.$acao;
                continue;
            }

            if (! $this->autoriza(new ReflectionMethod($classe, $metodo))) {
                $desprotegidas[] = $assinatura.'  ->  '.class_basename($classe).'::'.$metodo;
            }
        }

        $this->assertSame([], $desprotegidas, implode("\n  ", array_merge(
            ['rotas de escrita sem autorizacao e sem isencao justificada:'],
            $desprotegidas,
        )));
    }

    /**
     * Item 1 da rodada B: a rede de escrita filtra `api/` e só olha
     * POST/PUT/PATCH/DELETE — duas razões acumuladas para nunca alcançar
     * `GET /leads/{usuario}`, uma rota de LEITURA em web.php. Esta segunda
     * varredura cobre GET/HEAD com parâmetro de model, nas duas árvores de
     * rota.
     */
    public function test_toda_rota_get_com_model_autoriza_ou_esta_isenta(): void
    {
        $desprotegidas = [];

        foreach (Route::getRoutes() as $rota) {
            $metodos = array_intersect($rota->methods(), ['GET', 'HEAD']);
            if (! $metodos) {
                continue;
            }

            $reflexaoOuErro = $this->reflexaoDaAcao($rota->getAction('uses'));

            // GET sem parâmetro de model é listagem/catálogo: fora do
            // critério, não é isenção. Precisa da reflection para saber o
            // NOME dos parâmetros da ação e casar com o da URI — por isso
            // esta checagem vem depois de resolver a ação, não antes.
            if (is_string($reflexaoOuErro)) {
                // Ação inexistente: não dá para inspecionar os parâmetros
                // dela, então não dá para saber se tem model. Trata como
                // desprotegida só se a URI já sugere um recurso (tem `{`) —
                // caso contrário seria ruído (ex.: uma rota utilitária sem
                // controller de verdade).
                if (str_contains($rota->uri(), '{')) {
                    $assinatura = reset($metodos).' '.$rota->uri();
                    if (! in_array($assinatura, self::ISENTAS_LEITURA, true)) {
                        $desprotegidas[] = $assinatura.'  ->  '.$reflexaoOuErro;
                    }
                }
                continue;
            }

            if ($reflexaoOuErro === null || ! $this->temParametroDeModel($rota, $reflexaoOuErro)) {
                continue;
            }

            $assinatura = reset($metodos).' '.$rota->uri();
            if (in_array($assinatura, self::ISENTAS_LEITURA, true)) {
                continue;
            }

            if ($this->autorizaViaMiddlewareCan($rota)) {
                continue;
            }

            if (! $this->autoriza($reflexaoOuErro)) {
                $descricaoAcao = $reflexaoOuErro instanceof ReflectionMethod
                    ? class_basename($reflexaoOuErro->getDeclaringClass()->getName()).'::'.$reflexaoOuErro->getName()
                    : 'closure em '.$reflexaoOuErro->getFileName().':'.$reflexaoOuErro->getStartLine();

                $desprotegidas[] = $assinatura.'  ->  '.$descricaoAcao;
            }
        }

        $this->assertSame([], $desprotegidas, implode("\n  ", array_merge(
            ['rotas de leitura com parametro de model sem autorizacao e sem isencao justificada:'],
            $desprotegidas,
        )));
    }

    /**
     * Resolve a ação da rota para uma reflection navegável, cobrindo tanto
     * `Controller@metodo` (inclusive invokable, que o Laravel normaliza para
     * `Controller@__invoke` antes deste teste ver a rota) quanto closures
     * (as rotas de web.php que renderizam Inertia direto).
     *
     * Devolve uma STRING quando a ação aponta para um método que não existe
     * — mesmo raciocínio da varredura de escrita: isso não é "sem
     * autorização", é pior, some da contagem se for ignorado.
     */
    private function reflexaoDaAcao(mixed $acao): ReflectionFunctionAbstract|string|null
    {
        if ($acao instanceof \Closure) {
            return new ReflectionFunction($acao);
        }

        if (! is_string($acao) || ! str_contains($acao, '@')) {
            return null;
        }

        [$classe, $metodo] = explode('@', $acao);

        if (! class_exists($classe) || ! method_exists($classe, $metodo)) {
            return 'acao inexistente: '.$acao;
        }

        return new ReflectionMethod($classe, $metodo);
    }

    /**
     * Compara os parâmetros da URI (`{usuario}`, `{projeto}`, ...) com os
     * parâmetros da ação: só conta como "rota com model" quando o parâmetro
     * de mesmo NOME é tipado com uma subclasse de Eloquent Model — é assim
     * que o route model binding do Laravel resolve (por nome, não por
     * posição).
     */
    private function temParametroDeModel(RotaRegistrada $rota, ReflectionFunctionAbstract $reflexao): bool
    {
        $nomesNaUri = $rota->parameterNames();

        foreach ($reflexao->getParameters() as $parametro) {
            if (! in_array($parametro->getName(), $nomesNaUri, true)) {
                continue;
            }

            $tipo = $parametro->getType();
            if (! $tipo || $tipo->isBuiltin()) {
                continue;
            }

            $classe = $tipo->getName();
            if ($classe === Model::class || is_subclass_of($classe, Model::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `->can('view', 'usuario')` na definição da rota vira middleware
     * `can:view,usuario` (ver `Illuminate\Routing\Route::can()`). Não usa
     * `gatherMiddleware()` de propósito: esse método instancia o controller
     * para ler `Controller::getMiddleware()`, o que exigiria resolver
     * dependências de construtor só para checar uma string — `middleware()`
     * já basta, porque `->can()` sempre anexa direto na rota.
     *
     * `can:` sozinho não basta: `can:leads.manage` também começa com `can:`,
     * mas é permissão de CLASSE — nenhum argumento depois da permissão, nada
     * que amarre a checagem à INSTÂNCIA pedida pela URL. Uma rota
     * `GET probe/leads/{usuario}` com `can:leads.manage` passaria verde por
     * este método sem nunca perguntar "o usuário é deste lead?", e como todo
     * `vendedor` tem `leads.manage`, isso entregaria a carteira inteira do
     * tenant. Por isso exige-se que algum argumento do `can:` (depois da
     * permissão, daí o `array_slice(..., 1)`) seja o NOME de um parâmetro da
     * própria rota — é assim que `->can('view', 'usuario')` vira
     * `can:view,usuario` e amarra a checagem ao `{usuario}` da URL.
     */
    private function autorizaViaMiddlewareCan(RotaRegistrada $rota): bool
    {
        foreach ($rota->middleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                continue;
            }

            $args = explode(',', substr($middleware, 4));
            if (array_intersect(array_slice($args, 1), $rota->parameterNames())) {
                return true;
            }
        }

        return false;
    }

    /**
     * O método (ou closure) autoriza se chamar authorize() no próprio corpo,
     * ou se receber um FormRequest que autorize de verdade.
     */
    private function autoriza(ReflectionFunctionAbstract $funcao): bool
    {
        if (str_contains($this->corpoDe($funcao), '$this->authorize(')) {
            return true;
        }

        foreach ($funcao->getParameters() as $parametro) {
            $tipo = $parametro->getType();
            if (! $tipo || $tipo->isBuiltin()) {
                continue;
            }

            $classe = $tipo->getName();
            if (! is_subclass_of($classe, FormRequest::class)) {
                continue;
            }

            if ($this->formRequestAutoriza($classe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Um `authorize()` que só faz `return true;` não autoriza nada — é o valor
     * padrão do scaffold. Era exatamente o que três FormRequests deste projeto
     * tinham antes deste plano.
     */
    private function formRequestAutoriza(string $classe): bool
    {
        if (! method_exists($classe, 'authorize')) {
            return false;
        }

        $corpo = $this->corpoDe(new ReflectionMethod($classe, 'authorize'));

        // Tokenizador em vez de regex: comentário antes do return e TRUE em maiúscula
        // furavam a detecção por texto, e remendar o padrão caso a caso não termina.
        // Sem a flag TOKEN_PARSE de propósito: isto é lexer, não parser — o trecho
        // não é PHP válido em nível de arquivo.
        $significativos = [];
        foreach (token_get_all('<?php '.$corpo) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                    continue;
                }
                $significativos[] = strtolower($token[1]);
                continue;
            }
            $significativos[] = $token;
        }

        // Descarta a assinatura: o corpo começa depois da primeira chave.
        $abre = array_search('{', $significativos, true);
        if ($abre === false) {
            return false;   // não deu para ler: trata como não autorizando
        }

        $corpoTokens = array_slice($significativos, $abre + 1);
        if (end($corpoTokens) === '}') {
            array_pop($corpoTokens);
        }

        // Corpo vazio também não é autorização.
        return $corpoTokens !== [] && $corpoTokens !== ['return', 'true', ';'];
    }

    private function corpoDe(ReflectionFunctionAbstract $funcao): string
    {
        $arquivo = $funcao->getFileName();
        if ($arquivo === false) {
            return '';
        }

        $linhas = file($arquivo);
        $inicio = $funcao->getStartLine();
        $fim = $funcao->getEndLine();

        // De $inicio - 1 e com +1 no comprimento: inclui a linha da assinatura,
        // sem o que um método de uma linha só devolveria vazio.
        return implode('', array_slice($linhas, $inicio - 1, $fim - $inicio + 1));
    }

    /**
     * Item 1 da rodada C: prova que `autorizaViaMiddlewareCan()` agora
     * distingue permissão de INSTÂNCIA (`can:view,usuario`, que referencia o
     * parâmetro `{usuario}` da própria rota) de permissão de CLASSE
     * (`can:leads.manage`, sem argumento nenhum de instância). Antes desta
     * correção as duas passavam — bastava começar com `can:`.
     *
     * Registra duas rotas FALSAS só para este teste (a `Application` é
     * recriada a cada método de teste pelo TestCase do Laravel, então isto
     * não vaza para os outros testes desta classe nem para o resto da
     * suíte) e chama a lógica da rede diretamente sobre elas, via reflection
     * — não por uma requisição HTTP de verdade, que exigiria autenticação e
     * tenant montados só para chegar ao middleware.
     */
    public function test_can_de_classe_nao_engana_a_rede_mas_can_de_instancia_passa(): void
    {
        Route::get('probe/com-instancia/{usuario}', fn (Usuario $usuario) => null)
            ->middleware(['can:view,usuario']);

        Route::get('probe/leads/{usuario}', fn (Usuario $usuario) => null)
            ->middleware(['can:leads.manage']);

        $rotaComInstancia = null;
        $rotaDeClasse = null;
        foreach (Route::getRoutes() as $rota) {
            match ($rota->uri()) {
                'probe/com-instancia/{usuario}' => $rotaComInstancia = $rota,
                'probe/leads/{usuario}' => $rotaDeClasse = $rota,
                default => null,
            };
        }

        $this->assertNotNull($rotaComInstancia, 'rota de prova com-instancia nao foi registrada');
        $this->assertNotNull($rotaDeClasse, 'rota de prova leads (permissao de classe) nao foi registrada');

        $metodo = new ReflectionMethod($this, 'autorizaViaMiddlewareCan');
        $metodo->setAccessible(true);

        $this->assertTrue(
            $metodo->invoke($this, $rotaComInstancia),
            'can:view,usuario referencia o parametro {usuario} da rota: a rede deveria aceitar',
        );
        $this->assertFalse(
            $metodo->invoke($this, $rotaDeClasse),
            'can:leads.manage e permissao de CLASSE, sem argumento de instancia: a rede NAO deveria aceitar',
        );
    }
}
