<x-app-layout pageTitle="{{ $task->title }}">
    <div class="p-6">
        {{-- Header --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <a href="{{ route('tasks.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to Tasks
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $task->title }}</h1>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-sm text-gray-500 font-mono">{{ $task->task_code }}</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-sm text-gray-500">{{ $task->unit->name }}</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-sm text-gray-500">Due {{ $task->deadline->format('M d, Y') }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $task)
                    <a href="{{ route('tasks.edit', $task) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                @endcan
                @can('delete', $task)
                    <form action="{{ route('tasks.destroy', $task) }}" method="POST"
                          onsubmit="return confirm('Delete this task?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-3 gap-6">
            {{-- Main Content --}}
            <div class="col-span-2 space-y-6">

                {{-- Details Card --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Task Details</h2>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-gray-500 mb-1">Priority</dt>
                            @php
                                $priorityClass = match($task->priority) {
                                    'high'   => 'bg-red-100 text-red-700',
                                    'medium' => 'bg-yellow-100 text-yellow-700',
                                    'low'    => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $priorityClass }}">
                                {{ ucfirst($task->priority) }}
                            </span>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-1">Status</dt>
                            @php
                                $statusClass = match($task->status) {
                                    'pending'     => 'bg-gray-100 text-gray-600',
                                    'in_progress' => 'bg-blue-100 text-blue-700',
                                    'submitted'   => 'bg-purple-100 text-purple-700',
                                    'verified'    => 'bg-teal-100 text-teal-700',
                                    'completed'   => 'bg-green-100 text-green-700',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                {{ str_replace('_', ' ', ucfirst($task->status)) }}
                            </span>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-1">Created By</dt>
                            <dd class="text-sm text-gray-800">{{ $task->creator->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-1">Deadline</dt>
                            <dd class="text-sm text-gray-800 {{ $task->deadline->isPast() ? 'text-red-600 font-medium' : '' }}">
                                {{ $task->deadline->format('M d, Y') }}
                            </dd>
                        </div>
                    </dl>
                    @if($task->description)
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <dt class="text-xs text-gray-500 mb-2">Description</dt>
                            <dd class="text-sm text-gray-700 leading-relaxed">{{ $task->description }}</dd>
                        </div>
                    @endif
                </div>

                {{-- Writers / Assignments --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-900">Assigned Writers</h2>
                    </div>

                    @if($task->assignments->count())
                        <div class="space-y-2 mb-4">
                            @foreach($task->assignments as $assignment)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                            <span class="text-xs font-medium text-indigo-700">
                                                {{ strtoupper(substr($assignment->writer->name, 0, 2)) }}
                                            </span>
                                        </div>
                                        {{-- Writers only see their own name; PM/Admin see all --}}
                                        @if(auth()->user()->isWriter())
                                            <span class="text-sm text-gray-700">You</span>
                                        @else
                                            <span class="text-sm text-gray-700">{{ $assignment->writer->name }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @php
                                            $aStatusClass = match($assignment->status) {
                                                'pending'           => 'bg-gray-100 text-gray-600',
                                                'in_progress'       => 'bg-blue-100 text-blue-700',
                                                'ready_for_review'  => 'bg-teal-100 text-teal-700',
                                            };
                                        @endphp
                                        <span class="text-xs px-2 py-0.5 rounded font-medium {{ $aStatusClass }}">
                                            {{ str_replace('_', ' ', ucfirst($assignment->status)) }}
                                        </span>

                                        {{-- Writer updates their own status --}}
                                        @if(auth()->id() === $assignment->writer_id)
                                            <form action="{{ route('tasks.assignments.status', [$task, $assignment]) }}" method="POST" class="flex items-center gap-1">
                                                @csrf @method('PATCH')
                                                <select name="status" onchange="this.form.submit()"
                                                        class="text-xs border border-gray-300 rounded px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                                    <option value="pending" {{ $assignment->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                    <option value="in_progress" {{ $assignment->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                                    <option value="ready_for_review" {{ $assignment->status === 'ready_for_review' ? 'selected' : '' }}>Ready for Review</option>
                                                </select>
                                            </form>
                                        @endif

                                        @can('assign', $task)
                                            <form action="{{ route('tasks.assignments.destroy', [$task, $assignment]) }}" method="POST"
                                                  onsubmit="return confirm('Remove this assignment?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mb-4">No writers assigned yet.</p>
                    @endif

                    {{-- Assign Writer Form (admin only) --}}
                    @can('assign', $task)
                        @php
                            $assignedIds = $task->assignments->pluck('writer_id')->toArray();
                            $availableWriters = \App\Models\User::where('role', 'writer')
                                ->whereNotIn('id', $assignedIds)->orderBy('name')->get();
                        @endphp
                        @if($availableWriters->count())
                            <form action="{{ route('tasks.assignments.store', $task) }}" method="POST" class="border-t border-gray-100 pt-4">
                                @csrf
                                <div class="flex gap-2">
                                    <select name="writer_ids[]" multiple
                                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @foreach($availableWriters as $writer)
                                            <option value="{{ $writer->id }}">{{ $writer->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                        Assign
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple writers</p>
                            </form>
                        @endif
                    @endcan
                </div>

                {{-- Files --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Files</h2>

                    @if($task->files->count())
                        <div class="space-y-2 mb-4">
                            @foreach($task->files as $file)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <div class="min-w-0">
                                            <p class="text-sm text-gray-800 truncate">{{ $file->original_name }}</p>
                                            {{-- Uploader identity hidden from writers --}}
                                            @if(!auth()->user()->isWriter())
                                                <p class="text-xs text-gray-400">by {{ $file->uploader->name }} · {{ $file->file_size_formatted }}</p>
                                            @else
                                                <p class="text-xs text-gray-400">{{ $file->file_size_formatted }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Download</a>
                                        @can('uploadFiles', $task)
                                            <form action="{{ route('tasks.files.destroy', [$task, $file]) }}" method="POST"
                                                  onsubmit="return confirm('Delete this file?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mb-4">No files uploaded yet.</p>
                    @endif

                    @can('uploadFiles', $task)
                        <form action="{{ route('tasks.files.store', $task) }}" method="POST"
                              enctype="multipart/form-data" class="border-t border-gray-100 pt-4">
                            @csrf
                            <div class="flex gap-2">
                                <input type="file" name="files[]" multiple
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                    Upload
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Max 10MB per file</p>
                        </form>
                    @endcan
                </div>
            </div>

            {{-- Sidebar: Notes --}}
            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Notes</h2>

                    @if($task->notes->count())
                        <div class="space-y-3 mb-4">
                            @foreach($task->notes as $note)
                                <div class="p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                                    <p class="text-sm text-gray-700">{{ $note->note }}</p>
                                    <p class="text-xs text-gray-400 mt-2">
                                        {{ $note->author->name }} · {{ $note->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mb-4">No notes yet.</p>
                    @endif

                    <form action="{{ route('tasks.notes.store', $task) }}" method="POST" class="border-t border-gray-100 pt-4">
                        @csrf
                        <textarea name="note" rows="3" placeholder="Add a note..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <button type="submit"
                                class="mt-2 w-full px-3 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                            Add Note
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
