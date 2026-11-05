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

    .auth-otp { letter-spacing: .5em; text-align: center; font-size: 1.5rem !important; font-weight: 700; }
    .auth-ok { margin-bottom: 1rem; padding: .65rem .85rem; font-size: .85rem; line-height: 1.45; border-radius: var(--r-sm);
               background: var(--c-ok-bg); border: 1px solid var(--c-ok-line); color: var(--c-ok); }
    .auth-resend { display: block; width: 100%; margin-top: .6rem; min-height: 44px; background: transparent;
                   color: var(--c-primary-strong); border: 1px solid var(--c-primary-line); }
</style>

<div class="auth-card">
    <img class="login-photo"
         src="{{ asset('images/login-foto.jpg') }}"
         srcset="{{ asset('images/login-foto.jpg') }} 1x, {{ asset('images/login-foto-2x.jpg') }} 2x"
         alt="Gedung SMK Negeri 1 Bantul" width="372" height="170">

    <h2>Verifikasi Kode OTP</h2>
    <p class="auth-lead">
        Kode 6 angka sudah dikirim ke <strong>{{ $email }}</strong> (berlaku 10 menit).
        Setelah kode benar, Anda bisa membuat password baru.
    </p>

    @if(session('success'))
        <div class="auth-ok" role="status">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="auth-alert" role="alert">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('password.verify.submit') }}" method="POST" autocomplete="off">
        @csrf

        <div class="auth-field">
            <label for="otp">Kode OTP</label>
            <input type="text" id="otp" name="otp" class="auth-otp" required maxlength="6" minlength="6"
                   inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" autofocus>
        </div>

        <button type="submit" class="auth-submit">Verifikasi Kode</button>
    </form>

    <form action="{{ route('password.resend') }}" method="POST">
        @csrf
        <button type="submit" class="auth-resend">Kirim ulang kode OTP</button>
    </form>

    <a class="auth-back" href="{{ route('login') }}">&larr; Kembali ke login</a>
</div>



@endsection
