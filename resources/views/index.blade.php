@extends('laravel-usp-theme::master')

@section('title', 'DNS FFLCH')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8 text-center py-4">
        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
            style="background-color: #273e74; width: 60px; height: 60px; font-size: 24px;">
            <i class="fas fa-globe"></i>
        </div>
        <h2 class="font-weight-bold">DNS FFLCH</h2>
        <p class="text-muted">
            Solicitação de domínios <code>*.{{ config('dns.sufixo') }}</code> para funcionários e docentes da
            Faculdade de Filosofia, Letras e Ciências Humanas da USP.
        </p>

        @auth
            <div class="alert alert-warning d-inline-block">
                Somente funcionários e docentes da FFLCH podem solicitar domínios.
            </div>
        @else
            <a href="/login" class="btn btn-primary px-4 font-weight-bold">ENTRAR COM SENHA ÚNICA</a>
        @endauth
    </div>
</div>
@endsection
