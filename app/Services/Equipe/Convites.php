<?php

namespace App\Services\Equipe;

use App\Mail\ConviteDeEquipeMail;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Convidar, reenviar, cancelar e aceitar. O token só existe no e-mail; o
 * banco guarda o sha256 dele. Vale 7 dias.
 */
class Convites
{
    public const VALIDADE_DIAS = 7;

    public function convidar(User $autor, string $email, string $papel): Convite
    {
        $convite = new Convite(['email' => mb_strtolower($email), 'papel' => $papel, 'convidado_por' => $autor->id]);
        $convite->tenant_id = $autor->tenant_id;

        return $this->enviar($convite, $autor);
    }

    public function reenviar(Convite $convite, User $autor): Convite
    {
        if ($convite->aceito_em || $convite->cancelado_em) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'Este convite já foi usado ou cancelado; envie um novo.'], 422));
        }

        return $this->enviar($convite, $autor);
    }

    public function cancelar(Convite $convite): void
    {
        if ($convite->aceito_em === null && $convite->cancelado_em === null) {
            $convite->forceFill(['cancelado_em' => now()])->save();
        }
    }

    public static function porToken(string $token): ?Convite
    {
        // Sem tenant ativo (visitante): o TenantScope é ignorado aqui de
        // propósito; o hash do token é a credencial.
        return Convite::withoutGlobalScopes()->with('tenant')->where('token_hash', hash('sha256', $token))->first();
    }

    public static function estado(Convite $convite): string
    {
        return match (true) {
            $convite->aceito_em !== null => 'usado',
            $convite->cancelado_em !== null => 'cancelado',
            $convite->expira_em->isPast() => 'expirado',
            default => 'pendente',
        };
    }

    public function aceitar(Convite $convite, string $nome, string $senha): User
    {
        if (User::where('email', $convite->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este e-mail já tem uma conta. Entre com ela ou peça um convite para outro endereço.']);
        }

        return DB::transaction(function () use ($convite, $nome, $senha) {
            $user = User::create([
                'name' => $nome,
                'email' => $convite->email,
                'password' => Hash::make($senha),
                'tenant_id' => $convite->tenant_id,
            ]);
            $user->forceFill(['ativo' => true, 'email_verified_at' => now()])->save();

            setPermissionsTeamId($convite->tenant_id);
            $user->assignRole($convite->papel);

            $convite->forceFill(['aceito_em' => now()])->save();

            return $user;
        });
    }

    private function enviar(Convite $convite, User $autor): Convite
    {
        $token = Str::random(48);
        $convite->token_hash = hash('sha256', $token);
        $convite->expira_em = now()->addDays(self::VALIDADE_DIAS);
        $convite->save();

        Mail::to($convite->email)->send(new ConviteDeEquipeMail($convite, $token, $autor->tenant->nome, $autor->name));

        return $convite;
    }
}
