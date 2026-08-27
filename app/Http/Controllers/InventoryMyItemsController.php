<?php

namespace App\Http\Controllers;

use App\Exports\InventoryAssetsExport;
use App\Exports\InventoryIssuedStockExport;
use App\Models\InventoryAsset;
use App\Models\InventoryStockIssue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class InventoryMyItemsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeOwnItems();

        $tab = $request->input('tab', 'assets');
        if (! in_array($tab, ['assets', 'stock'], true)) {
            $tab = 'assets';
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $assets = $this->custodyAssetsQuery($user)
            ->orderBy('asset_code')
            ->paginate(20, ['*'], 'assets_page')
            ->withQueryString();

        $issues = $this->custodyIssuesQuery($user)
            ->orderByDesc('issued_at')
            ->paginate(20, ['*'], 'stock_page')
            ->withQueryString();

        return view('inventory.my-items.index', [
            'tab' => $tab,
            'assets' => $assets,
            'issues' => $issues,
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
        ]);
    }

    public function export(Request $request, string $type, string $format)
    {
        $this->authorizeOwnItems();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $filename = 'my-inventory-'.$type.'-'.now()->format('Y-m-d');

        if ($type === 'stock') {
            $issues = $this->custodyIssuesQuery($user)->orderByDesc('issued_at')->get();

            if ($format === 'pdf') {
                return Pdf::loadView('inventory.exports.my-stock-pdf', [
                    'issues' => $issues,
                    'generatedAt' => now(),
                    'user' => $user,
                ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
            }

            return Excel::download(new InventoryIssuedStockExport($issues), $filename.'.xlsx');
        }

        $assets = $this->custodyAssetsQuery($user)->orderBy('asset_code')->get();

        if ($format === 'pdf') {
            return Pdf::loadView('inventory.exports.assets-pdf', [
                'assets' => $assets,
                'statusOptions' => InventoryAsset::statusOptions(),
                'conditionOptions' => InventoryAsset::conditionOptions(),
                'generatedAt' => now(),
                'title' => 'My assigned assets',
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return Excel::download(new InventoryAssetsExport($assets), $filename.'.xlsx');
    }

    private function authorizeOwnItems(): void
    {
        abort_unless(Auth::user()?->can('view own inventory items'), 403);
    }

    private function custodyAssetsQuery($user)
    {
        $scopeIds = $user->inventoryCustodyScopeIds();

        return InventoryAsset::query()
            ->with(['category', 'location.parent', 'department.parent', 'responsibleUser'])
            ->where(function ($query) use ($user, $scopeIds) {
                $query->where('responsible_user_id', $user->id);

                if ($scopeIds !== []) {
                    $query->orWhereIn('department_id', $scopeIds);
                }

                $query->orWhereHas('assignments', function ($assignments) use ($user, $scopeIds) {
                    $assignments->whereNull('ended_at')
                        ->where(function ($inner) use ($user, $scopeIds) {
                            $inner->where('assigned_to_user_id', $user->id);
                            if ($scopeIds !== []) {
                                $inner->orWhereIn('assigned_to_department_id', $scopeIds);
                            }
                        });
                });
            });
    }

    private function custodyIssuesQuery($user)
    {
        $scopeIds = $user->inventoryCustodyScopeIds();

        return InventoryStockIssue::query()
            ->with(['stockItem.location.parent', 'issuedToUser', 'issuedToDepartment.parent', 'issuedByUser'])
            ->where(function ($query) use ($user, $scopeIds) {
                $query->where('issued_to_user_id', $user->id);
                if ($scopeIds !== []) {
                    $query->orWhereIn('issued_to_department_id', $scopeIds);
                }
            });
    }
}
