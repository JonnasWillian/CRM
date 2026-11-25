<x-mail::message>
# Você foi convidado

{{-- Nomes escritos pelo cliente: já vêm com o Markdown neutralizado (TextoSeguro). --}}
{{ $convidadoPorSeguro }} convidou você para entrar no CRM da **{{ $empresaSegura }}** como **{{ $papel }}**.

<x-mail::button :url="$url">
Criar meu acesso
</x-mail::button>

O convite vale até {{ $expira }}. Se você não esperava este e-mail, pode ignorá-lo.

{{ config('app.name') }}
</x-mail::message>
