<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /** Papéis que o usuário logado pode atribuir. */
    private function rolesDisponiveis(User $ator): array
    {
        return $ator->hasRole('super-admin')
            ? ['super-admin', 'admin-empresa', 'operador']
            : ['admin-empresa', 'operador'];
    }

    public function index(Request $request)
    {
        $ator = $request->user();

        $usuarios = User::with('empresa', 'roles')
            ->when(! $ator->hasRole('super-admin'), fn($q) => $q->where('empresa_id', $ator->empresa_id))
            ->orderBy('name')
            ->paginate(15);

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(Request $request)
    {
        return view('usuarios.create', [
            'usuario'  => new User(),
            'empresas' => $this->empresasSelecionaveis($request->user()),
            'roles'    => $this->rolesDisponiveis($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $ator = $request->user();

        $dados = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:4',
            'empresa_id' => 'required|exists:empresas,id',
            'role'       => ['required', Rule::in($this->rolesDisponiveis($ator))],
        ]);

        $empresaId = $this->resolverEmpresa($ator, $dados['empresa_id']);

        $usuario = User::create([
            'name'       => $dados['name'],
            'email'      => $dados['email'],
            'password'   => Hash::make($dados['password']),
            'empresa_id' => $empresaId,
        ]);

        $usuario->syncRoles([$dados['role']]);

        return redirect()->route('usuarios.index')->with('status', 'Usuário cadastrado.');
    }

    public function edit(Request $request, User $usuario)
    {
        $this->autorizarAlvo($request->user(), $usuario);

        return view('usuarios.edit', [
            'usuario'  => $usuario,
            'empresas' => $this->empresasSelecionaveis($request->user()),
            'roles'    => $this->rolesDisponiveis($request->user()),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $ator = $request->user();
        $this->autorizarAlvo($ator, $usuario);

        $dados = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password'   => 'nullable|string|min:4',
            'empresa_id' => 'required|exists:empresas,id',
            'role'       => ['required', Rule::in($this->rolesDisponiveis($ator))],
        ]);

        $usuario->update([
            'name'       => $dados['name'],
            'email'      => $dados['email'],
            'empresa_id' => $this->resolverEmpresa($ator, $dados['empresa_id']),
        ]);

        if (! empty($dados['password'])) {
            $usuario->update(['password' => Hash::make($dados['password'])]);
        }

        $usuario->syncRoles([$dados['role']]);

        return redirect()->route('usuarios.index')->with('status', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $usuario)
    {
        $ator = $request->user();
        $this->autorizarAlvo($ator, $usuario);

        if ($usuario->id === $ator->id) {
            return back()->withErrors(['usuario' => 'Você não pode remover o próprio usuário.']);
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', 'Usuário removido.');
    }

    // -------------------------------------------------------------------------

    /** admin-empresa só age sobre a própria empresa e nunca sobre super-admin. */
    private function autorizarAlvo(User $ator, User $alvo): void
    {
        if ($ator->hasRole('super-admin')) {
            return;
        }

        if ($alvo->empresa_id !== $ator->empresa_id || $alvo->hasRole('super-admin')) {
            abort(403);
        }
    }

    /** admin-empresa sempre cria/edita na própria empresa. */
    private function resolverEmpresa(User $ator, int $empresaIdSolicitada): int
    {
        return $ator->hasRole('super-admin') ? $empresaIdSolicitada : $ator->empresa_id;
    }

    private function empresasSelecionaveis(User $ator)
    {
        return $ator->hasRole('super-admin')
            ? Empresa::orderBy('nome_fantasia')->get()
            : Empresa::where('id', $ator->empresa_id)->get();
    }
}
