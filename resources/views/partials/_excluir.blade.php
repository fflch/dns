{{-- Excluir: somente admin, em qualquer status. Libera o nome para nova solicitação. --}}
@can('admin')
    <form method="POST" action="{{ route('admin.destroy', $dominio) }}" class="d-inline">
        @csrf
        @method('DELETE')
        <input type="hidden" name="mensagem">
        <button type="submit" class="btn btn-danger btn-sm"
            onclick="var m = prompt('Excluir definitivamente a solicitação de {{ $dominio->fqdn() }}?{{ $dominio->status == 'aprovado' ? ' Ele deixará de ser publicado.' : '' }}\n\nExplicação (opcional):'); if (m === null) { return false; } this.form.mensagem.value = m; return true;">
            <i class="fas fa-trash"></i> Excluir
        </button>
    </form>
@endcan
