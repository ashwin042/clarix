@props(['column', 'label', 'sortColumn', 'sortDirection'])

<th scope="col"
    wire:click="sortBy('{{ $column }}')"
    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:text-gray-700 whitespace-nowrap">
    <div class="flex items-center gap-1">
        {{ $label }}
        <span class="flex flex-col leading-none">
            <svg class="w-2.5 h-2.5 {{ $sortColumn === $column && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3l6 7H4l6-7z" clip-rule="evenodd" />
            </svg>
            <svg class="w-2.5 h-2.5 {{ $sortColumn === $column && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 17l-6-7h12l-6 7z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>
</th>
