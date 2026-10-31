<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed`: papéis e permissões, e a configuração padrão de
 * todas as empresas (seguro em produção; só cria o que falta). No ambiente
 * local, também a empresa de demonstração.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PapeisEPermissoesSeeder::class,
            ConfiguracaoPadraoSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemonstracaoSeeder::class);
        }
    }
}
