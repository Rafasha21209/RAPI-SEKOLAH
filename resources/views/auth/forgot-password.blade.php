@extends('layouts.app')

@section('body_class', 'page-login')

@section('content')

<style>
    /* Halaman Ubah Password: kartu di tengah, rapi di HP maupun PC. */
    main { display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; min-height: 100dvh; padding-top: 4vh; }
    main > div.auth-card { width: 100%; max-width: 440px; margin: 0 auto; padding: 1.5rem 1.5rem 1.75rem !important; }

    .auth-card .login-photo { height: 130px; margin-bottom: 1.1rem; }
    .auth-card h2 { margin: 0 0 .35rem; font-size: 1.35rem; }
    .auth-card .auth-lead { margin: 0 0 1.1rem; font-size: .88rem; color: var(--c-ink-soft); line-height: 1.5; }

    .auth-alert { margin-bottom: 1rem; padding: .65rem .85rem; font-size: .85rem; line-height: 1.45;
                  border-radius: var(--r-sm); background: var(--c-danger-bg); border: 1px solid var(--c-danger-line); color: var(--c-danger); }
    .auth-alert p { margin: 0; }
    .auth-alert p + p { margin-top: .3rem; }

    .auth-field { margin-bottom: .95rem; }
    .auth-field label { display: block; margin-bottom: .3rem; font-size: .85rem; font-weight: 600; color: var(--c-ink); }
    .auth-field input { display: block; width: 100%; box-sizing: border-box; min-height: 44px; }
    .auth-hint { display: block; margin-top: .3rem; font-size: .76rem; color: var(--c-ink-muted); }

    .auth-pass { position: relative; }
    .auth-pass input { padding-right: 4.6rem; }
    .auth-pass .auth-toggle { position: absolute; top: 50%; right: .4rem; transform: translateY(-50%);
                              min-height: 32px; padding: 0 .65rem; font-size: .75rem; font-weight: 600;
                              color: var(--c-primary-strong); background: transparent; border: 1px solid transparent; }
    .auth-pass .auth-toggle:hover { background: var(--c-primary-soft); color: var(--c-primary-strong); }

    .auth-match { display: none; margin-top: .3rem; font-size: .76rem; }
    .auth-match.ok  { display: block; color: var(--c-ok); }
    .auth-match.bad { display: block; color: var(--c-danger); }

    .auth-submit { display: block; width: 100%; min-height: 46px; margin-top: 1.2rem; font-size: .95rem; text-align: center; }
    .auth-back { display: block; margin-top: 1rem; text-align: center; font-size: .85rem; }

    @media (max-width: 640px) {
        main { padding: 0 .75rem 1.5rem; padding-top: 2vh; }
        main > div.auth-card { padding: 1.1rem 1rem 1.4rem !important; }
        .auth-card .login-photo { height: 100px; }
        .auth-card h2 { font-size: 1.2rem; }
        /* 16px mencegah iPhone memperbesar layar saat kolom diketuk */
        .auth-field input { font-size: 16px; }
    }
</style>

<div class="auth-card">
    <img class="login-photo"
         src="{{ asset('images/login-foto.jpg') }}"
         srcset="{{ asset('images/login-foto.jpg') }} 1x, {{ asset('images/login-foto-2x.jpg') }} 2x"
         alt="Gedung SMK Negeri 1 Bantul" width="372" height="170">

    <h2>Lupa Password</h2>
    <p class="auth-lead">
        Masukkan email yang terdaftar di akun Anda. Kode OTP 6 angka akan dikirim ke email tersebut.
    </p>

    @if($errors->any())
        <div class="auth-alert" role="alert">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" autocomplete="off">
        @csrf

        <div class="auth-field">
            <label for="email">Email terdaftar</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   placeholder="nama@gmail.com" required autocomplete="email" autocapitalize="none" spellcheck="false">
        </div>

        <button type="submit" class="auth-submit">Kirim Kode OTP</button>
    </form>

    <a class="auth-back" href="{{ route('login') }}">&larr; Kembali ke login</a>
</div>

@endsection
