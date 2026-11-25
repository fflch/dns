<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use Illuminate\Http\Response;

/**
 * Rota pública com os domínios aprovados, no mesmo formato de sites.fflch.usp.br/domains
 */
class ZonaController extends Controller
{
    public function index(): Response
    {
        $linhas = Dominio::currentStatus('aprovado')
            ->orderBy('nome')
            ->get()
            ->flatMap(fn (Dominio $dominio) => $dominio->registros());

        $corpo = $linhas->isEmpty() ? '' : $linhas->implode("\n")."\n";

        return response($corpo, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
