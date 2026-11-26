<?php

namespace App\Models;

use App\Mail\DominioMail;
use Database\Factories\DominioFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStatus\HasStatuses;

/**
 * Pedido de apontamento DNS (registro A) em <nome>.<sufixo>.
 * Status (spatie/laravel-model-status): pendente, aprovado, rejeitado.
 * Publicado sempre com e sem www.
 */
class Dominio extends Model implements Auditable
{
    /** @use HasFactory<DominioFactory> */
    use HasFactory;

    use HasStatuses;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nome', 'ip', 'motivo'];

    /**
     * Quem aprovou/rejeitou já fica registrado no audit de status (ver AppServiceProvider)
     *
     * @var array<int, string>
     */
    protected $auditExclude = ['avaliador_id'];

    /**
     * Servidor ou docente que pediu
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin que aprovou ou rejeitou por último
     *
     * @return BelongsTo<User, $this>
     */
    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliador_id');
    }

    /**
     * Nome completo, ex.: abc.fflch.usp.br
     */
    public function fqdn(): string
    {
        return $this->nome.'.'.config('dns.sufixo');
    }

    /**
     * Registros publicados (com e sem www)
     *
     * @return array<int, string>
     */
    public function registros(): array
    {
        return [
            "{$this->fqdn()}. IN A {$this->ip}",
            "www.{$this->fqdn()}. IN A {$this->ip}",
        ];
    }

    /**
     * Já existe solicitação com esse nome, em qualquer status (pendente, aprovado ou rejeitado).
     * O nome só fica livre de novo se um admin excluir a solicitação.
     */
    public static function nomeJaSolicitado(string $nome): bool
    {
        return static::where('nome', $nome)->exists();
    }

    /**
     * Mensagem da última decisão (motivo da rejeição ou mensagem da aprovação)
     */
    public function mensagem(): ?string
    {
        return $this->latestStatus()?->reason;
    }

    /**
     * Classe de cor do Bootstrap para o status atual
     */
    public function cor(): string
    {
        return match ($this->status) {
            'aprovado' => 'success',
            'rejeitado' => 'danger',
            default => 'warning',
        };
    }

    /**
     * Admins (permissão hierárquica 'admin' da senhaunica-socialite): recebem os avisos por e-mail
     *
     * @return Collection<int, User>
     */
    public static function admins(): Collection
    {
        return User::whereHas('permissions', fn ($query) => $query
            ->where('name', 'admin')
            ->where('guard_name', User::$hierarquiaNs))
            ->get();
    }

    /**
     * E-mail para todos os envolvidos: o solicitante e os admins (inclui quem fez a ação)
     */
    public function notificarEnvolvidos(
        string $acao,
        string $quem,
        ?string $mensagem = null,
        bool $revogado = false,
        ?string $ipAnterior = null,
        bool $voltouPendente = false,
    ): void {
        $this->enviarParaEnvolvidos(new DominioMail($this, $acao, $quem, $mensagem, $revogado, $ipAnterior, $voltouPendente));
    }

    /**
     * Envia o e-mail ao solicitante, com todos os admins em cópia
     */
    public function enviarParaEnvolvidos(Mailable $mail): void
    {
        $emails = collect([$this->user?->email])
            ->merge(static::admins()->pluck('email'))
            ->filter()
            ->map(fn ($email) => strtolower($email))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        Mail::to($emails->first())
            ->cc($emails->slice(1)->all())
            ->queue($mail);
    }

    /**
     * Histórico, do mais antigo ao mais recente, montado a partir da auditoria.
     *
     * @return array<int, array{data: Carbon, quem: string, acao: string, cor: string, detalhe: ?string, ip: ?string}>
     */
    public function historico(): array
    {
        $historico = [];

        foreach ($this->audits()->with('user')->oldest('id')->get() as $audit) {
            $antes = $audit->old_values['status'] ?? null;
            $depois = $audit->new_values['status'] ?? null;
            $detalhe = $audit->new_values['observacao'] ?? null;

            if ($audit->event == 'created') {
                $acao = 'Solicitação criada';
                $detalhe = ($audit->new_values['nome'] ?? '').' → '.($audit->new_values['ip'] ?? '')
                    .'. Motivo: '.($audit->new_values['motivo'] ?? '');
            } elseif ($audit->event == 'status' && $depois == 'pendente' && $antes === null) {
                // Status inicial, logo após a criação: não precisa aparecer
                continue;
            } elseif ($audit->event == 'status' && $depois == 'pendente') {
                $acao = 'Voltou para análise';
            } elseif (in_array($audit->event, ['ip', 'updated']) && isset($audit->new_values['ip'])) {
                $acao = 'IP alterado';
                $detalhe = ($audit->old_values['ip'] ?? '?').' → '.$audit->new_values['ip']
                    .($detalhe ? ". Mensagem: {$detalhe}" : '');
            } elseif ($audit->event == 'status' && $depois == 'aprovado') {
                $acao = $antes == 'rejeitado' ? 'Aprovado após rejeição' : 'Aprovado';
            } elseif ($audit->event == 'status' && $depois == 'rejeitado') {
                $acao = $antes == 'aprovado' ? 'Aprovação revogada' : 'Rejeitado';
            } else {
                $acao = 'Dados alterados';
                $detalhe = collect($audit->new_values)->map(fn ($valor, $campo) => "{$campo}: {$valor}")->implode(', ');
            }

            $historico[] = [
                'data' => $audit->created_at,
                'quem' => $audit->user?->name ?? 'Sistema',
                'acao' => $acao,
                'cor' => $depois == 'aprovado' ? 'success' : ($depois == 'rejeitado' ? 'danger' : 'secondary'),
                'detalhe' => $detalhe,
                'ip' => $audit->ip_address,
            ];
        }

        return $historico;
    }
}
