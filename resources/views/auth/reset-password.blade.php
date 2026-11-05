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

    <h2>Buat Password Baru</h2>
    <p class="auth-lead">Kode OTP sudah terverifikasi. Masukkan password baru untuk akun Anda.</p>

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

    <form action="{{ route('password.update') }}" method="POST" autocomplete="off">
        @csrf

        <div class="auth-field">
            <label for="password">Password baru</label>
            <div class="auth-pass">
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
                <button type="button" class="auth-toggle" data-target="password" aria-label="Tampilkan password">Lihat</button>
            </div>
            <span class="auth-hint">Minimal 8 karakter.</span>
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Ulangi password baru</label>
            <div class="auth-pass">
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <button type="button" class="auth-toggle" data-target="password_confirmation" aria-label="Tampilkan password">Lihat</button>
            </div>
            <span class="auth-match" id="matchMsg" aria-live="polite"></span>
        </div>

        <button type="submit" class="auth-submit">Simpan Password Baru</button>
    </form>

    <a class="auth-back" href="{{ route('login') }}">&larr; Batal, kembali ke login</a>
</div>

<script>
    (function () {
        document.querySelectorAll('.auth-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                var show  = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Sembunyi' : 'Lihat';
            });
        });

        var p1 = document.getElementById('password');
        var p2 = document.getElementById('password_confirmation');
        var msg = document.getElementById('matchMsg');
        function check() {
            if (!p2.value) { msg.className = 'auth-match'; msg.textContent = ''; return; }
            var ok = p1.value === p2.value;
            msg.className = 'auth-match ' + (ok ? 'ok' : 'bad');
            msg.textContent = ok ? 'Password cocok.' : 'Password belum sama.';
        }
        p1.addEventListener('input', check);
        p2.addEventListener('input', check);
    })();
</script>

@endsection
