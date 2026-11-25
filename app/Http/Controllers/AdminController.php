<?php

namespace App\Http\Controllers;

use App\Exceptions\SitesIndisponivelException;
use App\Models\Dominio;
use App\Services\SitesFflch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Telas dos admins (SENHAUNICA_ADMINS): aprovar ou rejeitar, a qualquer momento.
 */
class AdminController extends Controller
{
    public function pendentes(Request $request): View
    {
        Gate::authorize('admin');

        $query = Dominio::currentStatus('pendente')->with('statuses', 'user');
        $this->busca($query, $request->search);

        return view('admin.pendentes', [
            'dominios' => $query->oldest()->paginate(20)->withQueryString(),
        ]);
    }

    public function todos(Request $request): View
    {
        Gate::authorize('admin');

        $query = Dominio::with('statuses', 'user', 'avaliador');
        if ($request->filled('status')) {
            $query->currentStatus($request->status);
        }
        $this->busca($query, $request->search);

        return view('admin.todos', [
            'dominios' => $query->latest()->paginate(30)->withQueryString(),
        ]);
    }

    /**
     * Aprova (mensagem opcional). Vale para pendente e também para rejeitado.
     * Confere de novo o nome aqui e em sites.fflch.usp.br, pois pode ter mudado desde o pedido.
     */
    public function aprovar(Request $request, Dominio $dominio, SitesFflch $sites): RedirectResponse
    {
        Gate::authorize('admin');

        if ($dominio->status == 'aprovado') {
            return back()->with('alert-info', "{$dominio->fqdn()} já está aprovado.");
        }

        $request->validate([
            'mensagem' => 'nullable|max:1000',
        ]);

        if (Dominio::nomeReservado($dominio->nome, $dominio->id)) {
            return back()->withInput()->withErrors(['mensagem' => "Já existe outra solicitação pendente ou aprovada para {$dominio->fqdn()}."]);
        }

        try {
            if ($sites->emUso($dominio->fqdn())) {
                return back()->withInput()->withErrors(['mensagem' => "{$dominio->fqdn()} já está em uso em sites.fflch.usp.br. Rejeite a solicitação."]);
            }
        } catch (SitesIndisponivelException $e) {
            return back()->withInput()->withErrors(['mensagem' => $e->getMessage()]);
        }

        $dominio->avaliador_id = $request->user()->id;
        $dominio->save();
        $dominio->setStatus('aprovado', $request->mensagem);

        $dominio->notificarEnvolvidos('aprovado', $request->user()->name, $request->mensagem);

        return back()->with('alert-success', "{$dominio->fqdn()} aprovado e publicado.");
    }

    /**
     * Rejeita (explicação obrigatória). Vale para pendente e também para aprovado (revoga).
     */
    public function rejeitar(Request $request, Dominio $dominio): RedirectResponse
    {
        Gate::authorize('admin');

        if ($dominio->status == 'rejeitado') {
            return back()->with('alert-info', "{$dominio->fqdn()} já está rejeitado.");
        }

        $request->validate([
            'mensagem' => 'required|min:5|max:1000',
        ], [
            'mensagem.required' => 'Informe a explicação da rejeição.',
            'mensagem.min' => 'A explicação da rejeição deve ter pelo menos 5 caracteres.',
        ]);

        $revogado = $dominio->status == 'aprovado';

        $dominio->avaliador_id = $request->user()->id;
        $dominio->save();
        $dominio->setStatus('rejeitado', $request->mensagem);

        $dominio->notificarEnvolvidos('rejeitado', $request->user()->name, $request->mensagem, $revogado);

        return back()->with('alert-info', $revogado
            ? "Aprovação de {$dominio->fqdn()} revogada: o domínio deixou de ser publicado."
            : "{$dominio->fqdn()} rejeitado.");
    }

    /**
     * Busca por domínio, IP ou nome de quem pediu
     *
     * @param  Builder<Dominio>  $query
     */
    private function busca(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $query->where(function ($query) use ($search) {
            $query->where('nome', 'like', "%{$search}%")
                ->orWhere('ip', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
        });
    }
}
