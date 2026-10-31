<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Modules\Identity\Actions\RequestPasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(PasswordResetLinkRequest $request, RequestPasswordReset $reset): RedirectResponse
    {
        $reset->handle($request->string('email')->toString());

        return back()->with('status', 'If the account exists, a password reset link has been sent.');
    }
}
