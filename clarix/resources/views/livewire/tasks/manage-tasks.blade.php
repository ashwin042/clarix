<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Tasks</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage and track all project tasks</p>
        </div>
        @if(!auth()->user()->isWriter())
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Task
        </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative flex-1 min-w-[200px] max-w-xs">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search tasks..."
                class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <select wire:model.live="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="submitted">Submitted</option>
            <option value="verified">Verified</option>
            <option value="completed">Completed</option>
        </select>
        <select wire:model.live="filterPriority" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
        @if(auth()->user()->isAdmin())
        <select wire:model.live="filterUnit" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All units</option>
            @foreach($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
        </select>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-visible">
        @if($tasks->count())
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('title')">
                            <div class="flex items-center gap-1">
                                Task
                                @if($sortBy === 'title')
                                    <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">PM</th>
                        @if(auth()->user()->isAdmin())
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Supervisor</th>
                        @endif
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('deadline')">
                            <div class="flex items-center gap-1">
                                Deadline
                                @if($sortBy === 'deadline')
                                    <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Writers</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($tasks as $task)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <div>
                                    <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-gray-900 dark:text-slate-100 hover:text-indigo-600 transition-colors">{{ $task->title }}</a>
                                    <p class="text-xs text-gray-400 dark:text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ $task->task_code }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->pm?->name ?? '—' }}</td>
                            @if(auth()->user()->isAdmin())
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '—' }}</td>
                            @endif
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($task->status) { 'pending' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400', 'in_progress' => 'bg-blue-100 text-blue-700', 'submitted' => 'bg-purple-100 text-purple-700', 'verified' => 'bg-teal-100 text-teal-700', 'completed' => 'bg-green-100 text-green-700' };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm {{ $task->deadline->isPast() && $task->status !== 'completed' ? 'text-red-600 font-medium' : 'text-gray-600 dark:text-slate-400' }}">
                                {{ $task->deadline->format('M d, Y') }}
                            </td>
                            <td class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-slate-300">{{ number_format($task->credit_amount, 2) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">
                                @if(auth()->user()->isAdmin() && $task->assignments->count())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($task->assignments as $assignment)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                        @endforeach
                                    </div>
                                @elseif(auth()->user()->isAdmin())
                                    <span class="text-gray-400 dark:text-slate-500">—</span>
                                @else
                                    {{ $task->assignments->count() }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($task->files->count())
                                    <div class="relative" x-data="{ open: false }">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            @click.outside="open = false"
                                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-colors text-xs font-medium"
                                            title="{{ $task->files->count() }} {{ $task->files->count() === 1 ? 'file' : 'files' }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            {{ $task->files->count() }}
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 top-8 z-20 w-64 bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-lg overflow-hidden"
                                        >
                                            <div class="px-3 py-2 border-b border-gray-100 dark:border-slate-800/60">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</p>
                                            </div>
                                            <ul class="divide-y divide-gray-100 dark:divide-slate-800/60 max-h-48 overflow-y-auto">
                                                @foreach($task->files as $file)
                                                <li class="flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <div class="min-w-0">
                                                            <p class="text-xs font-medium text-gray-800 dark:text-slate-200 truncate">{{ $file->original_name }}</p>
                                                            <p class="text-[10px] text-gray-400 dark:text-slate-500">{{ $file->file_size_formatted }}</p>
                                                        </div>
                                                    </div>
                                                    <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                                                       class="ml-2 px-2 py-1 text-[10px] font-medium text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors shrink-0">
                                                        Download
                                                    </a>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @else
                                    @can('uploadFiles', $task)
                                    <button
                                        type="button"
                                        @click="$dispatch('open-upload-modal', { uploadUrl: '{{ route('tasks.files.store', $task) }}' })"
                                        title="Upload files"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800/50 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                    @else
                                    <span class="text-gray-400 dark:text-slate-500 text-sm">—</span>
                                    @endcan
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tasks.show', $task) }}"
                                        class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
                                    @if(!auth()->user()->isWriter())
                                    <button wire:click="openEdit({{ $task->id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Edit</button>
                                    @endif
                                    @if(auth()->user()->isAdmin())
                                    <button wire:click="openDeleteModal({{ $task->id }}, '{{ $task->task_code }} - {{ $task->title }}')"
                                        class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 rounded-lg transition-colors">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($tasks->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $tasks->links() }}</div>
            @endif
        @else
            <div class="py-16 text-center dark:text-slate-400">
                <div class="w-12 h-12 mx-auto bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-slate-400">No tasks found.</p>
            </div>
        @endif
    </div>

    {{-- Task Modal --}}
    <x-livewire-modal :title="$editingId ? 'Edit Task' : 'New Task'" maxWidth="xl">
        <form wire:submit="save" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Title</label>
                <input wire:model="title" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('title') border-red-400 @enderror"
                    placeholder="Task title">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Code</label>
                    <input wire:model="task_code" type="text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_code') border-red-400 @enderror"
                        placeholder="e.g. CA_001">
                    @error('task_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                    @if(auth()->user()->isAdmin())
                        <select wire:model.live="unit_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('unit_id') border-red-400 @enderror">
                            <option value="">Select unit</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" value="{{ $units->first()?->name ?? '—' }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    @endif
                    @error('unit_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- PM field --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Responsible PM</label>
                @if(auth()->user()->isAdmin())
                    @if($unit_id && $pmsForUnit->count())
                        <select wire:model="pm_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('pm_id') border-red-400 @enderror">
                            <option value="">Select PM</option>
                            @foreach($pmsForUnit as $pm)
                                <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    @elseif($unit_id)
                        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2.5">No PMs found in this unit. Assign a PM to this unit first.</p>
                    @else
                        <input type="text" value="Select a unit first" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-400 dark:text-slate-500 cursor-not-allowed">
                    @endif
                @else
                    <div class="relative">
                        <input type="text" value="{{ auth()->user()->name }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Automatically assigned to you</p>
                @endif
                @error('pm_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Assigned Supervisor --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Assigned Supervisor</label>
                <select wire:model="assigned_admin_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('assigned_admin_id') border-red-400 @enderror">
                    <option value="">Unassigned</option>
                    @foreach($adminUsers as $adminUser)
                        <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
                    @endforeach
                </select>
                @error('assigned_admin_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Important Notes</label>
                <textarea wire:model="important_notes" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    placeholder="Optional instructions..."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Priority</label>
                    <select wire:model="priority"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Status</label>
                    @if(auth()->user()->isAdmin())
                        <select wire:model="status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                            <option value="verified">Verified</option>
                            <option value="completed">Completed</option>
                        </select>
                    @else
                        <input type="text" value="Pending" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Deadline</label>
                    <input wire:model="deadline" type="date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('deadline') border-red-400 @enderror">
                    @error('deadline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Credit Amount</label>
                    <input wire:model="credit_amount" type="number" min="0" step="0.01" placeholder="e.g. 1.5"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('credit_amount') border-red-400 @enderror">
                    @error('credit_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if(!$editingId)
            <p class="text-xs text-gray-400 dark:text-slate-500 bg-gray-50 dark:bg-slate-950/50 border border-gray-200 dark:border-slate-800 rounded-lg px-3 py-2.5">
                Files can be uploaded from the task detail page after creation.
            </p>
            @endif

            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $editingId ? 'Save Changes' : 'Create Task' }}
                </button>
                <button type="button" wire:click="$set('showModal', false)"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </x-livewire-modal>

    {{-- Shared inline upload modal (triggered from Files column) --}}
    <div
        x-data="{
            show: false,
            uploadUrl: '',
            dragging: false,
            fileObjects: [],
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            },
            addFiles(fileList) {
                Array.from(fileList).forEach(f => {
                    const exists = this.fileObjects.find(e => e.name === f.name && e.size === f.size);
                    if (!exists) this.fileObjects.push(f);
                });
                this.syncInput();
            },
            removeFile(index) {
                this.fileObjects.splice(index, 1);
                this.syncInput();
            },
            syncInput() {
                const dT = new DataTransfer();
                this.fileObjects.forEach(f => dT.items.add(f));
                this.$refs.fileInput.files = dT.files;
            },
            reset() {
                this.fileObjects = [];
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },
            handleDrop(e) {
                this.dragging = false;
                if (e.dataTransfer?.files.length) this.addFiles(e.dataTransfer.files);
            }
        }"
        x-on:open-upload-modal.window="uploadUrl = $event.detail.uploadUrl; fileObjects = []; show = true"
        x-on:keydown.escape.window="show = false; reset()"
        x-show="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false; reset()"></div>
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Upload Files</h3>
                <button @click="show = false; reset()" class="text-gray-400 dark:text-slate-500 hover:text-gray-600 p-1 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="uploadUrl" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Files <span class="text-gray-400 dark:text-slate-500 font-normal">(max 10 MB each)</span></label>
                    <div
                        class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300 hover:bg-gray-50 dark:bg-slate-950/50'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-600 dark:text-slate-400">Drag & drop files here, or <span class="text-indigo-600 font-medium">browse</span></p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Multiple files supported</p>
                        <input
                            x-ref="fileInput"
                            type="file"
                            name="files[]"
                            multiple
                            class="sr-only"
                            @change="addFiles($event.target.files)"
                        >
                    </div>
                    <template x-if="fileObjects.length > 0">
                        <ul class="mt-2 space-y-1">
                            <template x-for="(f, i) in fileObjects" :key="i">
                                <li class="flex items-center justify-between text-xs bg-gray-50 dark:bg-slate-950/50 border border-gray-200 dark:border-slate-800/60 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate text-gray-700 dark:text-slate-300" x-text="f.name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-2 shrink-0">
                                        <span class="text-gray-400 dark:text-slate-500" x-text="formatSize(f.size)"></span>
                                        <button type="button" @click.stop="removeFile(i)"
                                            class="text-gray-400 dark:text-slate-500 hover:text-red-500 transition-colors p-0.5 rounded hover:bg-red-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Upload
                    </button>
                    <button type="button" @click="show = false; reset()"
                        class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-delete-confirm-modal
        title="Delete Task"
        :description="'You are about to delete: ' . $deletingName"
        :consequences="['Delete all associated files', 'Remove all writer assignments', 'This action cannot be undone']"
    />
</div>
