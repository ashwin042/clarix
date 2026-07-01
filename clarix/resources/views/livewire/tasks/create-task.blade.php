<div>
    <div class="max-w-2xl mx-auto py-8 px-4">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">New Task</h1>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Create a new task for your unit.</p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">
                ← Back to dashboard
            </a>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm">
            <form wire:submit="save" class="p-6 space-y-5">

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Title</label>
                    <input wire:model="title" type="text"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('title') border-red-400 @enderror"
                        placeholder="Task title">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Task Code + Unit (read-only for PM) --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Code</label>
                        <input wire:model="task_code" type="text"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_code') border-red-400 @enderror"
                            placeholder="e.g. CA_001">
                        @error('task_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                        <input type="text" value="{{ auth()->user()->unit?->name ?? '-' }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    </div>
                </div>

                {{-- Responsible PM (read-only) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Responsible PM</label>
                    <div class="relative">
                        <input type="text" value="{{ auth()->user()->name }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Automatically assigned to you.</p>
                </div>

                {{-- Assigned Supervisor --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Assigned Supervisor</label>
                    <select wire:model="assigned_admin_id"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('assigned_admin_id') border-red-400 @enderror">
                        <option value="">Unassigned</option>
                        @foreach($adminUsers as $adminUser)
                            <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_admin_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Task Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Type</label>
                    <select wire:model="task_type"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_type') border-red-400 @enderror">
                        <option value="">— Select type —</option>
                        <option value="tech">Tech</option>
                        <option value="content">Content</option>
                        <option value="accounts">Accounts</option>
                        <option value="maths">Maths</option>
                        <option value="nursing">Nursing</option>
                        <option value="science">Science</option>
                        <option value="civil">Civil</option>
                        <option value="others">Others</option>
                    </select>
                    @error('task_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Important Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Important Notes</label>
                    <textarea wire:model="important_notes" rows="3"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        placeholder="Optional instructions..."></textarea>
                </div>

                {{-- Files (optional) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                        Files <span class="text-gray-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>

                    {{-- Drop zone / file input --}}
                    <label class="flex flex-col items-center justify-center w-full border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg py-6 cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                        <svg class="w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-slate-400">Click to browse or drag & drop</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Any file type · max 50 MB each</p>
                        <input type="file" wire:model="uploads" multiple class="hidden">
                    </label>

                    {{-- Upload progress (indeterminate) --}}
                    <div wire:loading wire:target="uploads" class="mt-2 space-y-1.5">
                        <div class="upload-progress-track"></div>
                        <p class="flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Uploading your files…
                        </p>
                    </div>

                    {{-- Queued file list --}}
                    @if(count($uploads))
                        <ul class="mt-3 space-y-1.5">
                            @foreach($uploads as $i => $upload)
                                <li class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-slate-800/60 rounded-lg text-sm">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="truncate text-gray-800 dark:text-slate-200 font-medium">{{ $upload->getClientOriginalName() }}</span>
                                        <span class="text-xs text-gray-400 dark:text-slate-500 shrink-0">
                                            @php
                                                $bytes = $upload->getSize();
                                                echo $bytes < 1048576
                                                    ? round($bytes / 1024, 1) . ' KB'
                                                    : round($bytes / 1048576, 1) . ' MB';
                                            @endphp
                                        </span>
                                    </div>
                                    <button type="button" wire:click="removeFile({{ $i }})"
                                        class="ml-3 p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @error('uploads.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Priority + Deadline + Credits --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Priority</label>
                        <select wire:model="priority"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Deadline</label>
                        <input wire:model="deadline" type="date"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('deadline') border-red-400 @enderror">
                        @error('deadline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Credits</label>
                        <input wire:model="credit_amount" type="number" step="0.01" min="0"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('credit_amount') border-red-400 @enderror"
                            placeholder="0.00">
                        @error('credit_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100 dark:border-slate-800">
                    <a href="{{ route('dashboard') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create Task
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>
