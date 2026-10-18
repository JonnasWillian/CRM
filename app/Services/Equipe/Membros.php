<?php

namespace App\Services\Equipe;

use App\Models\User;
use App\Services\Leads\TransferirLeads;
use App\Support\Equipe\RegrasDeAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Papel, desativação e reativação de membros. Recusas como 422 com `message`. */
class Membros
{
    public const PAPEIS = [
        ['valor' => 'admin', 'rotulo' => 'Admin', 'descricao' => 'Tudo: equipe, configurações, todos os leads e relatórios.'],
        ['valor' => 'gestor', 'rotulo' => 'Gestor', 'descricao' => 'Vê e transfere todos os leads, configura funis e vê relatórios.'],
        ['valor' => 'vendedor', 'rotulo' => 'Vendedor', 'descricao' => 'Vê e trabalha só os próprios leads.'],
    ];

    public function __construct(private readonly TransferirLeads $transferir) {}

    public function alterarPapel(User $autor, User $membro, string $papel): void
    {
        if ($autor->is($membro)) {
            throw self::recusar('Você não pode mudar o seu próprio papel.');
        }
        if ($papel !== 'admin' && RegrasDeAdmin::ehUltimoAdmin($membro)) {
            throw self::recusar('A empresa precisa de pelo menos um admin ativo.');
        }

        setPermissionsTeamId($membro->tenant_id);
        $membro->syncRoles([$papel]);
    }

    public function desativar(User $autor, User $membro, ?User $destino): void
    {
        if ($autor->is($membro)) {
            throw self::recusar('Você não pode desativar a própria conta.');
        }
        if (RegrasDeAdmin::ehUltimoAdmin($membro)) {
            throw self::recusar('A empresa precisa de pelo menos um admin ativo.');
        }

        $temLeads = $membro->leads()->withTrashed()->exists();
        if ($temLeads && $destino === null) {
            throw ValidationException::withMessages(['transferir_para' => "{$membro->name} ainda tem leads. Escolha para quem transferir a carteira."]);
        }
        if ($destino !== null && $destino->is($membro)) {
            throw ValidationException::withMessages(['transferir_para' => 'Escolha outra pessoa para receber a carteira.']);
        }

        DB::transaction(function () use ($membro, $destino, $temLeads) {
            if ($temLeads) {
                $this->transferir->carteira($membro, $destino);
            }
            $membro->forceFill(['ativo' => false, 'desativado_em' => now()])->save();
            // Derruba as sessões abertas: o IdentifyTenant já barraria a
            // próxima requisição, mas sessão morta é sessão que não vaza.
            DB::table('sessions')->where('user_id', $membro->id)->delete();
        });
    }

    public function reativar(User $membro): void
    {
        $membro->forceFill(['ativo' => true, 'desativado_em' => null])->save();
    }

    private static function recusar(string $mensagem): ValidationException
    {
        return ValidationException::withMessages(['membro' => $mensagem]);
    }
}
