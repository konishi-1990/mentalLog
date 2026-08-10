<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->forgetForbiddenIntendedUrl($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * ログイン前に管理画面へアクセスした際の遷移先が残っていると、
     * 一般ユーザのログイン後に 403 となるため破棄する。
     */
    private function forgetForbiddenIntendedUrl(Request $request): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        $intended = $request->session()->get('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $path = trim(parse_url($intended, PHP_URL_PATH) ?: '', '/');

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            $request->session()->forget('url.intended');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
