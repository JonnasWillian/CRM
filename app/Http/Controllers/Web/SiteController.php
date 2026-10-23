<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Site público: HTML pronto no servidor (Blade, sem Inertia), que é o que
 * o Google indexa. Quem já está logado não vê site: vai para o dashboard.
 */
class SiteController extends Controller
{
    public function inicio(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('site.inicio', $this->base() + [
            'titulo' => config('app.name').' · CRM de vendas com pipeline e receita recorrente',
            'descricao' => config('comercial.descricao'),
            'caminho' => '/',
            'jsonLd' => [$this->organizacao(), $this->aplicativo(), $this->faqPage()],
        ]);
    }

    public function precos(): View
    {
        return view('site.precos', $this->base() + [
            'titulo' => 'Planos e preços · '.config('app.name'),
            'descricao' => 'Planos por usuário, em reais, com '.config('comercial.dias_de_teste').' dias grátis e sem cartão. Escolha entre Inicial, Profissional e Empresa.',
            'caminho' => '/precos',
            'jsonLd' => [$this->aplicativo()],
        ]);
    }

    public function termos(): View
    {
        return view('site.termos', $this->base() + ['titulo' => 'Termos de uso · '.config('app.name'), 'descricao' => 'Condições de uso do '.config('app.name').'.', 'caminho' => '/termos', 'jsonLd' => []]);
    }

    public function privacidade(): View
    {
        return view('site.privacidade', $this->base() + ['titulo' => 'Política de privacidade · '.config('app.name'), 'descricao' => 'Como o '.config('app.name').' trata dados pessoais, conforme a LGPD.', 'caminho' => '/privacidade', 'jsonLd' => []]);
    }

    /** Cartão 1200×630 para a imagem de compartilhamento (public/og.png é uma captura desta página). */
    public function og(): View
    {
        return view('site.og', $this->base());
    }

    public function robots(): Response
    {
        $linhas = [
            'User-agent: *',
            'Allow: /',
            ...array_map(fn ($p) => "Disallow: {$p}", ['/dashboard', '/leads', '/kanban', '/tarefas', '/relatorios', '/configuracoes', '/lixeira', '/profile', '/login', '/register', '/forgot-password', '/reset-password', '/convites', '/api/', '/og-card']),
            '',
            'Sitemap: '.$this->url('/sitemap.xml'),
        ];

        return response(implode("\n", $linhas)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $hoje = Carbon::now()->toDateString();
        $urls = collect([['/', '1.0', 'weekly'], ['/precos', '0.9', 'monthly'], ['/termos', '0.3', 'yearly'], ['/privacidade', '0.3', 'yearly']])
            ->map(fn ($u) => "  <url><loc>{$this->url($u[0])}</loc><lastmod>{$hoje}</lastmod><changefreq>{$u[2]}</changefreq><priority>{$u[1]}</priority></url>")
            ->implode("\n");

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** URL absoluta no domínio público (APP_URL), não no host da requisição. */
    public static function url(string $caminho = '/'): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($caminho, '/');
    }

    private function base(): array
    {
        return [
            'nome' => config('app.name'),
            'url' => fn (string $c = '/') => self::url($c),
            'planos' => config('comercial.planos'),
            'faq' => array_map(fn ($f) => [...$f, 'resposta' => str_replace(':dias', (string) config('comercial.dias_de_teste'), $f['resposta'])], config('comercial.faq')),
            'contato' => config('comercial.contato'),
            'empresa' => config('comercial.empresa'),
            'diasDeTeste' => config('comercial.dias_de_teste'),
        ];
    }

    private function organizacao(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('app.name'), 'url' => self::url('/'), 'logo' => self::url('/favicon.svg'), 'email' => config('comercial.contato.email')];
    }

    private function aplicativo(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('app.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'pt-BR',
            'description' => config('comercial.descricao'),
            'url' => self::url('/'),
            'offers' => array_map(fn ($p) => [
                '@type' => 'Offer',
                'name' => $p['nome'],
                'price' => number_format($p['preco_mensal'], 2, '.', ''),
                'priceCurrency' => 'BRL',
                'url' => self::url('/precos'),
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => number_format($p['preco_mensal'], 2, '.', ''), 'priceCurrency' => 'BRL', 'unitText' => 'usuário por mês'],
            ], config('comercial.planos')),
        ];
    }

    private function faqPage(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['pergunta'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => str_replace(':dias', (string) config('comercial.dias_de_teste'), $f['resposta'])]], config('comercial.faq')),
        ];
    }
}
