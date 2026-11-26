@extends('laravel-usp-theme::master')

@section('title', 'Todas as Solicitações')

@section('content')
<h3>Todas as Solicitações</h3>
<p class="text-muted">
    Qualquer solicitação pode ser aprovada ou rejeitada a qualquer momento, inclusive as já aprovadas, ou excluída (o nome fica livre para nova solicitação).
    Lista pública dos aprovados: <a href="{{ route('domains') }}">{{ route('domains') }}</a>
</p>

@include('partials._busca', ['comStatus' => true])
@include('partials._tabela', ['comSolicitante' => true])
@endsection
