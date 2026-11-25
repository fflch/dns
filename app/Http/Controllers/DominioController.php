<?php

namespace App\Http\Controllers;

use App\Http\Requests\DominioRequest;
use App\Models\Dominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Telas do solicitante: servidores e docentes (gate 'solicitante' no AppServiceProvider).
 */
class DominioController extends Controller
{
    /**
     * Solicitações do usuário logado
     */
    public function index(Request $request): View
    {
        Gate::authorize('solicitante');

        $dominios = Dominio::where('user_id', $request->user()->id)
            ->with('statuses', 'avaliador')
            ->latest()
            ->paginate(20);

        return view('dominios.index', [
            'dominios' => $dominios,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('solicitante');

        return view('dominios.create');
    }

    public function store(DominioRequest $request): RedirectResponse
    {
        $dominio = new Dominio($request->validated());
        $dominio->user_id = $request->user()->id;
        $dominio->save();
        $dominio->setStatus('pendente');

        $dominio->notificarEnvolvidos('solicitado', $request->user()->name);

        return redirect()->route('dominios.show', $dominio)
            ->with('alert-success', "Solicitação de {$dominio->fqdn()} enviada para os administradores.");
    }

    /**
     * Quem pediu ou admin
     */
    public function show(Request $request, Dominio $dominio): View
    {
        if ($dominio->user_id != $request->user()->id && Gate::denies('admin')) {
            abort(403);
        }

        return view('dominios.show', [
            'dominio' => $dominio,
        ]);
    }
}
