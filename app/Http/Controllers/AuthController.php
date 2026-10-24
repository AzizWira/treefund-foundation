<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): mixed
    {
        $request->merge(['email' => strtolower((string) $request->input('email'))]);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        $request->session()->forget('url.intended');

        return redirect()->route($request->user()->hasVerifiedEmail() ? 'dashboard' : 'verification.notice');
    }

    public function register(Request $request): mixed
    {
        $request->merge(['email' => strtolower((string) $request->input('email'))]);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users', 'password' => ['required', 'confirmed', Password::min(8)], 'role' => 'required|in:donor,partner']);
        $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->save();
        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return redirect()->route('verification.notice')->with('status', 'Akun berhasil dibuat. Periksa email untuk kode dan tautan verifikasi.');
    }

    public function logout(Request $request): mixed
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function profile(Request $request): mixed
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): mixed
    {
        $user = $request->user();
        $request->merge(['email' => strtolower((string) $request->input('email', $user->email))]);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)], 'phone' => 'nullable|string|max:25', 'current_password' => 'nullable|required_with:password|current_password', 'password' => ['nullable', 'confirmed', Password::min(8)]]);
        $emailChanged = $data['email'] !== $user->email;
        if ($emailChanged && ! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Masukkan kata sandi saat ini untuk mengubah alamat email.']);
        }
        DB::transaction(function () use ($user, $data, $request, $emailChanged): void {
            $oldUser = clone $user;
            $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
                app(EmailService::class)->send($oldUser, 'security', ['title' => 'Kata sandi akun Anda berubah', 'body' => 'Kata sandi akun TreeFund Anda telah diganti dari pengaturan akun. Jika bukan Anda, segera pulihkan akun.', 'url' => route('password.request'), 'action' => 'Pulihkan Akun'], false);
            }
            if ($emailChanged) {
                $user->email = $data['email'];
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
                DB::table('email_verification_challenges')->where('user_id', $user->id)->delete();
                if ($oldUser->hasVerifiedEmail()) {
                    app(EmailService::class)->send($oldUser, 'security', ['title' => 'Alamat email akun Anda diubah', 'body' => 'Alamat email pada akun TreeFund Anda telah diganti. Jika perubahan ini bukan tindakan Anda, segera hubungi pengelola.'], false);
                }
            }
            $user->save();
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }
        });
        if ($emailChanged) {
            return redirect()->route('verification.notice')->with('status', 'Alamat email diperbarui. Verifikasi kembali menggunakan kode atau tautan pada email baru.');
        }

        return back()->with('success', 'Pengaturan akun disimpan.');
    }
}
