<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Login for admins and merchants. The very first visit creates the platform
 * admin (setup); after that, setup is closed.
 */
class AuthController extends Controller
{
    public function showSetup(): View|RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User($data);
        $user->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.settings.edit')->with('status', 'Admin account created. Now connect your OxaPay account.');
    }

    public function showLogin(): View|RedirectResponse
    {
        if (! $this->adminExists()) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Wrong email or password.']);
        }

        $user = Auth::user();

        if ($user->isMerchant() && ! $user->merchant?->isActive()) {
            Auth::logout();

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Your account is suspended. Please contact support.']);
        }

        $request->session()->regenerate();

        return redirect()->intended($user->homeRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function adminExists(): bool
    {
        return User::where('role', User::ROLE_ADMIN)->exists();
    }
}
