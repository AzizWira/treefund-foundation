<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailService;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class EmailAuthController extends Controller
{
    public function notice(Request $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', ['user' => $request->user()]);
    }

    public function resend(Request $request, EmailVerificationService $verification): mixed
    {
        $sent = $verification->issue($request->user());

        return back()->with('status', $sent ? 'Email berisi kode dan tautan verifikasi telah masuk antrean pengiriman.' : 'Tunggu 60 detik sebelum meminta email baru. Periksa juga folder spam.');
    }

    public function code(Request $request, EmailVerificationService $verification): mixed
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $verification->verifyCode($request->user(), $data['code']);

        return redirect()->route('dashboard')->with('success', 'Email Anda berhasil diverifikasi. Selamat datang di TreeFund.');
    }

    public function link(Request $request, string $id, string $hash, EmailVerificationService $verification): mixed
    {
        if (! $request->hasValidSignature()) {
            return response()->view('auth.verification-result', ['success' => false], 410);
        }
        $user = User::find($id);
        if (! $user || ! hash_equals(sha1($user->email), $hash)) {
            return response()->view('auth.verification-result', ['success' => false], 410);
        }
        try {
            $verification->verifyLink($user, (string) $request->query('token'));
        } catch (ValidationException $exception) {
            return response()->view('auth.verification-result', ['success' => false], 410);
        }

        return view('auth.verification-result', ['success' => true]);
    }

    public function forgot(Request $request): mixed
    {
        $data = $request->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => strtolower($data['email'])]);

        return back()->with('status', 'Jika email terdaftar, tautan reset kata sandi akan dikirim. Periksa kotak masuk dan folder spam.');
    }

    public function reset(Request $request): mixed
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(8)]]);
        $data['email'] = strtolower($data['email']);
        $status = Password::reset($data, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                event(new PasswordReset($user));
                app(EmailService::class)->send($user, 'security', [
                    'title' => 'Kata sandi Anda berhasil diatur ulang',
                    'body' => 'Kata sandi akun TreeFund Anda telah diganti. Sesi login sebelumnya telah diakhiri. Jika ini bukan Anda, segera gunakan pemulihan akun.',
                    'url' => route('password.request'), 'action' => 'Pulihkan Akun',
                ], false);
            });
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.']);
        }

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diganti. Silakan masuk dengan kata sandi baru.');
    }
}
