
@extends('layouts.app')

@section('body_class', 'page-login')

@section('content')

<div>
    <img class="login-photo"
         src="{{ asset('images/login-foto.jpg') }}"
         srcset="{{ asset('images/login-foto.jpg') }} 1x, {{ asset('images/login-foto-2x.jpg') }} 2x"
         alt="Gedung SMK Negeri 1 Bantul" width="372" height="170">

    <h2>Login Sistem RAPI-Sekolah</h2>

    @if($errors->any())
        <p style="color: red;">{{ $errors->first() }}</p>
    @endif

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    <form action="{{ route('login') }}" method="POST">
        @csrf
        <div>
            <label>Username:</label><br>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password:</label><br>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Masuk</button>
    </form>
    <p style="margin-top: 12px; font-size: 13px;"><a href="{{ route('password.request') }}">Lupa password?</a></p>
</div>

@endsection
