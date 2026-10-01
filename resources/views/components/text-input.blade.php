@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-container-lowest text-on-surface border border-surface-container-highest placeholder:text-outline/60 focus:border-primary focus:ring-1 focus:ring-primary rounded-xl text-sm font-medium px-4 py-2.5 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed']) }}>
