<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): mixed
    {
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
}
