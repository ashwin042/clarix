@props(['item'])

{{--
    The inside of a disclosure panel, shared by both of the FAQ component's
    variants so a Help Center answer and a documentation topic are set in the
    same type.

    An item carries prose, steps, or both. Steps are numbered because the ones
    that need them are procedures — do this, then this — and a bulleted list
    would lose the ordering that is the whole point.

    An optional 'note' renders last, in a quieter register: it is for saying
    something about the answer rather than in it — that a topic is still a
    draft, most usefully.
--}}

<div {{ $attributes->merge(['class' => 'text-[14px] leading-relaxed text-[#4A4F63]']) }}>

    @if ($item['a'] ?? null)
        <p>{{ $item['a'] }}</p>
    @endif

    @if ($item['steps'] ?? null)
        <ol class="{{ ($item['a'] ?? null) ? 'mt-3.5' : '' }} space-y-2">
            @foreach ($item['steps'] as $n => $step)
                <li class="flex gap-3">
                    <span class="mt-[1px] flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-[#EEF0FF] font-mono-ui text-[9.5px] font-medium text-indigo-700">
                        {{ $n + 1 }}
                    </span>
                    <span class="min-w-0 flex-1">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($item['note'] ?? null)
        <p class="mt-4 border-t border-black/[.07] pt-3 text-[12px] leading-relaxed text-[#8A8FA0]">
            {{ $item['note'] }}
        </p>
    @endif
</div>
