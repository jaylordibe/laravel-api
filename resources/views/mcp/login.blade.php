@extends('mcp.layout', ['title' => 'Sign in'])

@section('content')
    <h1>Sign in</h1>
    @if ($clientName !== null)
        <p class="muted"><strong>{{ $clientName }}</strong> wants to connect to {{ config('app.name') }}. Sign in to continue.</p>
    @else
        <p class="muted">Sign in to connect an application to {{ config('app.name') }}.</p>
    @endif
    @if ($errors->any())
        <p class="error" role="alert">{{ $errors->first() }}</p>
    @endif
    <form method="POST" action="{{ route('mcp.oauth.login.submit') }}" novalidate>
        @csrf
        <label for="identifier">Email or username</label>
        <input id="identifier" name="identifier" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required value="{{ old('identifier') }}" autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <div class="actions">
            <button type="submit" class="primary">Sign in</button>
        </div>
    </form>
@endsection
