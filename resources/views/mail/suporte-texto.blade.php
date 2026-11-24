{{ config('app.name') }} · Suporte #{{ $protocolo }} · {{ $quando }}
{{ $tipo }}: {{ $area }}
@if ($impacto)
Impacto: {{ $impacto }}
@endif

O que a pessoa escreveu:
{!! $mensagem !!}

Nome: {{ $nome }}
E-mail da conta: {{ $emailDaConta }}
E-mail para contato: {{ $emailDeContato }}
Empresa: {{ $empresa }}
@if ($plano)
Plano: {{ $plano }}
@endif
@if ($nota !== null)
Nota (0 a 10): {{ $nota }}
@endif
@if ($pagina)
Página: {{ $pagina }}
@endif
@if ($navegador)
Navegador: {{ $navegador }}
@endif
