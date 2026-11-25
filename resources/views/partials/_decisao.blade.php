{{--
    Somente admin. Aprovar (mensagem opcional) quando não está aprovado;
    rejeitar (explicação obrigatória) quando não está rejeitado, inclusive se já aprovado.
--}}
@can('admin')
    @php
        $doFormulario = old('dominio') == $dominio->id;
    @endphp
    <form method="POST">
        @csrf
        @method('PATCH')
        <input type="hidden" name="dominio" value="{{ $dominio->id }}">

        <textarea name="mensagem" rows="2" class="form-control mb-2"
            placeholder="Explicação ({{ $dominio->status != 'aprovado' ? 'opcional ao aprovar, ' : '' }}obrigatória ao rejeitar)">{{ $doFormulario ? old('mensagem') : '' }}</textarea>

        <div class="row">
            @if($dominio->status != 'aprovado')
                <div class="col">
                    <button type="submit" formaction="{{ route('admin.aprovar', $dominio) }}" class="btn btn-success btn-block"
                        onclick="return confirm('Aprovar e publicar {{ $dominio->fqdn() }} → {{ $dominio->ip }}?');">
                        <i class="fas fa-check"></i> Aprovar
                    </button>
                </div>
            @endif
            @if($dominio->status != 'rejeitado')
                <div class="col">
                    <button type="submit" formaction="{{ route('admin.rejeitar', $dominio) }}" class="btn btn-outline-danger btn-block"
                        onclick="if (!this.form.mensagem.value.trim()) { alert('Informe a explicação da rejeição.'); this.form.mensagem.focus(); return false; } return confirm('{{ $dominio->status == 'aprovado' ? 'Revogar a aprovação de' : 'Rejeitar' }} {{ $dominio->fqdn() }}?');">
                        <i class="fas fa-times"></i> {{ $dominio->status == 'aprovado' ? 'Revogar (rejeitar)' : 'Rejeitar' }}
                    </button>
                </div>
            @endif
        </div>
    </form>
@endcan
