<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Menampilkan halaman formulir login.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /**
     * Memproses autentikasi pengguna.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard')->with('success', 'Berhasil login!');
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ])->onlyInput('username');
    }

    private const OTP_TTL_MINUTES   = 10;
    private const OTP_MAX_ATTEMPTS  = 5;
    private const OTP_RESEND_SECOND = 60;

    /**
     * Langkah 1 - Lupa Password: masukkan username.
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Langkah 1 (proses): kirim kode OTP 6 digit ke EMAIL yang terdaftar di akun.
     * Pesan balasan sengaja sama baik username ada maupun tidak, agar username
     * tidak bisa ditebak-tebak.
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
        ], [
            'username.required' => 'Username wajib diisi.',
        ]);

        $user = User::where('username', $validated['username'])->first();

        if ($user && !empty($user->email)) {
            $error = $this->issueOtp($user);
            if ($error) {
                return back()->withErrors(['username' => $error])->onlyInput('username');
            }
        }

        $request->session()->put('reset_username', $validated['username']);

        return redirect()->route('password.verify')
            ->with('success', 'Jika akun terdaftar dan memiliki email, kode OTP telah dikirim. Cek inbox atau folder Spam.');
    }

    /**
     * Langkah 2 - form kode OTP + password baru.
     */
    public function showVerifyOtp(Request $request)
    {
        if (!$request->session()->has('reset_username')) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-otp', ['username' => $request->session()->get('reset_username')]);
    }

    /**
     * Kirim ulang OTP (jeda minimal 60 detik).
     */
    public function resendOtp(Request $request)
    {
        $username = $request->session()->get('reset_username');
        if (!$username) {
            return redirect()->route('password.request');
        }

        $user = User::where('username', $username)->first();

        if ($user && !empty($user->email)) {
            $error = $this->issueOtp($user);
            if ($error) {
                return back()->withErrors(['otp' => $error]);
            }
        }

        return back()->with('success', 'Kode OTP baru telah dikirim jika akun terdaftar.');
    }

    /**
     * Langkah 2 (proses): cek OTP lalu atur password baru.
     */
    public function resetPassword(Request $request)
    {
        $username = $request->session()->get('reset_username');
        if (!$username) {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'otp'      => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'otp.required'       => 'Kode OTP wajib diisi.',
            'otp.digits'         => 'Kode OTP terdiri dari 6 angka.',
            'password.required'  => 'Password baru wajib diisi.',
            'password.min'       => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $gagal = 'Kode OTP salah atau sudah kedaluwarsa. Minta kode baru bila perlu.';

        $user = User::where('username', $username)->first();
        $otp  = $user ? PasswordOtp::where('user_id', $user->id)->latest('id')->first() : null;

        if (!$otp || $otp->expires_at->isPast() || $otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['otp' => $gagal]);
        }

        if (!Hash::check($validated['otp'], $otp->code_hash)) {
            $otp->increment('attempts');
            return back()->withErrors(['otp' => $gagal]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        PasswordOtp::where('user_id', $user->id)->delete();
        $request->session()->forget('reset_username');

        return redirect()->route('login')->with('success', 'Password berhasil diubah. Silakan login dengan password baru.');
    }

    /**
     * Buat OTP baru + kirim ke email. Mengembalikan pesan error (string) bila gagal, null bila sukses.
     */
    private function issueOtp(User $user): ?string
    {
        $last = PasswordOtp::where('user_id', $user->id)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < self::OTP_RESEND_SECOND) {
            return 'Tunggu ' . self::OTP_RESEND_SECOND . ' detik sebelum meminta kode baru.';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordOtp::where('user_id', $user->id)->delete();
        $otp = PasswordOtp::create([
            'user_id'    => $user->id,
            'code_hash'  => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        try {
            $nama = e($user->fullname ?: $user->username);
            $html = '<div style="font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:20px;border:1px solid #e2e8e5;border-radius:10px;">'
                . '<h2 style="margin:0 0 8px;color:#2f5f51;">RAPI-Sekolah</h2>'
                . '<p>Halo ' . $nama . ', gunakan kode berikut untuk mengatur ulang password Anda:</p>'
                . '<p style="font-size:32px;letter-spacing:8px;font-weight:bold;text-align:center;margin:18px 0;">' . $code . '</p>'
                . '<p style="font-size:13px;color:#555;">Kode berlaku ' . self::OTP_TTL_MINUTES . ' menit. Jangan berikan kode ini kepada siapa pun. '
                . 'Jika Anda tidak meminta reset password, abaikan email ini.</p></div>';

            Mail::html($html, function ($m) use ($user) {
                $m->to($user->email)->subject('Kode OTP Reset Password RAPI-Sekolah');
            });
        } catch (\Throwable $e) {
            report($e);
            $otp->delete();
            return 'Gagal mengirim email OTP. Periksa pengaturan email server, lalu coba lagi.';
        }

        return null;
    }

    /**
     * Memproses keluar sistem (logout).
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Berhasil logout!');
    }
}
