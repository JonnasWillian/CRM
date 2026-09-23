<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base mínima de RBAC: os papéis e as permissões do spec de autorização, e a
 * atribuição inicial de `admin`.
 *
 * Só `configuracoes.manage` é efetivamente usada nesta entrega — ela protege a
 * tela de funis. As outras cinco entram junto porque o conjunto é o contrato
 * descrito em docs/superpowers/specs/2026-09-15-autorizacao-rbac-design.md, e
 * semear metade dele agora obrigaria a segunda migração a conviver com papéis
 * já atribuídos.
 *
 * Papéis e permissões são GLOBAIS (`tenant_id = null` em `roles`). No modo
 * teams do pacote, papel global é atribuível a qualquer team e evita duplicar a
 * definição por empresa. O que é por tenant é a *atribuição*, que carrega
 * `tenant_id` em `model_has_roles`.
 *
 * ── Quem recebe admin ──
 *
 * Sem esta parte a feature subiria inacessível: `configuracoes.manage` existiria
 * e ninguém a teria. O critério é o primeiro usuário de cada tenant (menor id),
 * que é quem passou pelo /register e criou a empresa. Usuários seguintes ficam
 * sem papel — o que hoje não os limita em nada além desta tela, já que as
 * policies do spec de RBAC ainda não entraram.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'leads.view-all',
            'leads.manage',
            'projetos.manage',
            'tarefas.manage',
            'configuracoes.manage',
            'agentes.manage',
        ];

        $papeis = [
            'admin' => $permissions,
            'gestor' => ['leads.view-all', 'leads.manage', 'projetos.manage', 'tarefas.manage', 'configuracoes.manage'],
            'vendedor' => ['leads.manage', 'projetos.manage', 'tarefas.manage'],
        ];

        $agora = now();

        $permissionIds = [];
        foreach ($permissions as $nome) {
            $permissionIds[$nome] = DB::table('permissions')->insertGetId([
                'name' => $nome,
                'guard_name' => 'web',
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        $roleIds = [];
        foreach ($papeis as $papel => $suasPermissions) {
            $roleIds[$papel] = DB::table('roles')->insertGetId([
                'name' => $papel,
                'guard_name' => 'web',
                'tenant_id' => null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            foreach ($suasPermissions as $nome) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionIds[$nome],
                    'role_id' => $roleIds[$papel],
                ]);
            }
        }

        // Primeiro usuário de cada tenant vira admin.
        $primeiros = DB::table('users')
            ->selectRaw('tenant_id, MIN(id) as user_id')
            ->groupBy('tenant_id')
            ->get();

        foreach ($primeiros as $linha) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleIds['admin'],
                'model_type' => \App\Models\User::class,
                'model_id' => $linha->user_id,
                'tenant_id' => $linha->tenant_id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
