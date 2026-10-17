<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Janela de tempo das métricas de fluxo. Objeto, não array: `hash()` precisa
 * ser canônico para a chave de cache (inicio|fim em data), e um array não
 * garante ordem de chaves.
 *
 * `mes_anterior` é o mês calendário completo anterior, não 30 dias móveis.
 */
final class Periodo
{
    public const PRESETS = ['7d', '30d', 'mes_atual', 'mes_anterior'];

    private function __construct(
        private readonly CarbonImmutable $inicio,
        private readonly CarbonImmutable $fim,
        private readonly string $preset,
    ) {}

    public static function preset(string $preset): self
    {
        $agora = Carbon::now()->toImmutable();

        return match ($preset) {
            '7d' => new self($agora->subDays(7)->startOfDay(), $agora->endOfDay(), '7d'),
            '30d' => new self($agora->subDays(30)->startOfDay(), $agora->endOfDay(), '30d'),
            'mes_atual' => new self($agora->startOfMonth(), $agora->endOfDay(), 'mes_atual'),
            'mes_anterior' => new self($agora->subMonthNoOverflow()->startOfMonth(), $agora->subMonthNoOverflow()->endOfMonth(), 'mes_anterior'),
            default => throw new InvalidArgumentException("Período desconhecido: {$preset}"),
        };
    }

    public static function customizado(CarbonInterface $inicio, CarbonInterface $fim): self
    {
        return new self(
            CarbonImmutable::instance($inicio)->startOfDay(),
            CarbonImmutable::instance($fim)->endOfDay(),
            'custom',
        );
    }

    public static function padrao(): self
    {
        return self::preset('30d');
    }

    /** A partir dos parâmetros da requisição (periodo, de, ate), com fallback no padrão. */
    public static function de(?string $preset, ?string $de = null, ?string $ate = null): self
    {
        if ($preset === 'custom' && $de && $ate) {
            return self::customizado(Carbon::parse($de), Carbon::parse($ate));
        }

        if ($preset !== null && in_array($preset, self::PRESETS, true)) {
            return self::preset($preset);
        }

        return self::padrao();
    }

    public function inicio(): CarbonImmutable
    {
        return $this->inicio;
    }

    public function fim(): CarbonImmutable
    {
        return $this->fim;
    }

    /** O código do preset ('7d', '30d', 'mes_atual', 'mes_anterior', 'custom'). */
    public function chave(): string
    {
        return $this->preset;
    }

    public function rotulo(): string
    {
        return match ($this->preset) {
            '7d' => 'Últimos 7 dias',
            '30d' => 'Últimos 30 dias',
            'mes_atual' => 'Mês atual',
            'mes_anterior' => 'Mês anterior',
            default => $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y'),
        };
    }

    public function hash(): string
    {
        return md5($this->inicio->toDateString().'|'.$this->fim->toDateString());
    }

    public function toArray(): array
    {
        return [
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->fim->toDateString(),
            'preset' => $this->preset,
            'rotulo' => $this->rotulo(),
        ];
    }
}
