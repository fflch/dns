<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DominioController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\ZonaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexController::class, 'index']);

// Pública: domínios aprovados, com e sem www (formato de sites.fflch.usp.br/domains)
Route::get('/domains', [ZonaController::class, 'index'])->name('domains');

Route::middleware('auth')->group(function () {
    // Solicitante (servidor ou docente)
    Route::get('/dominios', [DominioController::class, 'index'])->name('dominios.index');
    Route::get('/dominios/create', [DominioController::class, 'create'])->name('dominios.create');
    Route::post('/dominios', [DominioController::class, 'store'])->name('dominios.store');
    Route::get('/dominios/{dominio}', [DominioController::class, 'show'])->name('dominios.show');
    // Solicitante (pendente/aprovado) ou admin (sempre)
    Route::patch('/dominios/{dominio}/ip', [DominioController::class, 'trocarIp'])->name('dominios.ip');

    // Admin (SENHAUNICA_ADMINS)
    Route::get('/admin/pendentes', [AdminController::class, 'pendentes'])->name('admin.pendentes');
    Route::get('/admin/todos', [AdminController::class, 'todos'])->name('admin.todos');
    Route::patch('/admin/{dominio}/aprovar', [AdminController::class, 'aprovar'])->name('admin.aprovar');
    Route::patch('/admin/{dominio}/rejeitar', [AdminController::class, 'rejeitar'])->name('admin.rejeitar');
    Route::delete('/admin/{dominio}', [AdminController::class, 'destroy'])->name('admin.destroy');
});
