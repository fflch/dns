@extends('laravel-usp-theme::master')

@section('title', $dominio->fqdn())

@section('content')
<h3>
    {{ $dominio->fqdn() }}
    <span class="badge badge-{{ $dominio->cor() }}">{{ $dominio->status }}</span>
</h3>

<p>
    IP de apontamento: <code>{{ $dominio->ip }}</code> <br>
    Solicitante: {{ $dominio->user?->name }} ({{ $dominio->user?->email }}) <br>
    Motivo: {{ $dominio->motivo }} <br>
    Solicitado em {{ $dominio->created_at->format('d/m/Y H:i') }} <br>
    @if($dominio->avaliador)
        <b>{{ ucfirst($dominio->status) }} por {{ $dominio->avaliador->name }}</b>
        em {{ $dominio->latestStatus()->created_at->format('d/m/Y H:i') }}
        @if($dominio->mensagem())
            <br>Explicação: <i>{{ $dominio->mensagem() }}</i>
        @endif
    @endif
</p>

@if($dominio->status == 'aprovado')
    <div class="alert alert-success">
        Publicado em <a href="{{ route('domains') }}">{{ route('domains') }}</a>:
        <pre class="mb-0 mt-2">{{ implode("\n", $dominio->registros()) }}</pre>
    </div>
@elseif($dominio->status == 'pendente')
    <div class="alert alert-warning">Aguardando avaliação de um administrador.</div>
@endif

@include('partials._erros')

@can('trocarIp', $dominio)
    <div class="card mb-3" style="max-width: 600px;">
        <div class="card-body">
            <form method="POST" action="{{ route('dominios.ip', $dominio) }}">
                @csrf
                @method('PATCH')
                <label for="ip">Novo IP de apontamento</label>
                <input type="text" name="ip" id="ip" value="{{ old('ip') }}" class="form-control mb-2" placeholder="{{ $dominio->ip }}" required>
                <textarea name="mensagem" rows="2" class="form-control mb-2" placeholder="Mensagem (opcional): por que o IP mudou">{{ old('mensagem') }}</textarea>
                @php($voltaPendente = $dominio->status == 'aprovado' && Gate::denies('admin'))
                <button type="submit" class="btn btn-outline-primary"
                    @if($voltaPendente) onclick="return confirm('O domínio já está aprovado. Ao trocar o IP ele volta para análise e deixa de ser publicado até nova aprovação. Continuar?');" @endif>
                    Trocar IP
                </button>
            </form>
            @if($voltaPendente)
                <small class="text-muted">Como o domínio já está aprovado, trocar o IP faz ele voltar para análise.</small>
            @endif
        </div>
    </div>
@endcan

@can('admin')
    <div class="card mb-3" style="max-width: 600px;">
        <div class="card-body">
            @include('partials._decisao')
        </div>
    </div>
    @include('partials._excluir')
@endcan

<h5 class="mt-4">Histórico</h5>
<table class="table table-sm">
    <thead class="thead-light">
        <tr><th>Data</th><th>Ação</th><th>Quem</th><th>Detalhe</th><th>IP</th></tr>
    </thead>
    <tbody>
        @forelse($dominio->historico() as $item)
            <tr>
                <td class="text-nowrap">{{ $item['data']->format('d/m/Y H:i:s') }}</td>
                <td><span class="badge badge-{{ $item['cor'] }}">{{ $item['acao'] }}</span></td>
                <td>{{ $item['quem'] }}</td>
                <td>{{ $item['detalhe'] }}</td>
                <td><small class="text-muted">{{ $item['ip'] }}</small></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">Nenhum registro na auditoria.</td></tr>
        @endforelse
    </tbody>
</table>

<a href="{{ url()->previous() }}">Voltar</a>
@endsection
