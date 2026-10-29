<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Login');
    }

    public function showRegister(Request $request)
    {
        $existing = $this->registeredUserFromCookie($request);

        return Inertia::render('Register', [
            'registration_status' => $existing?->status,
        ]);
    }

    public function register(Request $request)
    {
        if ($this->registeredUserFromCookie($request)) {
            throw ValidationException::withMessages([
                'register' => 'Browser ini sudah pernah dipakai untuk mendaftar.',
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
            'status' => 'pending',
        ]);

        return redirect('/register')
            ->withCookie(cookie('nb_registered', (string) $user->id, 60 * 24 * 365));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Email atau password salah.']);
        }

        if ($user->status === 'pending') {
            return back()->withErrors(['email' => 'Akun kamu masih menunggu persetujuan admin.']);
        }

        if ($user->status === 'rejected') {
            return back()->withErrors(['email' => 'Akun kamu ditolak. Hubungi admin.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    private function registeredUserFromCookie(Request $request): ?User
    {
        $id = $request->cookie('nb_registered');

        if (!$id || !ctype_digit((string) $id)) {
            return null;
        }

        $user = User::find((int) $id);

        // Akun yang dihapus atau ditolak admin tidak lagi memblokir browser ini
        return ($user && $user->status !== 'rejected') ? $user : null;
    }
}