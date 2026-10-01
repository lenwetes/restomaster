<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-surface-container-low hover:bg-surface-container border border-surface-container-highest text-on-surface font-semibold text-xs uppercase tracking-wider rounded-xl shadow-sm hover:shadow focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2 focus:ring-offset-background disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 cursor-pointer']) }}>
    {{ $slot }}
</button>
