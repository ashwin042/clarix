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
