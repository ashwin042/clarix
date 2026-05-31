# Credit List Excel Export — Design Spec
**Date:** 2026-05-31

## Overview

Add an Export button to the Credit List page (`/credits`) that downloads the currently filtered credit list as a formatted `.xlsx` file. The export respects all active filters (date range, unit, PM, view mode) and mirrors the on-screen layout: grouped exports use structured unit sections with subtotals; unified exports use a flat table.

---

## Architecture

The export is handled entirely outside the Livewire component via a standard Laravel GET route. The Livewire view renders an `<a>` tag whose `href` encodes current filter state as query parameters. Clicking it triggers a normal browser navigation that returns a file download — no Livewire streaming, no AJAX.

```
[CreditList Livewire component]
  → renders <a href="/credits/export?dateFrom=...&dateTo=...&filterUnit=...&filterPm=...&viewMode=...">
       ↓ (browser GET)
[CreditExportController]
  → calls CreditQueryBuilder (shared service)
  → constructs CreditListExport
       ↓
[maatwebsite/excel]
  → returns credit-list-export-{Y-m-d}.xlsx
```

---

## New Files

| File | Purpose |
|---|---|
| `app/Services/CreditQueryBuilder.php` | Extracted shared query logic (replaces duplicate logic in `CreditList` and the new controller) |
| `app/Exports/CreditListExport.php` | Export class: builds row collection, applies styles |
| `app/Http/Controllers/CreditExportController.php` | Single-action invokable controller |

### Modified Files

| File | Change |
|---|---|
| `app/Livewire/CreditList.php` | Replace inline `baseQuery()` body with a call to `CreditQueryBuilder` |
| `routes/web.php` | Add `GET /credits/export` route |
| `resources/views/livewire/credit-list.blade.php` | Add Export button in page header |
| `composer.json` | Add `maatwebsite/excel` |

---

## Package

**`maatwebsite/excel`** (`composer require maatwebsite/excel`). Publishes a config file via `php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"`.

---

## CreditQueryBuilder Service

Extracted from `CreditList::baseQuery()`. Accepts the authenticated user and filter values; returns a query builder instance with all role-based restrictions and active filters applied.

```php
class CreditQueryBuilder
{
    public function build(User $user, array $filters): Builder
    {
        // identical logic to current CreditList::baseQuery()
        // filters: dateFrom, dateTo, filterUnit, filterPm
    }
}
```

`CreditList::baseQuery()` becomes a one-liner delegating to this service.

---

## CreditListExport

Implements `FromCollection` and `WithStyles`. The `collection()` method builds the full row array (including unit header rows, data rows, subtotal rows, blank separators, and grand total) before handing it to the package.

**Grouped view sheet layout:**

```
[UNIT NAME]   ← merged across 6 cols, bold, indigo-50 background
Code | Task Title | Priority | Assigned Supervisor | Completed Date | Credits  ← bold header
...task rows...
Unit Subtotal   ← right-aligned bold, 5-col span + credits value
[blank row]
[next unit block]
...
Grand Total   ← bold, top border
```

**Unified view sheet layout:**

```
Code | Task Title | Unit | Priority | Assigned Supervisor | Completed Date | Credits  ← bold header
...task rows (ordered by date desc)...
Grand Total   ← bold, top border
```

Column widths are set via `WithColumnWidths` to prevent truncation (Task Title gets the widest allocation).

The export class tracks which row indices are unit headers, subtotal rows, and the grand total row, then applies styles in `styles(Worksheet $sheet)`.

---

## CreditExportController

Single-action invokable. Reads filter params from the request, enforces `credits.view` permission, delegates query to `CreditQueryBuilder`, constructs `CreditListExport`, and returns `Excel::download(...)`.

```php
public function __invoke(Request $request): BinaryFileResponse
{
    abort_unless(auth()->user()->hasPermission('credits.view'), 403);

    $export   = new CreditListExport($request->only([...filters...]), auth()->user());
    $filename = 'credit-list-export-' . now()->format('Y-m-d') . '.xlsx';

    return Excel::download($export, $filename);
}
```

No extra role middleware on the route — access control is handled inside the controller and query builder (writer sees only their tasks, PM sees only their unit).

---

## Route

Added inside the existing `auth` middleware group in `routes/web.php`, alongside the existing credits route:

```php
Route::get('/credits/export', CreditExportController::class)->name('credits.export');
```

---

## Export Button (UI)

Placed in the page header of `credit-list.blade.php` alongside the existing title. It is a plain `<a>` tag — no `wire:click` needed.

```blade
<a href="{{ route('credits.export', array_filter([
    'dateFrom'   => $dateFrom,
    'dateTo'     => $dateTo,
    'filterUnit' => $filterUnit,
    'filterPm'   => $filterPm,
    'viewMode'   => $viewMode,
])) }}"
   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
    Export Excel
</a>
```

`array_filter` removes empty strings so the URL is clean when no filters are active. Because Livewire re-renders on every filter change, the `href` is always up-to-date.

---

## Access Control Summary

| Role | Export scope |
|---|---|
| Admin | All completed tasks; optional unit/PM filters applied |
| PM | Tasks in their own unit only |
| Writer | Tasks assigned to them only |

---

## Excel Columns

**Grouped:** Code · Task Title · Priority · Assigned Supervisor · Completed Date · Credits

**Unified:** Code · Task Title · Unit · Priority · Assigned Supervisor · Completed Date · Credits

"Completed Date" maps to `updated_at` (the field used on-screen), formatted `d M Y`.

---

## Error Handling

- `abort(403)` if user lacks `credits.view` permission.
- If no tasks match the filters, the export returns a file with headers only (no data rows), plus the grand total row showing 0.00.
