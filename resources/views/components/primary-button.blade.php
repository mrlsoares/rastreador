<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#2468f2] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#1d5ce0] focus:bg-[#1d5ce0] active:bg-[#1a52c9] focus:outline-none focus:ring-2 focus:ring-[#2468f2] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
