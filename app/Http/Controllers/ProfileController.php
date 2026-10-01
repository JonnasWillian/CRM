<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Contas\ExclusaoDeConta;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, ExclusaoDeConta $exclusao): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'impedimentosDeExclusao' => $exclusao->impedimentos($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     *
     * A senha é conferida primeiro (um impedimento não deve ser revelado a
     * quem só está com a sessão aberta de outra pessoa); depois a regra de
     * negócio. Só então a sessão é encerrada.
     */
    public function destroy(Request $request, ExclusaoDeConta $exclusao): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $exclusao->excluir($user);

        // logoutCurrentDevice() e não logout(): este último cicla o
        // remember_token (SessionGuard::cycleRememberToken -> $user->save()),
        // e como a linha já foi apagada esse save() faria um INSERT — o
        // usuário "excluído" ressuscitava sozinho assim que a sessão era
        // encerrada. logoutCurrentDevice() não mexe no remember_token.
        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
