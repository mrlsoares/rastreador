<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Usuários</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded bg-red-100 text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4">
            <a href="{{ route('usuarios.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded">Novo usuário</a>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="border-b dark:border-gray-700">
                    <tr>
                        <th class="p-3">Nome</th>
                        <th class="p-3">E-mail</th>
                        <th class="p-3">Empresa</th>
                        <th class="p-3">Papel</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $usuario)
                        <tr class="border-b dark:border-gray-700">
                            <td class="p-3">{{ $usuario->name }}</td>
                            <td class="p-3">{{ $usuario->email }}</td>
                            <td class="p-3">{{ $usuario->empresa?->nome_fantasia ?? '—' }}</td>
                            <td class="p-3">{{ $usuario->roles->pluck('name')->join(', ') }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('usuarios.edit', $usuario) }}" class="text-indigo-600">Editar</a>
                                <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST" class="inline" onsubmit="return confirm('Remover usuário?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 ml-2">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $usuarios->links() }}</div>
    </div>
</x-app-layout>
