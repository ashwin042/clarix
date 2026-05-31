# Credit List Excel Export — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an Export Excel button to the Credit List page that downloads all currently-filtered completed tasks as a styled `.xlsx` file, respecting role-based access and grouped/unified view mode.

**Architecture:** A shared `CreditQueryBuilder` service encapsulates the filter + role logic used by both the existing Livewire component and a new `CreditExportController`. The controller accepts filter params as a query string, builds a `CreditListExport` object, and streams the file via `maatwebsite/excel`. The Livewire view renders a plain `<a>` tag whose `href` encodes current filter state — no Livewire actions needed.

**Tech Stack:** Laravel 12 · Livewire 3 · maatwebsite/excel · PhpSpreadsheet (bundled with excel package)

---

## File Map

| Status | Path | Purpose |
|--------|------|---------|
| Create | `app/Services/CreditQueryBuilder.php` | Shared query logic (role + filter) |
| Create | `app/Exports/CreditListExport.php` | Builds xlsx row collection + applies styles |
| Create | `app/Http/Controllers/CreditExportController.php` | Single-action controller, returns file download |
| Create | `tests/Unit/CreditQueryBuilderTest.php` | Unit tests for query service |
| Create | `tests/Unit/CreditListExportTest.php` | Unit tests for export row builder |
| Create | `tests/Feature/CreditExportControllerTest.php` | HTTP feature tests for export endpoint |
| Modify | `app/Livewire/CreditList.php` | Delegate `baseQuery()` to `CreditQueryBuilder` |
| Modify | `routes/web.php` | Add `GET /credits/export` route |
| Modify | `resources/views/livewire/credit-list.blade.php` | Add Export button to page header |

---

## Task 1: Install maatwebsite/excel

**Files:**
- Modify: `composer.json` (via composer)
- Create: `config/excel.php` (via artisan vendor:publish)

- [ ] **Step 1: Run composer require**

```bash
composer require maatwebsite/excel
```

Expected output ends with: `Package manifest generated successfully.`

If this fails with a Laravel version constraint error, run:
```bash
composer require maatwebsite/excel --with-all-dependencies
```

- [ ] **Step 2: Publish the package config**

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

Expected: `Copied File [...] To [/config/excel.php]`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/excel.php
git commit -m "chore: install maatwebsite/excel for credit list export"
```

---

## Task 2: CreditQueryBuilder service

**Files:**
- Create: `app/Services/CreditQueryBuilder.php`
- Create: `tests/Unit/CreditQueryBuilderTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/CreditQueryBuilderTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CreditQueryBuilder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreditQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeTask(array $overrides = []): Task
    {
        $unit    = Unit::create(['name' => 'Unit ' . uniqid()]);
        $creator = $this->makeAdmin();

        return Task::create(array_merge([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $creator->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 5.00,
        ], $overrides));
    }

    public function test_admin_sees_all_completed_tasks(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask();
        $this->makeTask(['status' => 'pending']); // excluded

        $results = (new CreditQueryBuilder())->build($admin)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_admin_filter_by_unit(): void
    {
        $admin = $this->makeAdmin();
        $unit1 = Unit::create(['name' => 'Unit A']);
        $unit2 = Unit::create(['name' => 'Unit B']);
        $task1 = $this->makeTask(['unit_id' => $unit1->id, 'created_by' => $admin->id]);
        $this->makeTask(['unit_id' => $unit2->id, 'created_by' => $admin->id]);

        $results = (new CreditQueryBuilder())
            ->build($admin, filterUnit: (string) $unit1->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_admin_filter_by_pm(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Unit A']);
        $pm1   = $this->makePm($unit);
        $pm2   = $this->makePm($unit);
        $task1 = $this->makeTask(['created_by' => $pm1->id, 'unit_id' => $unit->id]);
        $this->makeTask(['created_by' => $pm2->id, 'unit_id' => $unit->id]);

        $results = (new CreditQueryBuilder())
            ->build($admin, filterPm: (string) $pm1->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_admin_filter_by_date_from(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask();
        $old   = $this->makeTask();
        DB::table('tasks')->where('id', $old->id)->update(['updated_at' => '2024-01-15 00:00:00']);

        $results = (new CreditQueryBuilder())
            ->build($admin, dateFrom: '2026-01-01')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_admin_filter_by_date_to(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask();
        DB::table('tasks')->where('id', $task->id)->update(['updated_at' => '2024-06-01 00:00:00']);
        $this->makeTask(); // recent, excluded

        $results = (new CreditQueryBuilder())
            ->build($admin, dateTo: '2024-12-31')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_pm_sees_only_their_unit(): void
    {
        $unit1 = Unit::create(['name' => 'PM Unit']);
        $unit2 = Unit::create(['name' => 'Other Unit']);
        $pm    = $this->makePm($unit1);
        $task1 = $this->makeTask(['unit_id' => $unit1->id]);
        $this->makeTask(['unit_id' => $unit2->id]);

        $results = (new CreditQueryBuilder())->build($pm)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_writer_sees_only_assigned_tasks(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();
        $task1  = $this->makeTask();
        $this->makeTask(); // not assigned

        TaskAssignment::create([
            'task_id'     => $task1->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'completed',
        ]);

        $results = (new CreditQueryBuilder())->build($writer)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }
}
```

- [ ] **Step 2: Run tests — expect failure (class not found)**

```bash
php artisan test tests/Unit/CreditQueryBuilderTest.php
```

Expected: FAIL — `Class "App\Services\CreditQueryBuilder" not found`

- [ ] **Step 3: Create CreditQueryBuilder**

Create `app/Services/CreditQueryBuilder.php`:

```php
<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CreditQueryBuilder
{
    public function build(
        User $user,
        string $dateFrom = '',
        string $dateTo = '',
        string $filterUnit = '',
        string $filterPm = ''
    ): Builder {
        $query = Task::with(['unit', 'creator', 'assignedAdmin'])
            ->where('status', 'completed');

        if ($user->isWriter()) {
            $query->whereHas('assignments', fn ($q) => $q->where('writer_id', $user->id));
        }

        if ($user->isPm()) {
            $query->where('unit_id', $user->unit_id);
        }

        if ($user->isAdmin() && $filterUnit) {
            $query->where('unit_id', $filterUnit);
        }

        if ($user->isAdmin() && $filterPm) {
            $query->where('created_by', $filterPm);
        }

        if ($dateFrom) {
            $query->whereDate('updated_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('updated_at', '<=', $dateTo);
        }

        return $query;
    }
}
```

- [ ] **Step 4: Run tests — expect all 7 to pass**

```bash
php artisan test tests/Unit/CreditQueryBuilderTest.php
```

Expected: 7 tests, 7 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CreditQueryBuilder.php tests/Unit/CreditQueryBuilderTest.php
git commit -m "feat: add CreditQueryBuilder service with tests"
```

---

## Task 3: Refactor CreditList to use CreditQueryBuilder

**Files:**
- Modify: `app/Livewire/CreditList.php`

- [ ] **Step 1: Update imports in CreditList.php**

In `app/Livewire/CreditList.php`, replace:
```php
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
```
With:
```php
use App\Models\Unit;
use App\Models\User;
use App\Services\CreditQueryBuilder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
```

(`Task` is removed — it was only used inside `baseQuery()`.)

- [ ] **Step 2: Replace the baseQuery() method body**

In `app/Livewire/CreditList.php`, replace the entire `baseQuery()` method:
```php
private function baseQuery()
{
    $user  = auth()->user();
    $query = Task::with(['unit', 'creator', 'assignedAdmin'])
        ->where('status', 'completed');

    // Writer can only see tasks they are assigned to
    if ($user->isWriter()) {
        $query->whereHas('assignments', fn ($q) => $q->where('writer_id', $user->id));
    }

    // PM can only see their own unit
    if ($user->isPm()) {
        $query->where('unit_id', $user->unit_id);
    }

    // Admin filters
    if ($user->isAdmin() && $this->filterUnit) {
        $query->where('unit_id', $this->filterUnit);
    }
    if ($user->isAdmin() && $this->filterPm) {
        $query->where('created_by', $this->filterPm);
    }

    if ($this->dateFrom) {
        $query->whereDate('updated_at', '>=', $this->dateFrom);
    }
    if ($this->dateTo) {
        $query->whereDate('updated_at', '<=', $this->dateTo);
    }

    return $query;
}
```
With:
```php
private function baseQuery()
{
    return (new CreditQueryBuilder())->build(
        auth()->user(),
        $this->dateFrom,
        $this->dateTo,
        $this->filterUnit,
        $this->filterPm,
    );
}
```

- [ ] **Step 3: Run all existing tests**

```bash
php artisan test
```

Expected: All tests pass (no regressions).

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/CreditList.php
git commit -m "refactor: delegate baseQuery to CreditQueryBuilder in CreditList"
```

---

## Task 4: CreditListExport class

**Files:**
- Create: `app/Exports/CreditListExport.php`
- Create: `tests/Unit/CreditListExportTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/CreditListExportTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Exports\CreditListExport;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeCompletedTask(Unit $unit, User $creator, float $credits = 5.00): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $creator->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => $credits,
        ]);
    }

    public function test_unified_header_row_has_seven_columns(): void
    {
        $admin  = $this->makeAdmin();
        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        $this->assertEquals(
            ['Code', 'Task Title', 'Unit', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits'],
            $rows[0]
        );
    }

    public function test_unified_export_includes_task_row_and_grand_total(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Alpha Unit']);
        $task  = $this->makeCompletedTask($unit, $admin, 3.50);

        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        // Row 1: task data
        $this->assertEquals($task->task_code, $rows[1][0]);
        $this->assertEquals('Alpha Unit', $rows[1][2]);
        $this->assertEquals('3.50', $rows[1][6]);

        // Last row: grand total
        $lastRow = end($rows);
        $this->assertEquals('Grand Total', $lastRow[5]);
        $this->assertEquals('3.50', $lastRow[6]);
    }

    public function test_grouped_export_has_unit_header_then_col_header(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Beta Unit']);
        $this->makeCompletedTask($unit, $admin, 4.00);

        $export = new CreditListExport(['viewMode' => 'grouped'], $admin);
        $rows   = $export->collection()->toArray();

        // Row 0: unit header (merged cell content)
        $this->assertEquals('Beta Unit', $rows[0][0]);

        // Row 1: column headers
        $this->assertEquals(
            ['Code', 'Task Title', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits'],
            $rows[1]
        );
    }

    public function test_grouped_export_subtotal_and_grand_total_are_correct(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Gamma Unit']);
        $this->makeCompletedTask($unit, $admin, 4.00);
        $this->makeCompletedTask($unit, $admin, 6.00);

        $export = new CreditListExport(['viewMode' => 'grouped'], $admin);
        $rows   = $export->collection()->toArray();

        $subtotalRow = collect($rows)->first(fn ($r) => $r[4] === 'Unit Subtotal');
        $this->assertNotNull($subtotalRow);
        $this->assertEquals('10.00', $subtotalRow[5]);

        $lastRow = end($rows);
        $this->assertEquals('Grand Total', $lastRow[4]);
        $this->assertEquals('10.00', $lastRow[5]);
    }

    public function test_writer_always_gets_unified_format_regardless_of_viewmode(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();
        $unit   = Unit::create(['name' => 'Writer Unit']);
        $task   = $this->makeCompletedTask($unit, $admin);

        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'completed',
        ]);

        // Even with viewMode=grouped, writers get 7-column unified format
        $export = new CreditListExport(['viewMode' => 'grouped'], $writer);
        $rows   = $export->collection()->toArray();

        $this->assertCount(7, $rows[0]);
        $this->assertEquals('Unit', $rows[0][2]);
    }

    public function test_empty_result_returns_header_and_zero_grand_total(): void
    {
        $admin  = $this->makeAdmin();
        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        $this->assertCount(2, $rows);
        $this->assertEquals('0.00', end($rows)[6]);
    }
}
```

- [ ] **Step 2: Run tests — expect failure (class not found)**

```bash
php artisan test tests/Unit/CreditListExportTest.php
```

Expected: FAIL — `Class "App\Exports\CreditListExport" not found`

- [ ] **Step 3: Create CreditListExport**

Create `app/Exports/CreditListExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\User;
use App\Services\CreditQueryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CreditListExport implements FromCollection, WithColumnWidths, WithStyles, WithTitle
{
    private string $viewMode;
    /** @var array<int, string> */
    private array $styleMap = [];

    public function __construct(
        private readonly array $filters,
        private readonly User $user
    ) {
        $this->viewMode = $user->isWriter()
            ? 'unified'
            : ($filters['viewMode'] ?? 'grouped');
    }

    public function collection(): Collection
    {
        $this->styleMap = [];

        $query = (new CreditQueryBuilder())->build(
            $this->user,
            $this->filters['dateFrom']   ?? '',
            $this->filters['dateTo']     ?? '',
            $this->filters['filterUnit'] ?? '',
            $this->filters['filterPm']   ?? '',
        );

        if ($this->viewMode === 'grouped') {
            return $this->buildGroupedRows(
                $query->orderBy('unit_id')->orderBy('updated_at', 'desc')->get()
            );
        }

        return $this->buildUnifiedRows(
            $query->orderBy('updated_at', 'desc')->get()
        );
    }

    private function buildGroupedRows(Collection $tasks): Collection
    {
        $rows       = collect();
        $rowNum     = 1;
        $grandTotal = 0.0;

        $grouped = $tasks
            ->groupBy('unit_id')
            ->map(fn ($unitTasks) => [
                'unit'    => $unitTasks->first()->unit,
                'tasks'   => $unitTasks,
                'credits' => (float) $unitTasks->sum('credit_amount'),
            ])
            ->sortBy(fn ($g) => $g['unit']?->name ?? '')
            ->values();

        foreach ($grouped as $group) {
            $this->styleMap[$rowNum] = 'unit_header';
            $rows->push([$group['unit']?->name ?? 'Unknown Unit', '', '', '', '', '']);
            $rowNum++;

            $this->styleMap[$rowNum] = 'col_header';
            $rows->push(['Code', 'Task Title', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits']);
            $rowNum++;

            foreach ($group['tasks'] as $task) {
                $rows->push([
                    $task->task_code,
                    $task->title,
                    ucfirst($task->priority),
                    $task->assignedAdmin?->name ?? '—',
                    $task->updated_at->format('d M Y'),
                    number_format((float) $task->credit_amount, 2),
                ]);
                $rowNum++;
            }

            $this->styleMap[$rowNum] = 'subtotal';
            $rows->push(['', '', '', '', 'Unit Subtotal', number_format($group['credits'], 2)]);
            $rowNum++;

            $rows->push(['', '', '', '', '', '']);
            $rowNum++;

            $grandTotal += $group['credits'];
        }

        $this->styleMap[$rowNum] = 'grand_total';
        $rows->push(['', '', '', '', 'Grand Total', number_format($grandTotal, 2)]);

        return $rows;
    }

    private function buildUnifiedRows(Collection $tasks): Collection
    {
        $rows   = collect();
        $rowNum = 1;

        $this->styleMap[$rowNum] = 'col_header';
        $rows->push(['Code', 'Task Title', 'Unit', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits']);
        $rowNum++;

        foreach ($tasks as $task) {
            $rows->push([
                $task->task_code,
                $task->title,
                $task->unit?->name ?? '—',
                ucfirst($task->priority),
                $task->assignedAdmin?->name ?? '—',
                $task->updated_at->format('d M Y'),
                number_format((float) $task->credit_amount, 2),
            ]);
            $rowNum++;
        }

        $this->styleMap[$rowNum] = 'grand_total';
        $rows->push(['', '', '', '', '', 'Grand Total', number_format((float) $tasks->sum('credit_amount'), 2)]);

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = $this->viewMode === 'grouped' ? 'F' : 'G';

        foreach ($this->styleMap as $rowNum => $type) {
            $range = "A{$rowNum}:{$lastCol}{$rowNum}";

            if ($type === 'unit_header') {
                $sheet->mergeCells($range);
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFEEF2FF'],
                    ],
                ]);
            } elseif ($type === 'col_header') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF3F4F6'],
                    ],
                ]);
            } elseif ($type === 'subtotal') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true],
                ]);
            } elseif ($type === 'grand_total') {
                $sheet->getStyle($range)->applyFromArray([
                    'font'    => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 45,
            'C' => 20,
            'D' => 15,
            'E' => 25,
            'F' => 18,
            'G' => 12,
        ];
    }

    public function title(): string
    {
        return 'Credit List';
    }
}
```

- [ ] **Step 4: Run tests — expect all 6 to pass**

```bash
php artisan test tests/Unit/CreditListExportTest.php
```

Expected: 6 tests, 6 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/CreditListExport.php tests/Unit/CreditListExportTest.php
git commit -m "feat: add CreditListExport with grouped and unified xlsx layouts"
```

---

## Task 5: CreditExportController + route

**Files:**
- Create: `app/Http/Controllers/CreditExportController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/CreditExportControllerTest.php`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/CreditExportControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class CreditExportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('credits.export'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_download_export(): void
    {
        Excel::fake();

        $this->actingAs($this->makeAdmin());

        $this->get(route('credits.export'))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function test_pm_can_download_export(): void
    {
        Excel::fake();

        $unit = Unit::create(['name' => 'PM Unit']);
        $this->actingAs($this->makePm($unit));

        $this->get(route('credits.export'))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function test_writer_without_credits_view_permission_is_forbidden(): void
    {
        // By default in PermissionSeeder, writers have credits.view = false
        $this->actingAs($this->makeWriter());

        $this->get(route('credits.export'))->assertForbidden();
    }

    public function test_export_accepts_all_filter_query_params(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Filter Unit']);
        $this->actingAs($admin);

        $this->get(route('credits.export', [
            'dateFrom'   => '2026-01-01',
            'dateTo'     => '2026-12-31',
            'filterUnit' => $unit->id,
            'viewMode'   => 'unified',
        ]))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run tests — expect failure (route not found)**

```bash
php artisan test tests/Feature/CreditExportControllerTest.php
```

Expected: FAIL — route `credits.export` not found.

- [ ] **Step 3: Create CreditExportController**

Create `app/Http/Controllers/CreditExportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exports\CreditListExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CreditExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->user()->hasPermission('credits.view'), 403);

        $filters  = $request->only(['dateFrom', 'dateTo', 'filterUnit', 'filterPm', 'viewMode']);
        $export   = new CreditListExport($filters, auth()->user());
        $filename = 'credit-list-export-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download($export, $filename);
    }
}
```

- [ ] **Step 4: Add route and import to routes/web.php**

In `routes/web.php`, add the import alongside the other controller imports at the top:
```php
use App\Http\Controllers\CreditExportController;
```

Add the route immediately after `Route::get('/credits', CreditList::class)->name('credits.index');`:
```php
Route::get('/credits/export', CreditExportController::class)->name('credits.export');
```

- [ ] **Step 5: Run feature tests — expect all 5 to pass**

```bash
php artisan test tests/Feature/CreditExportControllerTest.php
```

Expected: 5 tests, 5 passed.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CreditExportController.php routes/web.php tests/Feature/CreditExportControllerTest.php
git commit -m "feat: add CreditExportController and GET /credits/export route"
```

---

## Task 6: Export button in Blade view

**Files:**
- Modify: `resources/views/livewire/credit-list.blade.php`

- [ ] **Step 1: Replace the page header block**

In `resources/views/livewire/credit-list.blade.php`, replace lines 3–9:

```blade
    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Credit List</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track credits earned from completed tasks.</p>
        </div>
    </div>
```

With:

```blade
    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Credit List</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track credits earned from completed tasks.</p>
        </div>
        <a href="{{ route('credits.export', array_filter([
                'dateFrom'   => $dateFrom,
                'dateTo'     => $dateTo,
                'filterUnit' => $filterUnit,
                'filterPm'   => $filterPm,
                'viewMode'   => $viewMode,
            ])) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Excel
        </a>
    </div>
```

Note: `array_filter` removes empty strings, keeping the URL clean when no filters are set. `viewMode` defaults to `'grouped'` so it will always be present. Because Livewire re-renders on every filter change, the `href` is always current.

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/credit-list.blade.php
git commit -m "feat: add Export Excel button to credit list page header"
```
