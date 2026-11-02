<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aviso recebido de um gateway de pagamento. `evento_id` é único por
 * gateway: o reenvio do mesmo aviso não repete a regra de negócio.
 * Sem tenant: o aviso chega antes de se saber de quem é.
 */
class EventoDeGateway extends Model
{
    protected $table = 'eventos_de_gateway';

    protected $fillable = ['gateway', 'evento_id', 'tipo', 'cobranca_id', 'payload', 'processado_em', 'resultado'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processado_em' => 'datetime',
        ];
    }
}
