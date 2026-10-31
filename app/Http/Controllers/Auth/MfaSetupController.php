<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmMfaRequest;
use App\Modules\Identity\Actions\BeginMfaEnrollment;
use App\Modules\Identity\Actions\ConfirmMfaEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MfaSetupController extends Controller
{
    public function show(Request $request, BeginMfaEnrollment $begin): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasConfirmedMfa()) {
            return redirect()->route('dashboard');
        }

        $enrollment = $begin->handle($user);

        return view('auth.mfa-setup', $enrollment);
    }

    public function confirm(ConfirmMfaRequest $request, ConfirmMfaEnrollment $confirm): RedirectResponse
    {
        $codes = $confirm->handle($request->user(), $request->string('code')->toString());

        $request->session()->forget('mfa.setup_required');

        return redirect()->route('dashboard')
            ->with('status', 'Multi-factor authentication is now enabled.')
            ->with('recovery_codes', $codes);
    }
}
