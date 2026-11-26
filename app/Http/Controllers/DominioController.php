<?php

namespace App\Http\Controllers;

use App\Http\Requests\DominioRequest;
use App\Models\Dominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use OwenIt\Auditing\Events\AuditCustom;

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
     * Troca o IP de apontamento (gate 'trocarIp'). Admin troca sem mudar o status.
     * Se o solicitante troca um domínio já aprovado, ele volta para pendente e sai da lista pública.
     */
    public function trocarIp(Request $request, Dominio $dominio): RedirectResponse
    {
        Gate::authorize('trocarIp', $dominio);

        $request->validate([
            'ip' => ['required', 'ipv4', Rule::notIn([$dominio->ip])],
            'mensagem' => ['nullable', 'max:1000'],
        ], [
            'ip.required' => 'Informe o novo IP de apontamento.',
            'ip.ipv4' => 'Informe um endereço IPv4 válido (ex.: 200.144.244.59).',
            'ip.not_in' => 'O novo IP é igual ao atual.',
        ]);

        $ipAnterior = $dominio->ip;
        $voltouPendente = $dominio->status == 'aprovado' && Gate::denies('admin');

        // Sem o audit automático de 'updated': um audit próprio guarda os dois IPs e a mensagem
        Dominio::disableAuditing();
        $dominio->ip = $request->ip;
        $dominio->save();
        Dominio::enableAuditing();

        $dominio->auditEvent = 'ip';
        $dominio->isCustomEvent = true;
        $dominio->auditCustomOld = ['ip' => $ipAnterior];
        $dominio->auditCustomNew = ['ip' => $dominio->ip, 'observacao' => $request->mensagem];
        Event::dispatch(new AuditCustom($dominio));

        if ($voltouPendente) {
            $dominio->setStatus('pendente', "IP alterado pelo solicitante de {$ipAnterior} para {$dominio->ip}");
        }

        $dominio->notificarEnvolvidos('ip alterado', $request->user()->name, $request->mensagem, ipAnterior: $ipAnterior, voltouPendente: $voltouPendente);

        return back()->with('alert-success', $voltouPendente
            ? "IP alterado para {$dominio->ip}. O domínio voltou para análise e deixou de ser publicado até nova aprovação."
            : "IP alterado para {$dominio->ip}.");
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
