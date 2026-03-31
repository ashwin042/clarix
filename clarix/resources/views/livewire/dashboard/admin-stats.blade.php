<div class="space-y-6">
    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Tasks</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['totalTasks'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">All time</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-6 9l2 2 4-4"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Completion Rate</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completionRate'] }}%</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $stats['completedTasks'] }} of {{ $stats['totalTasks'] }} tasks</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Active Units</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['totalUnits'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $stats['totalUsers'] }} total users</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Credits Earned</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($stats['totalCredits']) }}</p>
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
                <canvas id="statusDonut"></canvas>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-4">Tasks Created (Last 30 Days)</h3>
            <div class="h-52">
                <canvas id="trendLine"></canvas>
            </div>
        </div>

    </div>

    {{-- Bar chart --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-4">Tasks per Unit</h3>
        <div class="h-52">
            <canvas id="unitBar"></canvas>
        </div>
    </div>

    {{-- Bottom row: recent tasks + activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Recent tasks --}}
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
                        <p class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500 font-mono">{{ $task->task_code }} · {{ $task->unit?->name ?? '—' }}</p>
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

    {{-- Activity feed --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Activity Feed</h3>
        </div>
        <div class="p-5 space-y-4">
            @foreach($recentAssignments as $assignment)
            <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-700 dark:text-gray-300">Writer assigned to <span class="font-medium text-gray-900 dark:text-white">{{ Str::limit($assignment->task?->title ?? '—', 32) }}</span></p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $assignment->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @endforeach

            @foreach($recentFiles as $file)
            <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-full bg-teal-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-700 dark:text-gray-300">File uploaded to <span class="font-medium text-gray-900 dark:text-white">{{ Str::limit($file->task?->title ?? '—', 32) }}</span></p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $file->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @endforeach

            @if($recentAssignments->isEmpty() && $recentFiles->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">No recent activity.</p>
            @endif
        </div>
    </div>

    </div>{{-- /bottom row --}}

</div>

{{-- Chart.js initialisation (runs once after Livewire hydration) --}}
<script>
(function() {
    var isDark = document.documentElement.classList.contains('dark');
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : '#f3f4f6';
    var tickColor = isDark ? '#9ca3af' : '#6b7280';
    var legendColor = isDark ? '#d1d5db' : undefined;

    function initCharts() {
        var donutCtx = document.getElementById('statusDonut');
        if (donutCtx && !donutCtx._chartInited) {
            donutCtx._chartInited = true;
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($donutData['labels']),
                    datasets: [{
                        data: @json($donutData['data']),
                        backgroundColor: ['#e2e8f0','#bfdbfe','#c7d2fe','#99f6e4','#bbf7d0'],
                        borderColor:     ['#cbd5e1','#93c5fd','#a5b4fc','#5eead4','#86efac'],
                        borderWidth: 1.5,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 }, color: legendColor } } }
                }
            });
        }

        var lineCtx = document.getElementById('trendLine');
        if (lineCtx && !lineCtx._chartInited) {
            lineCtx._chartInited = true;
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: @json($trendLabels),
                    datasets: [{
                        label: 'Tasks',
                        data: @json($trendValues),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 8, color: '#9ca3af' } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#9ca3af' }, grid: { color: gridColor } }
                    }
                }
            });
        }

        var barCtx = document.getElementById('unitBar');
        if (barCtx && !barCtx._chartInited) {
            barCtx._chartInited = true;
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: @json($barData['labels']),
                    datasets: [{
                        label: 'Tasks',
                        data: @json($barData['data']),
                        backgroundColor: 'rgba(99,102,241,0.15)',
                        borderColor: '#6366f1',
                        borderWidth: 1.5,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: tickColor } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 }, color: '#9ca3af' }, grid: { color: gridColor } }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }

    document.addEventListener('livewire:navigated', initCharts);
})();
</script>

