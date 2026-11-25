@extends('laravel-usp-theme::master')

@section('title', 'Minhas Solicitações')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Minhas Solicitações</h3>
    <a href="{{ route('dominios.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nova solicitação</a>
</div>
<p class="text-muted">Domínios que você pediu. Os aprovados são publicados com e sem www.</p>

@include('partials._tabela')
@endsection
