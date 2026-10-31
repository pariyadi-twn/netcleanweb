<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmMfaRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Actions\VerifyMfaCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticate): RedirectResponse
    {
        try {
            $user = $authenticate->credentials(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (AuthenticationException) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        if ($user->requiresMfa() && ! $user->hasConfirmedMfa()) {
            Auth::guard('web')->login($user);
            $request->session()->put('mfa.setup_required', true);

            return redirect()->route('mfa.setup');
        }

        if ($user->requiresMfa()) {
            $request->session()->put('auth.pending_user_id', $user->getKey());

            return redirect()->route('mfa.challenge');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        if (! $request->session()->has('auth.pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(ConfirmMfaRequest $request, VerifyMfaCode $verify): RedirectResponse
    {
        $userId = $request->session()->get('auth.pending_user_id');
        $user = $userId === null ? null : User::query()->find($userId);

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $verify->handle($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        $request->session()->forget('auth.pending_user_id');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
