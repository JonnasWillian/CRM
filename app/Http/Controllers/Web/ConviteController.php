<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Equipe\Convites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Aceitar convite (visitante): nome e senha; a conta nasce na empresa e no papel do convite. */
class ConviteController extends Controller
{
    public function show(string $token): Response
    {
        $convite = Convites::porToken($token) ?? abort(404);

        return Inertia::render('Equipe/AceitarConvite', [
            'token' => $token,
            'convite' => [
                'email' => $convite->email,
                'papel' => $convite->papel,
                'empresa' => $convite->tenant?->nome,
                'convidado_por' => $convite->convidadoPor()->withoutGlobalScopes()->value('name'),
                'estado' => Convites::estado($convite),
            ],
        ]);
    }

    public function store(Request $request, Convites $convites, string $token): RedirectResponse
    {
        $convite = Convites::porToken($token) ?? abort(404);
        abort_if(Convites::estado($convite) !== 'pendente', 410, 'Este convite não está mais válido.');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $convites->aceitar($convite, $dados['name'], $dados['password']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('sucesso', "Bem-vindo à {$convite->tenant->nome}. Comece pelo Pipeline.");
    }
}
