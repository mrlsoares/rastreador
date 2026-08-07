<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index()
    {
        $empresas = Empresa::orderBy('nome_fantasia')->paginate(15);

        return view('empresas.index', compact('empresas'));
    }

    public function create()
    {
        return view('empresas.create', ['empresa' => new Empresa()]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        Empresa::create($dados);

        return redirect()->route('empresas.index')->with('status', 'Empresa cadastrada.');
    }

    public function edit(Empresa $empresa)
    {
        return view('empresas.edit', compact('empresa'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        $dados = $this->validar($request, $empresa->id);

        $empresa->update($dados);

        return redirect()->route('empresas.index')->with('status', 'Empresa atualizada.');
    }

    public function destroy(Empresa $empresa)
    {
        if ($empresa->id === 1) {
            return back()->withErrors(['empresa' => 'A empresa padrão (1) não pode ser removida.']);
        }

        $empresa->delete();

        return redirect()->route('empresas.index')->with('status', 'Empresa removida.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'razao_social'  => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'cnpj'          => 'nullable|string|max:20|unique:empresas,cnpj' . ($id ? ",{$id}" : ''),
            'telefone'      => 'nullable|string|max:20',
            'status'        => 'required|in:ativo,inativo',
        ]);
    }
}
