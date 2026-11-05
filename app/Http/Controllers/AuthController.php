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
     * Langkah 1 - Lupa Password: masukkan EMAIL yang terdaftar di akun.
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Langkah 1 (proses): kirim kode OTP 6 digit ke EMAIL yang terdaftar di akun.
     * Pesan balasan sengaja sama baik email ada maupun tidak, agar email
     * tidak bisa ditebak-tebak.
     */
    public function sendOtp(Request $request)
    {
        // Cadangan: bila form lama masih mengirim field "username", anggap itu isian email.
        if (!$request->filled('email') && $request->filled('username')) {
            $request->merge(['email' => $request->input('username')]);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user  = $this->findUserByEmail($email);

        if ($user) {
            $error = $this->issueOtp($user);
            if ($error) {
                return back()->withErrors(['email' => $error])->onlyInput('email');
            }
        }

        $request->session()->put('reset_email', $email);

        return redirect()->route('password.verify')
            ->with('success', 'Jika akun terdaftar dan memiliki email, kode OTP telah dikirim. Cek inbox atau folder Spam.');
    }

    /**
     * Langkah 2 - form kode OTP (verifikasi dulu).
     */
    public function showVerifyOtp(Request $request)
    {
        if (!$request->session()->has('reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-otp', ['email' => $request->session()->get('reset_email')]);
    }

    /**
     * Kirim ulang OTP (jeda minimal 60 detik).
     */
    public function resendOtp(Request $request)
    {
        $email = $request->session()->get('reset_email');
        if (!$email) {
            return redirect()->route('password.request');
        }

        $user = $this->findUserByEmail($email);

        if ($user) {
            $error = $this->issueOtp($user);
            if ($error) {
                return back()->withErrors(['otp' => $error]);
            }
        }

        return back()->with('success', 'Kode OTP baru telah dikirim jika akun terdaftar.');
    }

    /**
     * Langkah 2 (proses): verifikasi kode OTP. Password baru BELUM diminta di sini.
     */
    public function verifyOtp(Request $request)
    {
        $email = $request->session()->get('reset_email');
        if (!$email) {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits'   => 'Kode OTP terdiri dari 6 angka.',
        ]);

        $gagal = 'Kode OTP salah atau sudah kedaluwarsa. Minta kode baru bila perlu.';

        $user = $this->findUserByEmail($email);
        $otp  = $user ? PasswordOtp::where('user_id', $user->id)->latest('id')->first() : null;

        if (!$otp || $otp->expires_at->isPast() || $otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['otp' => $gagal]);
        }

        if (!Hash::check($validated['otp'], $otp->code_hash)) {
            $otp->increment('attempts');
            return back()->withErrors(['otp' => $gagal]);
        }

        // OTP benar -> sekali pakai. Izin ganti password berlaku 10 menit di sesi ini saja.
        PasswordOtp::where('user_id', $user->id)->delete();
        $request->session()->put('otp_verified', [
            'user_id' => $user->id,
            'until'   => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('password.reset.form')
            ->with('success', 'Kode OTP terverifikasi. Silakan buat password baru.');
    }

    /**
     * Langkah 3 - form password baru (hanya bisa dibuka setelah OTP terverifikasi).
     */
    public function showResetForm(Request $request)
    {
        if (!$this->verifiedUserId($request)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Verifikasi kode OTP dulu sebelum membuat password baru.']);
        }

        return view('auth.reset-password');
    }

    /**
     * Langkah 3 (proses): simpan password baru.
     */
    public function resetPassword(Request $request)
    {
        $userId = $this->verifiedUserId($request);
        if (!$userId) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Verifikasi kode OTP dulu sebelum membuat password baru.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'password.required'  => 'Password baru wajib diisi.',
            'password.min'       => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        User::whereKey($userId)->update(['password' => Hash::make($validated['password'])]);
        $request->session()->forget(['otp_verified', 'reset_email']);

        return redirect()->route('login')->with('success', 'Password berhasil diubah. Silakan login dengan password baru.');
    }

    /** ID user yang OTP-nya sudah terverifikasi dan masih dalam masa berlaku, atau null. */
    private function verifiedUserId(Request $request): ?int
    {
        $v = $request->session()->get('otp_verified');

        if (!is_array($v) || empty($v['user_id']) || ($v['until'] ?? 0) < now()->timestamp) {
            $request->session()->forget('otp_verified');
            return null;
        }

        return (int) $v['user_id'];
    }

    /**
     * Cari user dari email. Bila ada lebih dari satu akun dengan email yang sama,
     * tidak ada OTP yang dikirim (tidak jelas akun mana yang dimaksud) dan kejadian dicatat di log.
     */
    private function findUserByEmail(string $email): ?User
    {
        $users = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->limit(2)->get();

        if ($users->count() > 1) {
            logger()->warning('Reset password dibatalkan: email dipakai lebih dari satu akun.', ['email' => $email]);
            return null;
        }

        return $users->first();
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
