<div>
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('tasks.index') }}" class="text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:text-gray-500 transition-colors">Tasks</a>
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm text-gray-600 dark:text-gray-400 dark:text-gray-500">{{ $task->task_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $task->title }}</h1>
            <div class="flex items-center flex-wrap gap-x-4 gap-y-1 mt-2">
                <span class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500">{{ $task->unit->name }}</span>
                <span class="text-gray-300">·</span>
                @php
                    $sc = match($task->status) { 'pending' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500', 'in_progress' => 'bg-blue-100 text-blue-700', 'submitted' => 'bg-purple-100 text-purple-700', 'verified' => 'bg-teal-100 text-teal-700', 'completed' => 'bg-green-100 text-green-700' };
                    $pc = match($task->priority) { 'high' => 'bg-red-100 text-red-700', 'medium' => 'bg-yellow-100 text-yellow-700', 'low' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500' };
                @endphp
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pc }}">{{ ucfirst($task->priority) }}</span>
                <span class="text-sm {{ $task->deadline->isPast() && $task->status !== 'completed' ? 'text-red-600 font-medium' : 'text-gray-500 dark:text-gray-400 dark:text-gray-500' }}">
                    Due {{ $task->deadline->format('M d, Y') }}
                </span>
            </div>
        </div>

        {{-- Status quick-change (admin/pm) --}}
        @if(!auth()->user()->isWriter())
        <div class="flex items-center gap-2 flex-shrink-0">
            <select wire:change="updateTaskStatus($event.target.value)"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="pending" {{ $task->status === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in_progress" {{ $task->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="submitted" {{ $task->status === 'submitted' ? 'selected' : '' }}>Submitted</option>
                <option value="verified" {{ $task->status === 'verified' ? 'selected' : '' }}>Verified</option>
                <option value="completed" {{ $task->status === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-6">

        {{-- Main column --}}
        <div class="col-span-2 space-y-5">

            {{-- Details card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Details</h2>
                <dl class="grid grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500 mb-1">Responsible PM</dt>
                        <dd class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $task->pm?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500 mb-1">Created by</dt>
                        <dd class="text-sm text-gray-800 dark:text-gray-200">{{ $task->creator->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500 mb-1">Credit Amount</dt>
                        <dd class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format($task->credit_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500 mb-1">Code</dt>
                        <dd class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $task->task_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400 dark:text-gray-500 mb-1">Unit</dt>
                        <dd class="text-sm text-gray-700 dark:text-gray-300">{{ $task->unit->name }}</dd>
                    </div>
                </dl>
                @if($task->important_notes)
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 font-medium uppercase tracking-wider">Important Notes</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $task->important_notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Writers / Assignments --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Assigned Writers
                        <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500 rounded-full">{{ $task->assignments->count() }}</span>
                    </h2>
                    @can('assign', $task)
                    <button wire:click="openAssignModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Assign Writer
                    </button>
                    @endcan
                </div>

                @if($task->assignments->count())
                    <div class="space-y-2">
                        @foreach($task->assignments as $assignment)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <span class="text-xs font-semibold text-indigo-600">
                                            {{ strtoupper(substr($assignment->writer->name, 0, 2)) }}
                                        </span>
                                    </div>
                                    <div>
                                        {{-- PM sees "Writer" not real name --}}
                                        @if(auth()->user()->isWriter())
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">You</p>
                                        @elseif(auth()->user()->isPm())
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 text-gray-400 dark:text-gray-500 italic">Writer</p>
                                        @else
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $assignment->writer->name }}</p>
                                        @endif
                                        @php
                                            $asc = match($assignment->status) { 'pending' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500', 'in_progress' => 'bg-blue-100 text-blue-700', 'ready_for_review' => 'bg-teal-100 text-teal-700' };
                                        @endphp
                                        <span class="inline-flex mt-0.5 px-2 py-0 rounded text-xs font-medium {{ $asc }}">
                                            {{ str_replace('_', ' ', ucfirst($assignment->status)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    {{-- Writer sets their own status --}}
                                    @if(auth()->id() === $assignment->writer_id)
                                        <select wire:change="updateAssignmentStatus({{ $assignment->id }}, $event.target.value)"
                                            class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                            <option value="pending" {{ $assignment->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="in_progress" {{ $assignment->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                            <option value="ready_for_review" {{ $assignment->status === 'ready_for_review' ? 'selected' : '' }}>Ready for Review</option>
                                        </select>
                                    @endif
                                    @can('assign', $task)
                                        <button wire:click="removeAssignment({{ $assignment->id }})"
                                            wire:confirm="Remove this assignment?"
                                            class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500">No writers assigned yet.</p>
                @endif
            </div>

            {{-- Files --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Files
                        <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500 rounded-full">{{ $task->files->count() }}</span>
                    </h2>
                    @can('uploadFiles', $task)
                    <button wire:click="openUploadModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload Files
                    </button>
                    @endcan
                </div>

                @if($task->files->count())
                    <div class="space-y-2">
                        @foreach($task->files as $file)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-xl">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-800 dark:text-gray-200 font-medium truncate">{{ $file->original_name }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">
                                            {{ $file->file_size_formatted }}
                                        @if(!auth()->user()->isWriter())
                                            · {{ $file->uploader->name }}
                                        @endif
                                            · {{ $file->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Download</a>
                                    @can('uploadFiles', $task)
                                    <button wire:click="deleteFile({{ $file->id }})"
                                        wire:confirm="Delete this file?"
                                        class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500">No files uploaded yet.</p>
                @endif
            </div>

        </div>

        {{-- Notes sidebar --}}
        <div class="space-y-5">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Notes
                    <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500 rounded-full">{{ $task->notes->count() }}</span>
                </h2>

                @if($task->notes->count())
                    <div class="space-y-3 mb-4 max-h-72 overflow-y-auto pr-1">
                        @foreach($task->notes as $n)
                            <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl">
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $n->note }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ $n->author->name }} · {{ $n->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">No notes yet.</p>
                @endif

                <form wire:submit="addNote">
                    <textarea wire:model="note" rows="3" placeholder="Add a note..."
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none @error('note') border-red-400 @enderror"></textarea>
                    @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <button type="submit"
                        class="mt-2 w-full py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-xl hover:bg-gray-700 transition-colors">
                        Add Note
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Assign Writers Modal --}}
    <div
        x-data="{ show: @entangle('showAssignModal').live }"
        x-show="show"
        x-on:keydown.escape.window="show = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false"></div>

        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Assign Writers</h3>
                <button @click="show = false" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:text-gray-500 p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                @error('selectedWriters') <p class="mb-3 text-xs text-red-600">{{ $message }}</p> @enderror

                @if($availableWriters->count())
                    <div class="space-y-2 max-h-72 overflow-y-auto mb-4">
                        @foreach($availableWriters as $writer)
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-300 hover:bg-indigo-50 cursor-pointer transition-colors"
                                :class="$wire.selectedWriters.includes({{ $writer->id }}) ? 'border-indigo-400 bg-indigo-50' : ''">
                                <input type="checkbox" wire:model="selectedWriters" value="{{ $writer->id }}"
                                    class="w-4 h-4 text-indigo-600 rounded">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-semibold text-indigo-600">{{ strtoupper(substr($writer->name, 0, 2)) }}</span>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $writer->name }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $writer->active_tasks > 3 ? 'bg-red-100 text-red-600' : ($writer->active_tasks > 1 ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600') }}">
                                        {{ $writer->active_tasks }} active
                                    </span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex gap-3">
                        <button wire:click="assign"
                            class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                            Assign Selected
                        </button>
                        <button @click="show = false"
                            class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            Cancel
                        </button>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500 text-center py-6">All available writers are already assigned.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Upload Files Modal --}}
    <div
        x-data="{
            show: @entangle('showUploadModal').live,
            dragging: false,
            fileObjects: [],
            _syncing: false,
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            },
            addFiles(fileList) {
                if (this._syncing) return;
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
                const input = this.$refs.fileInput;
                input.files = dT.files;
                this._syncing = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                this.$nextTick(() => { this._syncing = false; });
            },
            reset() {
                this.fileObjects = [];
                this._syncing = false;
                const input = this.$refs.fileInput;
                if (input) { input.value = ''; }
            },
            handleDrop(e) {
                this.dragging = false;
                const dt = e.dataTransfer;
                if (dt && dt.files.length) this.addFiles(dt.files);
            }
        }"
        x-show="show"
        x-on:keydown.escape.window="show = false; reset()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false; reset()"></div>
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Upload Files</h3>
                <button @click="show = false" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:text-gray-500 p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="uploadFiles" class="p-6 space-y-4">
                {{-- Drag & Drop Zone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Files <span class="text-gray-400 dark:text-gray-500 font-normal">(max 10 MB each)</span></label>
                    <div
                        class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300 hover:bg-gray-50 dark:bg-gray-900'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-600 dark:text-gray-400 dark:text-gray-500">Drag & drop files here, or <span class="text-indigo-600 font-medium">browse</span></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Multiple files supported</p>
                        <input
                            id="upload-file-input"
                            x-ref="fileInput"
                            wire:model="pendingFiles"
                            type="file"
                            multiple
                            class="sr-only"
                            @change="addFiles($event.target.files)"
                        >
                    </div>
                    {{-- Selected files preview --}}
                    <template x-if="fileObjects.length > 0">
                        <ul class="mt-2 space-y-1">
                            <template x-for="(f, i) in fileObjects" :key="i">
                                <li class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate text-gray-700 dark:text-gray-300" x-text="f.name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-2 shrink-0">
                                        <span class="text-gray-400 dark:text-gray-500" x-text="formatSize(f.size)"></span>
                                        <button type="button" @click.stop="removeFile(i)"
                                            class="text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors p-0.5 rounded hover:bg-red-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>
                    @error('pendingFiles') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('pendingFiles.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="uploadFiles" class="mt-2 flex items-center gap-2 text-xs text-indigo-600">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                        Uploading...
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Note <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span></label>
                    <textarea wire:model="uploadNote" rows="2" placeholder="Add a note about these files..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                        wire:loading.attr="disabled" wire:loading.class="opacity-60">
                        <span wire:loading.remove wire:target="uploadFiles">Upload</span>
                        <span wire:loading wire:target="uploadFiles">Uploading...</span>
                    </button>
                    <button type="button" @click="show = false; reset()"
                        class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
