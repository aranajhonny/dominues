<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->canAccessBackoffice()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Acceso restringido al personal autorizado de Dominues.']);
            }

            if (! $user->active) {
                Auth::logout();
                return back()->withErrors(['email' => 'Cuenta desactivada.']);
            }

            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        return back()->withErrors(['email' => 'Credenciales inválidas.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}