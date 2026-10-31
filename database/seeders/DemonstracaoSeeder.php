<?php

namespace Database\Seeders;

use App\Models\Anotacao;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Projeto;
use App\Models\ProjetoAditivo;
use App\Models\StatusProjeto;
use App\Models\Tarefa;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Perdas\AplicarTransicao;
use App\Services\TenantBootstrapper;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Empresa de demonstração para desenvolvimento e apresentações: um usuário
 * por papel e dados que acendem todas as telas — pipeline com vários
 * estágios, ganho com valor fechado, perda com motivo, contrato recorrente
 * com renovação, tarefas atrasadas, de hoje, futuras e concluídas.
 *
 * Dados fixos (sem Faker): o mesmo resultado em toda máquina, e funciona
 * sem as dependências de desenvolvimento. Nunca roda em produção. Se a
 * empresa de demonstração já existe, não faz nada; para recriar, apague-a.
 *
 *   php artisan db:seed --class=DemonstracaoSeeder
 */
class DemonstracaoSeeder extends Seeder
{
    public const SLUG = 'demonstracao';

    public const SENHA = 'demonstracao';

    /** @var array<string, array{0:string, 1:string}> e-mail => [nome, papel] */
    public const USUARIOS = [
        'admin@demo.test' => ['Ana Ribeiro', 'admin'],
        'gestor@demo.test' => ['Bruno Costa', 'gestor'],
        'vendedor@demo.test' => ['Carla Mendes', 'vendedor'],
    ];

    public function run(CurrentTenant $atual, Assinaturas $assinaturas, AplicarTransicao $transicao): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Demonstração não roda em produção.');

            return;
        }
        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->command?->info('A empresa de demonstração já existe. Para recriar, apague-a e rode de novo.');

            return;
        }
        if ($emUso = User::whereIn('email', array_keys(self::USUARIOS))->value('email')) {
            $this->command?->warn("O e-mail {$emUso} já está em uso em outra empresa. Demonstração não criada.");

            return;
        }

        $this->call(PapeisEPermissoesSeeder::class);
        $time = getPermissionsTeamId();

        try {
            DB::transaction(function () use ($atual, $assinaturas, $transicao) {
                $tenant = Tenant::create(['nome' => 'Agência Demonstração', 'slug' => self::SLUG]);
                TenantBootstrapper::bootstrap($tenant);
                $atual->set($tenant);
                setPermissionsTeamId($tenant->id);
                $assinaturas->iniciarTeste($tenant, 'profissional');

                $usuarios = $this->usuarios($tenant);
                $this->dados($usuarios, $transicao);
            });
        } finally {
            Auth::guard('web')->forgetUser();
            $atual->clear();
            setPermissionsTeamId($time);
        }

        $this->command?->info('Empresa de demonstração criada. Entre com '.implode(', ', array_keys(self::USUARIOS)).' e a senha "'.self::SENHA.'".');
    }

    /** @return array<string, User> papel => usuário */
    private function usuarios(Tenant $tenant): array
    {
        $porPapel = [];
        foreach (self::USUARIOS as $email => [$nome, $papel]) {
            $user = new User(['name' => $nome, 'email' => $email, 'password' => Hash::make(self::SENHA)]);
            $user->forceFill(['tenant_id' => $tenant->id, 'ativo' => true, 'email_verified_at' => now()])->save();
            $user->assignRole($papel);
            $porPapel[$papel] = $user;
        }

        return $porPapel;
    }

    /** @param  array<string, User>  $u */
    private function dados(array $u, AplicarTransicao $transicao): void
    {
        $hoje = Carbon::today();
        $funil = Funil::padrao();
        $estagios = Estagio::where('funil_id', $funil->id)->orderBy('ordem')->get();
        $abertos = $estagios->where('tipo', Estagio::TIPO_ABERTO)->values(); // captação, negociação, desenvolvimento, pausado
        $ganho = $estagios->firstWhere('tipo', Estagio::TIPO_GANHO);
        $perdido = $estagios->firstWhere('tipo', Estagio::TIPO_PERDIDO);

        // [nome, e-mail, telefone, estágio, responsável, valor estimado, probabilidade, previsão em dias, dias desde o cadastro]
        $leads = [
            ['Clínica Vitale', 'contato@vitale.test', '+5511987650001', $abertos[0], 'vendedor', 12000, 30, 20, 3],
            ['Loja Horizonte', 'compras@horizonte.test', '+5511987650002', $abertos[0], 'vendedor', 3200, 20, 35, 21],
            ['Studio Pilates Equilíbrio', 'studio@equilibrio.test', '+5511987650003', $abertos[0], 'gestor', null, null, null, 1],
            ['Nexa Studio', 'ola@nexa.test', '+5511987650004', $abertos[1], 'vendedor', 4800, 60, 10, 12],
            ['Padaria Trigal', 'gerencia@trigal.test', '+5511987650005', $abertos[1], 'gestor', 1800, 50, 5, 9],
            ['Restaurante Sabor Caseiro', 'contato@saborcaseiro.test', '+5511987650006', $abertos[1], 'vendedor', 3500, 40, -3, 30],
            ['Construtora Alicerce', 'obras@alicerce.test', '+5511987650007', $abertos[2], 'gestor', 25000, 80, 7, 40],
            ['Escola Aprender', 'secretaria@aprender.test', '+5511987650008', $abertos[2], 'vendedor', 9000, 70, 15, 25],
            ['Pet Shop Amigo Fiel', 'petshop@amigofiel.test', '+5511987650009', $abertos[3], 'vendedor', 2500, 10, null, 45],
            ['Agência Prisma', 'financeiro@prisma.test', '+5511987650010', $abertos[2], 'gestor', 2400, 90, 2, 60],
            ['Ótica Visão Clara', 'contato@visaoclara.test', '+5511987650011', $abertos[1], 'vendedor', 6000, 50, 12, 18],
        ];

        $criados = [];
        foreach ($leads as [$nome, $email, $telefone, $estagio, $dono, $valor, $prob, $previsao, $idade]) {
            $criados[$nome] = $this->como($u[$dono], function () use ($nome, $email, $telefone, $estagio, $funil, $u, $dono, $valor, $prob, $previsao, $hoje) {
                $lead = new Usuario([
                    'nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'user_id' => $u[$dono]->id,
                    'funil_id' => $funil->id, 'estagio_id' => $estagio->id, 'valor_estimado' => $valor, 'probabilidade' => $prob,
                    'previsao_fechamento' => $previsao === null ? null : $hoje->copy()->addDays($previsao)->toDateString(),
                ]);
                $lead->save();

                return $lead;
            });
            // Idade do cadastro: deixa alguns leads "sem contato há +14 dias".
            DB::table('usuarios')->where('id', $criados[$nome]->id)->update(['created_at' => now()->subDays($idade), 'updated_at' => now()->subDays($idade)]);
        }

        // Ganho: Agência Prisma fecha um contrato mensal.
        $this->como($u['gestor'], fn () => $criados['Agência Prisma']->fill(['estagio_id' => $ganho->id, 'valor_fechado' => 28800])->save());

        // Perda com motivo: Ótica Visão Clara escolheu pelo preço.
        $preco = MotivoPerda::where('descricao', 'Preço')->first();
        $this->como($u['vendedor'], fn () => $transicao($criados['Ótica Visão Clara'], ['estagio_id' => $perdido->id], [
            'motivo_perda_id' => $preco->id, 'observacao' => 'Fechou com um concorrente 20% mais barato.',
        ]));

        // Projetos: dois únicos em andamento e um contrato recorrente com renovação.
        $status = StatusProjeto::orderBy('ordem')->get()->keyBy('descricao');
        $this->como($u['vendedor'], fn () => Projeto::create(['nome' => 'Site institucional', 'descricao' => 'Site com 6 páginas e blog.', 'tipo' => 'unico', 'preco' => 4800, 'usuario_id' => $criados['Nexa Studio']->id, 'status_id' => $status['Em analise']->id]));
        $this->como($u['gestor'], fn () => Projeto::create(['nome' => 'Projeto executivo', 'descricao' => 'Plantas e memorial descritivo.', 'tipo' => 'unico', 'preco' => 25000, 'usuario_id' => $criados['Construtora Alicerce']->id, 'status_id' => $status['Em desenvolvimento']->id]));
        $this->como($u['gestor'], function () use ($criados, $status, $hoje, $u) {
            $contrato = Projeto::create([
                'nome' => 'Gestão de tráfego mensal', 'descricao' => 'Campanhas e relatório mensal.', 'tipo' => 'recorrente',
                'valor_mensal' => 2400, 'duracao_meses' => 12, 'data_inicial' => $hoje->copy()->subMonths(3)->startOfMonth()->toDateString(),
                'usuario_id' => $criados['Agência Prisma']->id, 'status_id' => $status['Em desenvolvimento']->id,
            ]);
            $aditivo = new ProjetoAditivo(['projeto_id' => $contrato->id, 'tipo' => 'renovacao', 'meses' => 6, 'valor_mensal' => 2600, 'observacao' => 'Renovação com reajuste.', 'user_id' => $u['gestor']->id]);
            $aditivo->tenant_id = $contrato->tenant_id;
            $aditivo->save();
            $contrato->save(); // recalcula total e fim do contrato
        });

        // Tarefas de cada situação.
        $tarefas = [
            ['Clínica Vitale', 'Ligar para confirmar a reunião', -2, false],
            ['Nexa Studio', 'Enviar proposta revisada', 0, false],
            ['Construtora Alicerce', 'Reunião de alinhamento do projeto', 3, false],
            ['Escola Aprender', 'Mandar estudo de caso', 6, false],
            ['Agência Prisma', 'Enviar contrato para assinatura', -10, true],
        ];
        foreach ($tarefas as [$lead, $titulo, $dias, $concluida]) {
            $dono = $criados[$lead]->user_id === $u['gestor']->id ? $u['gestor'] : $u['vendedor'];
            $this->como($dono, fn () => Tarefa::create([
                'usuario_id' => $criados[$lead]->id, 'titulo' => $titulo, 'data_limite' => $hoje->copy()->addDays($dias)->toDateString(),
                'concluido' => $concluida, 'concluido_em' => $concluida ? now()->subDays(abs($dias)) : null,
            ]));
        }

        // Anotações (também atualizam o último contato do lead).
        $this->como($u['vendedor'], fn () => Anotacao::create(['usuario_id' => $criados['Nexa Studio']->id, 'descricao' => 'Pediu 10% de desconto para fechar ainda este mês.']));
        $this->como($u['gestor'], fn () => Anotacao::create(['usuario_id' => $criados['Construtora Alicerce']->id, 'descricao' => 'Reunião feita; aguardando aprovação da diretoria.']));

        // Modelos de tarefa do admin.
        foreach ([['Ligar em 3 dias', 3], ['Enviar proposta', 1], ['Follow-up da proposta', 7]] as [$titulo, $prazo]) {
            TarefaPadrao::create(['user_id' => $u['admin']->id, 'titulo' => $titulo, 'prazo_dias' => $prazo]);
        }
    }

    /** Executa como `$user`, para o histórico registrar quem fez. */
    private function como(User $user, callable $acao): mixed
    {
        Auth::guard('web')->setUser($user);

        return $acao();
    }
}
