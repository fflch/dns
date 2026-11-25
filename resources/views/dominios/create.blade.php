@extends('laravel-usp-theme::master')

@section('title', 'Nova Solicitação')

@section('content')
<h3>Nova Solicitação de Domínio</h3>
<p class="text-muted">
    O pedido vai para os administradores, que aprovam ou rejeitam. Você recebe um e-mail a cada decisão.
    O domínio é publicado com e sem www (ex.: <code>abc.{{ config('dns.sufixo') }}</code> e <code>www.abc.{{ config('dns.sufixo') }}</code>).
</p>

@include('partials._erros')

<form method="POST" action="{{ route('dominios.store') }}" style="max-width: 700px;">
    @csrf

    <div class="form-group">
        <label for="nome">Domínio</label>
        <div class="input-group">
            <input type="text" name="nome" id="nome" value="{{ old('nome') }}" class="form-control @error('nome') is-invalid @enderror"
                placeholder="abc ou abc.departamento" required autofocus>
            <div class="input-group-append">
                <span class="input-group-text">.{{ config('dns.sufixo') }}</span>
            </div>
        </div>
        <small class="form-text text-muted">Letras minúsculas, números, hífen e ponto. Sem "www".</small>
    </div>

    <div class="form-group">
        <label for="ip">IP de apontamento (IPv4)</label>
        <input type="text" name="ip" id="ip" value="{{ old('ip') }}" class="form-control @error('ip') is-invalid @enderror"
            placeholder="200.144.244.59" required>
    </div>

    <div class="form-group">
        <label for="motivo">Motivo</label>
        <textarea name="motivo" id="motivo" rows="4" class="form-control @error('motivo') is-invalid @enderror"
            placeholder="Para que será usado o domínio, quem é o responsável pelo servidor..." required>{{ old('motivo') }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary">Enviar solicitação</button>
    <a href="{{ route('dominios.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
