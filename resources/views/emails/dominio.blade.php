<p>
    @if($acao == 'solicitado')
        <b>{{ $quem }}</b> solicitou o domínio <b>{{ $dominio->fqdn() }}</b>.
    @elseif($revogado)
        A aprovação do domínio <b>{{ $dominio->fqdn() }}</b> foi <b>revogada</b> por <b>{{ $quem }}</b>.
        O domínio deixou de ser publicado.
    @else
        O domínio <b>{{ $dominio->fqdn() }}</b> foi <b>{{ $acao }}</b> por <b>{{ $quem }}</b>.
    @endif
</p>

<p>
    Domínio: {{ $dominio->fqdn() }} (publicado também como www.{{ $dominio->fqdn() }}) <br>
    IP de apontamento: {{ $dominio->ip }} <br>
    Solicitante: {{ $dominio->user?->name }} <br>
    Motivo do pedido: <i>{{ $dominio->motivo }}</i>
</p>

@if($mensagem)
    <p>
        Explicação de {{ $quem }}:<br>
        <i>{{ $mensagem }}</i>
    </p>
@endif

<p><a href="{{ route('dominios.show', $dominio) }}">Ver a solicitação</a></p>

<p>Faculdade de Filosofia, Letras e Ciências Humanas - USP</p>
