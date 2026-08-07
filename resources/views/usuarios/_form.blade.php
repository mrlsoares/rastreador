@csrf
<div class="space-y-4">
    <div>
        <x-input-label for="name" value="Nome" />
        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $usuario->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="email" value="E-mail" />
        <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email', $usuario->email)" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="password" value="Senha" />
        <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" autocomplete="new-password" />
        <p class="text-xs text-gray-500 mt-1">{{ $usuario->exists ? 'Deixe em branco para manter a senha atual.' : '' }}</p>
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="empresa_id" value="Empresa" />
        <select id="empresa_id" name="empresa_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:text-gray-300" required>
            @foreach ($empresas as $empresa)
                <option value="{{ $empresa->id }}" @selected(old('empresa_id', $usuario->empresa_id) == $empresa->id)>{{ $empresa->nome_fantasia }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('empresa_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="role" value="Papel" />
        <select id="role" name="role" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm dark:bg-gray-900 dark:text-gray-300" required>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role', $usuario->roles->first()?->name) === $role)>{{ $role }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>
    <div class="flex items-center gap-3">
        <x-primary-button>Salvar</x-primary-button>
        <a href="{{ route('usuarios.index') }}" class="text-gray-600">Cancelar</a>
    </div>
</div>
