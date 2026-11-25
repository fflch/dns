@extends('laravel-usp-theme::master')

@section('title', 'Pendentes')

@section('content')
<h3>Solicitações Pendentes</h3>
<p class="text-muted">Domínios aguardando avaliação. Ao aprovar, o sistema confere de novo se o domínio não está em uso em sites.fflch.usp.br.</p>

@include('partials._erros')
@include('partials._busca')

<div class="row">
    @forelse($dominios as $dominio)
        <div class="col-md-6 col-xl-4 mb-4">
            <div class="card h-100 border-warning shadow-sm">
                <div class="card-header bg-warning d-flex justify-content-between">
                    <strong>{{ $dominio->user?->name }}</strong>
                    <span title="{{ $dominio->created_at->format('d/m/Y H:i') }}">{{ $dominio->created_at->diffForHumans() }}</span>
                </div>
                <div class="card-body">
                    <h5 class="card-title mb-1">
                        <a href="{{ route('dominios.show', $dominio) }}" class="text-dark">{{ $dominio->fqdn() }}</a>
                    </h5>
                    <p class="mb-2">→ <code>{{ $dominio->ip }}</code></p>
                    <p class="mb-0"><i class="fas fa-quote-left text-muted"></i> {{ $dominio->motivo }}</p>
                </div>
                <div class="card-footer bg-white">
                    @include('partials._decisao')
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-success text-center py-4">
                <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                Nenhuma solicitação pendente no momento.
            </div>
        </div>
    @endforelse
</div>

{{ $dominios->links() }}
@endsection
