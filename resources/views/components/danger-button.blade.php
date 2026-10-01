<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-error hover:bg-red-700 active:bg-red-800 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2 focus:ring-offset-background transition duration-150 cursor-pointer disabled:opacity-50']) }}>
    {{ $slot }}
</button>
