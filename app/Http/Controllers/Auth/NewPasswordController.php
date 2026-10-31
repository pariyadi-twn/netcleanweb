<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Modules\Identity\Actions\ResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(NewPasswordRequest $request, ResetPassword $reset): RedirectResponse
    {
        $status = $reset->handle($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
