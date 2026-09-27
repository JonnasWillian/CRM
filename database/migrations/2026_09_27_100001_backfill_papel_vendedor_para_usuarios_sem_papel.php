<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Backfill: todo usuário sem papel nenhum vira `vendedor`.
 *
 * ── Por que esta migration existe ──
 *
 * 2026_09_22_100008_seed_roles_and_permissions deu `admin` só ao primeiro
 * usuário de cada tenant e deixou os seguintes sem papel, com o comentário
 * "o que hoje não os limita em nada além desta tela, já que as policies do
 * spec de RBAC ainda não entraram". As policies entraram. A frase envelheceu
 * e virou um buraco: no banco de desenvolvimento, 1 dos 2 usuários está sem
 * papel.
 *
 * O efeito medido, com o usuário sem papel e o PRÓPRIO lead dele:
 *
 *   GET  /leads/{id}           -> 200   (a tela abre)
 *   PUT  /api/usuarios/{id}    -> 404   ("o lead sumiu" ao salvar)
 *   POST /api/tarefas          -> 201   (mas criar tarefa nesse lead funciona)
 *   POST /api/anotacao         -> 201   (e anotar também)
 *
 * Quem não tem `leads.manage` é barrado pelo `authorize()` de classe do
 * UsuarioRequest; quem não tem `tarefas.manage` não é barrado por nada, porque
 * tarefa e anotação só checam a policy de INSTÂNCIA do lead, e o lead é dele.
 * O resultado não é "conta restrita": é uma conta incoerente.
 *
 * ── Por que só o backfill basta ──
 *
 * O único caminho que cria usuário nesta base é RegisteredUserController, e ele
 * sempre cria tenant + `admin` na mesma transação. Não há fluxo produzindo
 * novos órfãos, então não é preciso um default no model nem um observer.
 *
 * ATENÇÃO para quem for implementar a tela de convite de agentes
 * (`agentes.manage` já está semeada e sem uso): ela abre um segundo caminho de
 * criação de usuário e PRECISA atribuir papel no ato da criação. Sem isso, esta
 * migration passa a ser um remendo que só conserta o passado.
 *
 * ── Modo teams ──
 *
 * `team_foreign_key => tenant_id`. O papel é GLOBAL (`roles.tenant_id = null`);
 * o que é por empresa é a ATRIBUIÇÃO, que carrega `tenant_id` em
 * `model_has_roles`. Por isso a linha inserida leva o `tenant_id` do próprio
 * usuário, e não o do papel.
 *
 * Estilo `DB` facade e não Eloquent, como a migration de seed: uma migration
 * não pode depender do estado atual dos models — TenantScope é fail-closed e
 * derrubaria isto por não haver tenant ativo durante `artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $vendedorId = DB::table('roles')
            ->where('name', 'vendedor')
            ->where('guard_name', 'web')
            ->whereNull('tenant_id')
            ->value('id');

        // Sem o papel semeado não há o que atribuir. Acontece num banco em que
        // o seed de RBAC foi revertido — mas também acontece, em silêncio, se
        // o papel `vendedor` for RENOMEADO (ex.: para `consultor`): a busca
        // acima não acha nada com esse nome, `$vendedorId` fica null, e esta
        // migration passa sem atribuir nada a ninguém, deixando de pé
        // exatamente a conta incoerente que ela existe para fechar. Falhar
        // aqui (lançando) atrapalharia mais do que ajudaria — um `migrate`
        // legítimo sem seed ainda rodado pararia a build — então o log de
        // aviso é o que resta para o rename não passar despercebido.
        if ($vendedorId === null) {
            logger()->warning(
                'Backfill de papel vendedor pulado: papel "vendedor" (guard web, tenant_id null) '.
                'não foi encontrado em roles. Nenhum usuário sem papel foi corrigido nesta execução '.
                '— banco sem o seed de RBAC rodado ainda, ou o papel foi renomeado.'
            );

            return;
        }

        // whereColumn tenant_id: no modo teams, a atribuição em
        // model_has_roles carrega tenant_id (ver "Modo teams" acima). Sem
        // filtrar por ele aqui, um usuário com papel em OUTRO tenant contaria
        // como "já tem papel" e seria pulado, ficando sem atribuição no
        // tenant dele — inalcançável hoje porque `users.tenant_id` é NOT NULL
        // e único por usuário (não há usuário compartilhado entre tenants),
        // mas a subquery fica correta por conta própria, sem depender dessa
        // garantia externa.
        $orfaos = DB::table('users')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->whereColumn('model_has_roles.tenant_id', 'users.tenant_id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->get(['id', 'tenant_id']);

        foreach ($orfaos as $usuario) {
            // insertOrIgnore além do whereNotExists: idempotência de cinto e
            // suspensório, caso a migration rode duas vezes em paralelo.
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $vendedorId,
                'model_type' => User::class,
                'model_id' => $usuario->id,
                'tenant_id' => $usuario->tenant_id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sem reversão, e o motivo é honesto: não há como distinguir uma atribuição
     * feita por este backfill de uma feita por um humano depois dele.
     *
     * Um `down()` que apagasse todo `model_has_roles` de `vendedor` rebaixaria
     * também quem recebeu o papel legitimamente — e, pior, reabriria
     * exatamente a incoerência que o `up()` fechou, em silêncio. Gravar quais
     * linhas foram inseridas exigiria uma tabela de controle que só existiria
     * para servir a um rollback que ninguém pretende executar.
     *
     * Para desfazer de propósito num ambiente específico, revogue o papel dos
     * usuários em questão pela aplicação, que é onde a decisão tem contexto.
     */
    public function down(): void
    {
        // Intencionalmente vazio. Ver o bloco acima.
    }
};
