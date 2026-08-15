<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

class Tags extends Model
{
    use HasFactory, Notifiable, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'id',
        'descricao',
        'ordem',
        'tenant_id'
    ];
}
