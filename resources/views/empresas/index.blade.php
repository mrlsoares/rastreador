<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Empresas</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mb-4">
            <a href="{{ route('empresas.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded">Nova empresa</a>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-800 dark:text-gray-200">
                <thead class="border-b dark:border-gray-700">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">Fantasia</th>
                        <th class="p-3">Razão social</th>
                        <th class="p-3">CNPJ</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($empresas as $empresa)
                        <tr class="border-b dark:border-gray-700">
                            <td class="p-3">{{ $empresa->id }}</td>
                            <td class="p-3">{{ $empresa->nome_fantasia }}</td>
                            <td class="p-3">{{ $empresa->razao_social }}</td>
                            <td class="p-3">{{ $empresa->cnpj }}</td>
                            <td class="p-3">{{ $empresa->status }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('empresas.edit', $empresa) }}" class="text-indigo-600">Editar</a>
                                <form action="{{ route('empresas.destroy', $empresa) }}" method="POST" class="inline" onsubmit="return confirm('Remover empresa?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 ml-2">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $empresas->links() }}</div>
    </div>
</x-app-layout>
