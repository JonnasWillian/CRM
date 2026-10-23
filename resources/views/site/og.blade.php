<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Cartão de compartilhamento</title>
    @vite(['resources/css/app.css'])
    <style>html, body { margin: 0; } body { width: 1200px; height: 630px; overflow: hidden; }</style>
</head>
<body class="bg-bg-0 font-sans text-fg-0" data-theme="claro">
    <div class="flex h-[630px] w-[1200px] flex-col justify-between p-16" style="background: linear-gradient(135deg, #f4f6f9 0%, #e8eff7 100%);">
        <p class="font-display text-4xl font-bold tracking-tight">{{ $nome }}<span class="text-primary">.</span></p>
        <div>
            <h1 class="max-w-4xl font-display text-6xl font-bold leading-tight tracking-tight">O CRM de vendas que mostra o que você <span class="text-primary">já fatura</span> e o que ainda vai entrar.</h1>
            <p class="mt-6 text-2xl text-fg-1">Pipeline · Equipe · Importação · Contratos recorrentes com MRR</p>
        </div>
        <p class="text-xl text-fg-2">Em português, em reais. {{ $diasDeTeste }} dias grátis, sem cartão.</p>
    </div>
</body>
</html>
