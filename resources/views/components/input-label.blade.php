@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-bold text-xs uppercase tracking-wider text-on-surface-variant mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
