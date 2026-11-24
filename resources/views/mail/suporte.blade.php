{{-- Chamado do assistente de ajuda. Blade comum, tudo escapado: o texto do cliente nunca vira HTML nem Markdown. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Suporte #{{ $protocolo }}</title></head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1b2230;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dfe4ec;border-radius:12px;">
<tr><td style="padding:24px 28px 8px;">
    <p style="margin:0;font-size:12px;color:#5f6b80;">{{ config('app.name') }} · Suporte #{{ $protocolo }} · {{ $quando }}</p>
    <h1 style="margin:6px 0 0;font-size:20px;">{{ $tipo }}: {{ $area }}</h1>
    @if ($impacto)
        <p style="margin:8px 0 0;font-size:14px;"><strong>Impacto:</strong> {{ $impacto }}</p>
    @endif
</td></tr>
<tr><td style="padding:12px 28px;">
    <p style="margin:0 0 6px;font-size:12px;color:#5f6b80;">O que a pessoa escreveu</p>
    <div style="padding:14px 16px;background:#f0f3f7;border-radius:10px;font-size:14px;line-height:1.55;">{!! nl2br(e($mensagem)) !!}</div>
</td></tr>
<tr><td style="padding:8px 28px 24px;font-size:14px;line-height:1.7;">
    <strong>Nome:</strong> {{ $nome }}<br>
    <strong>E-mail da conta:</strong> {{ $emailDaConta }}<br>
    <strong>E-mail para contato:</strong> {{ $emailDeContato }}@if ($emailDeContato !== $emailDaConta) (diferente do da conta)@endif<br>
    <strong>Empresa:</strong> {{ $empresa }}<br>
    @if ($plano)<strong>Plano:</strong> {{ $plano }}<br>@endif
    @if ($nota !== null)<strong>Nota (0 a 10):</strong> {{ $nota }}<br>@endif
    @if ($pagina)<strong>Página:</strong> {{ $pagina }}<br>@endif
    @if ($navegador)<strong>Navegador:</strong> {{ $navegador }}<br>@endif
    <p style="margin:14px 0 0;font-size:12px;color:#5f6b80;">Responder este e-mail fala direto com a pessoa, no e-mail de contato.</p>
</td></tr>
</table>
</body>
</html>
