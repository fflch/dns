<table class="table table-hover">
    <thead class="thead-light">
        <tr>
            <th>Data</th>
            <th>Domínio</th>
            <th>IP</th>
            @if($comSolicitante ?? false)
                <th>Solicitante</th>
            @endif
            <th>Motivo</th>
            <th>Status</th>
            <th>Decidido por</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($dominios as $dominio)
            <tr>
                <td class="text-nowrap">{{ $dominio->created_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('dominios.show', $dominio) }}">{{ $dominio->fqdn() }}</a></td>
                <td><code>{{ $dominio->ip }}</code></td>
                @if($comSolicitante ?? false)
                    <td>{{ $dominio->user?->name }}</td>
                @endif
                <td>{{ Str::limit($dominio->motivo, 80) }}</td>
                <td><span class="badge badge-{{ $dominio->cor() }}">{{ $dominio->status }}</span></td>
                <td>
                    @if($dominio->avaliador)
                        {{ $dominio->avaliador->name }}<br>
                        <small class="text-muted">{{ $dominio->latestStatus()->created_at->format('d/m/Y H:i') }}</small>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td><a href="{{ route('dominios.show', $dominio) }}" class="btn btn-outline-primary btn-sm">Abrir</a></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $dominios->links() }}
