@csrf
<div class="space-y-4">
    <div>
        <x-input-label for="razao_social" value="Razão social" />
        <x-text-input id="razao_social" name="razao_social" class="block mt-1 w-full" :value="old('razao_social', $empresa->razao_social)" required />
        <x-input-error :messages="$errors->get('razao_social')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="nome_fantasia" value="Nome fantasia" />
        <x-text-input id="nome_fantasia" name="nome_fantasia" class="block mt-1 w-full" :value="old('nome_fantasia', $empresa->nome_fantasia)" />
        <x-input-error :messages="$errors->get('nome_fantasia')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="cnpj" value="CNPJ/CPF" />
        <x-text-input id="cnpj" name="cnpj" class="block mt-1 w-full" :value="old('cnpj', $empresa->cnpj)" />
        <x-input-error :messages="$errors->get('cnpj')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="telefone" value="Telefone" />
        <x-text-input id="telefone" name="telefone" class="block mt-1 w-full" :value="old('telefone', $empresa->telefone)" />
        <x-input-error :messages="$errors->get('telefone')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:text-gray-300">
            @foreach (['ativo', 'inativo'] as $s)
                <option value="{{ $s }}" @selected(old('status', $empresa->status ?? 'ativo') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
    <div class="flex items-center gap-3">
        <x-primary-button>Salvar</x-primary-button>
        <a href="{{ route('empresas.index') }}" class="text-gray-600">Cancelar</a>
    </div>
</div>
