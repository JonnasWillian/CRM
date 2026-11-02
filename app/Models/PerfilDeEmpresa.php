<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respostas do questionário de primeiro acesso, uma linha por empresa. A
 * linha nasce pendente no cadastro; respondida ou pulada, está resolvida e
 * o questionário não volta. Empresa sem linha (anterior ao questionário)
 * nunca é levada a ele.
 */
class PerfilDeEmpresa extends Model
{
    protected $table = 'perfis_de_empresa';

    protected $fillable = ['tenant_id'];

    protected function casts(): array
    {
        return [
            'respostas' => 'array',
            'respondido_em' => 'datetime',
            'pulado_em' => 'datetime',
            'aceite_dicas_em' => 'datetime',
            'aceita_dicas_whatsapp' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resolvido(): bool
    {
        return $this->respondido_em !== null || $this->pulado_em !== null;
    }

    public static function daEmpresa(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->first();
    }

    /** Só a linha criada no cadastro e ainda sem resposta leva ao questionário. */
    public static function pendente(int $tenantId): bool
    {
        return static::where('tenant_id', $tenantId)->whereNull('respondido_em')->whereNull('pulado_em')->exists();
    }
}
