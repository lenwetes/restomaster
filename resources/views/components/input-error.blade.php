@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-xs text-error font-semibold mt-1.5 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">error</span>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif

