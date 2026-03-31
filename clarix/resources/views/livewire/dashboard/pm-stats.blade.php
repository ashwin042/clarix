<div class="space-y-6">

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Overall Tasks</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">All time</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Completed</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completed'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $stats['completionRate'] }}% completion rate</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">In Progress</p>
                <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['inProgress'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                    @if($totalWriters > 0)
                        {{ $readyWriters }}/{{ $totalWriters }} writers ready
                    @else
                        No writers assigned
                    @endif
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Overall Credits</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($stats['credits']) }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">From completed tasks</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
        </div>

    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-4">Task Status</h3>
            <div class="relative h-52">
                <canvas id="pmStatusDonut"></canvas>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-4">Tasks Created (Last 30 Days)</h3>
            <div class="h-52">
                <canvas id="pmTrendLine"></canvas>
            </div>
        </div>

    </div>

    {{-- Bottom row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Recent Tasks --}}
        @if($recentTasks->count())
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent Tasks</h3>
                <a href="{{ route('tasks.index') }}" class="text-xs text-indigo-600 font-medium hover:text-indigo-800">View all →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($recentTasks as $task)
                @php
                    $sc = match($task->status) {
                        'pending'     => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500',
                        'in_progress' => 'bg-blue-100 text-blue-700',
                        'submitted'   => 'bg-purple-100 text-purple-700',
                        'verified'    => 'bg-teal-100 text-teal-700',
                        'completed'   => 'bg-green-100 text-green-700',
                        default       => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500',
                    };
                @endphp
                <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                        </div>
                        <div class="min-w-0">
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:text-indigo-600 truncate block transition-colors">{{ $task->title }}</a>
                            <p class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500 font-mono">{{ $task->task_code }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 ml-3 flex-shrink-0">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $task->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Recent File Activity --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent File Uploads</h3>
            </div>
            <div class="p-5 space-y-3">
                @forelse($recentFiles as $file)
                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $file->original_name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">{{ $file->task?->task_code ?? '—' }} · {{ $file->file_size_formatted }} · {{ $file->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                    <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No file uploads yet.</p>
                @endforelse
            </div>
        </div>

    </div>

</div>

<script>
(function () {
    var isDark = document.documentElement.classList.contains('dark');
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : '#f3f4f6';
    var legendColor = isDark ? '#d1d5db' : undefined;

    function initPmCharts() {
        var donutEl = document.getElementById('pmStatusDonut');
        if (donutEl && !donutEl._ci) {
            donutEl._ci = true;
            new Chart(donutEl, {
                type: 'doughnut',
                data: {
                    labels: @json($donutData['labels']),
                    datasets: [{
                        data: @json($donutData['data']),
                        backgroundColor: ['#e2e8f0','#bfdbfe','#c7d2fe','#99f6e4','#bbf7d0'],
                        borderColor:     ['#cbd5e1','#93c5fd','#a5b4fc','#5eead4','#86efac'],
                        borderWidth: 1.5, hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '65%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 }, color: legendColor } } }
                }
            });
        }

        var lineEl = document.getElementById('pmTrendLine');
        if (lineEl && !lineEl._ci) {
            lineEl._ci = true;
            new Chart(lineEl, {
                type: 'line',
                data: {
                    labels: @json($trendLabels),
                    datasets: [{
                        label: 'Tasks', data: @json($trendValues),
                        borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)',
                        borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: true, tension: 0.4,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 8, color: '#9ca3af' } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#9ca3af' }, grid: { color: gridColor } }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPmCharts);
    } else {
        initPmCharts();
    }
    document.addEventListener('livewire:navigated', initPmCharts);
})();
</script>

