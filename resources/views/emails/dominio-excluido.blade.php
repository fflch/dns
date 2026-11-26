<p>
    A solicitação do domínio <b>{{ $fqdn }}</b> (que estava <b>{{ $status }}</b>) foi <b>excluída</b> por <b>{{ $quem }}</b>.
    @if($status == 'aprovado')
        O domínio deixou de ser publicado.
    @endif
</p>

<p>
    Domínio: {{ $fqdn }} (e www.{{ $fqdn }}) <br>
    IP de apontamento: {{ $ip }} <br>
    Solicitante: {{ $solicitante }} <br>
    Motivo do pedido: <i>{{ $motivo }}</i>
</p>

@if($mensagem)
    <p>
        Explicação de {{ $quem }}:<br>
        <i>{{ $mensagem }}</i>
    </p>
@endif

<p>Com a exclusão, o nome fica livre para uma nova solicitação.</p>

<p>Faculdade de Filosofia, Letras e Ciências Humanas - USP</p>
