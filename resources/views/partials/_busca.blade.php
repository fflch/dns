<form class="form-inline mb-3">
    @if(isset($comStatus))
        <select name="status" class="form-control mr-2">
            <option value="">Todos os status</option>
            @foreach(['pendente', 'aprovado', 'rejeitado'] as $status)
                <option value="{{ $status }}" @selected(request('status') == $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    @endif
    <input type="text" name="search" value="{{ request('search') }}" class="form-control mr-2" placeholder="Domínio, IP ou solicitante">
    <button type="submit" class="btn btn-outline-primary">Pesquisar</button>
    @if(request('search') || request('status'))
        <a href="{{ url()->current() }}" class="btn btn-link">Limpar</a>
    @endif
</form>
