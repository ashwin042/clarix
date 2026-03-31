<x-app-layout pageTitle="Units">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Units</h1>
                <p class="text-sm text-gray-500 mt-1">Manage organisational units</p>
            </div>
            <a href="{{ route('admin.units.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Unit
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @if($units->count())
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Members</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($units as $unit)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-medium text-sm text-gray-900">{{ $unit->name }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $unit->users_count }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500">{{ $unit->created_at->format('M d, Y') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.units.edit', $unit) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</a>
                                        <form action="{{ route('admin.units.destroy', $unit) }}" method="POST"
                                              onsubmit="return confirm('Delete this unit?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-medium">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($units->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100">
                        {{ $units->links() }}
                    </div>
                @endif
            @else
                <div class="px-5 py-16 text-center">
                    <p class="text-gray-500 text-sm">No units created yet.</p>
                    <a href="{{ route('admin.units.create') }}" class="mt-2 inline-flex text-indigo-600 text-sm font-medium hover:underline">Create first unit</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
