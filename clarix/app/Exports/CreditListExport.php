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
            $this->filters['dateFrom']       ?? '',
            $this->filters['dateTo']         ?? '',
            $this->filters['filterUnit']     ?? '',
            $this->filters['filterPm']       ?? '',
            $this->filters['filterTaskType'] ?? '',
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
            $rows->push([$group['unit']?->name ?? 'Unknown Unit', '', '', '', '', '', '']);
            $rowNum++;

            $this->styleMap[$rowNum] = 'col_header';
            $rows->push(['Task Code', 'Task Title', 'Task Type', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits']);
            $rowNum++;

            foreach ($group['tasks'] as $task) {
                $rows->push([
                    $task->task_code,
                    $task->title,
                    $task->task_type ? ucfirst($task->task_type) : '—',
                    ucfirst($task->priority),
                    $task->assignedAdmin?->name ?? '—',
                    $task->updated_at->format('d M Y'),
                    number_format((float) $task->credit_amount, 2),
                ]);
                $rowNum++;
            }

            $this->styleMap[$rowNum] = 'subtotal';
            $rows->push(['', '', '', '', '', 'Unit Subtotal', number_format($group['credits'], 2)]);
            $rowNum++;

            $rows->push(['', '', '', '', '', '', '']);
            $rowNum++;

            $grandTotal += $group['credits'];
        }

        $this->styleMap[$rowNum] = 'grand_total';
        $rows->push(['', '', '', '', '', 'Grand Total', number_format($grandTotal, 2)]);

        return $rows;
    }

    private function buildUnifiedRows(Collection $tasks): Collection
    {
        $rows   = collect();
        $rowNum = 1;

        $this->styleMap[$rowNum] = 'col_header';
        $rows->push(['Task Code', 'Task Title', 'Task Type', 'Unit', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits']);
        $rowNum++;

        foreach ($tasks as $task) {
            $rows->push([
                $task->task_code,
                $task->title,
                $task->task_type ? ucfirst($task->task_type) : '—',
                $task->unit?->name ?? '—',
                ucfirst($task->priority),
                $task->assignedAdmin?->name ?? '—',
                $task->updated_at->format('d M Y'),
                number_format((float) $task->credit_amount, 2),
            ]);
            $rowNum++;
        }

        $this->styleMap[$rowNum] = 'grand_total';
        $rows->push(['', '', '', '', '', '', 'Grand Total', number_format((float) $tasks->sum('credit_amount'), 2)]);

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = $this->viewMode === 'grouped' ? 'G' : 'H';

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
            'C' => 15,
            'D' => 20,
            'E' => 15,
            'F' => 25,
            'G' => 18,
            'H' => 12,
        ];
    }

    public function title(): string
    {
        return 'Credit List';
    }
}
