<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nova empresa</h2>
    </x-slot>
    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded p-6">
            <form action="{{ route('empresas.store') }}" method="POST">
                @include('empresas._form')
            </form>
        </div>
    </div>
</x-app-layout>
