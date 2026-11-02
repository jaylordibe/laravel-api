@extends('mcp.layout', ['title' => $title])

@section('content')
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
@endsection
