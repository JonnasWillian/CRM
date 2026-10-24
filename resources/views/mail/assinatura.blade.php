<x-mail::message>
# {{ $titulo }}

@foreach ($paragrafos as $paragrafo)
{{ $paragrafo }}

@endforeach
@if ($botao)
<x-mail::button :url="$botao['url']">
{{ $botao['rotulo'] }}
</x-mail::button>
@endif

{{ config('app.name') }}
</x-mail::message>
