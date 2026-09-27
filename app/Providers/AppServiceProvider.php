<?php

namespace App\Providers;

use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use App\Models\TarefaPadrao;
use App\Models\Usuario;
use App\Observers\AnotacaoObserver;
use App\Observers\ArquivoObserver;
use App\Observers\ProjetoAnexoObserver;
use App\Observers\ProjetoAnotacaoObserver;
use App\Observers\ProjetoObserver;
use App\Observers\TarefaObserver;
use App\Observers\UsuarioObserver;
use App\Policies\AnotacaoPolicy;
use App\Policies\ArquivoPolicy;
use App\Policies\ProjetoAnexoPolicy;
use App\Policies\ProjetoAnotacaoPolicy;
use App\Policies\ProjetoPolicy;
use App\Policies\TarefaPolicy;
use App\Policies\TarefaPadraoPolicy;
use App\Policies\UsuarioPolicy;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Alimentam o activity log de lead. Observers só disparam em operações
        // de model: uma escrita via query builder (Usuario::where(...)->update())
        // passaria despercebida. Não há esse caminho nos controllers hoje.
        Usuario::observe(UsuarioObserver::class);
        Anotacao::observe(AnotacaoObserver::class);
        arquivo::observe(ArquivoObserver::class);
        Projeto::observe(ProjetoObserver::class);
        ProjetoAnotacao::observe(ProjetoAnotacaoObserver::class);
        ProjetoAnexo::observe(ProjetoAnexoObserver::class);
        Tarefa::observe(TarefaObserver::class);

        // Registro explícito e não por convenção: a descoberta automática do
        // Laravel deriva o nome da classe, e o model `arquivo` é minúsculo —
        // ela procuraria `arquivoPolicy`. Explícito evita depender disso.
        Gate::policy(Usuario::class, UsuarioPolicy::class);
        Gate::policy(Projeto::class, ProjetoPolicy::class);
        Gate::policy(ProjetoAnotacao::class, ProjetoAnotacaoPolicy::class);
        Gate::policy(ProjetoAnexo::class, ProjetoAnexoPolicy::class);
        Gate::policy(Tarefa::class, TarefaPolicy::class);
        Gate::policy(Anotacao::class, AnotacaoPolicy::class);
        Gate::policy(arquivo::class, ArquivoPolicy::class);
        Gate::policy(TarefaPadrao::class, TarefaPadraoPolicy::class);
    }
}
