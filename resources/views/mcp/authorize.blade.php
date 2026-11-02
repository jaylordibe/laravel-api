@extends('mcp.layout', ['title' => 'Allow access'])

@section('content')
    <h1>Allow access?</h1>
    <p>An application wants to use {{ config('app.name') }} as <strong>{{ $user->email }}</strong>.</p>
    <dl class="client">
        <dt>Application (name chosen by the application itself)</dt>
        <dd>{{ $client->name }}</dd>
        <dt>After you choose, you will be sent to</dt>
        {{-- The redirect URI this request uses, which Passport has already matched exactly against the client's registration. --}}
        <dd class="redirect">{{ $request->query('redirect_uri') }}</dd>
    </dl>
    <p class="muted">Only allow this if you started the connection and recognise the address above. The application will be able to use {{ config('app.name') }}'s MCP tools as you until you sign out everywhere or change your password.</p>
    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="inline">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->query('state') }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Deny</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="inline">
            @csrf
            <input type="hidden" name="state" value="{{ $request->query('state') }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">Allow</button>
        </form>
    </div>
@endsection
