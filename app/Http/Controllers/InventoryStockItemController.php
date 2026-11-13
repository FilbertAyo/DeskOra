<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Exports\InventoryStockItemsExport;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryStockAdjustment;
use App\Models\InventoryStockIssue;
use App\Models\InventoryStockItem;
use App\Models\InventorySupplier;
use App\Models\User;
use App\Support\InventoryHierarchy;
use App\Support\InventoryReturnUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class InventoryStockItemController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryStockItem::class);

        $query = $this->filteredStockQuery($request);

        $stockItems = $query->orderBy('name')->paginate(20)->withQueryString();
        $lowStockCount = InventoryStockItem::query()
            ->where('status', 'active')
            ->where('minimum_level', '>', 0)
            ->whereColumn('quantity_remaining', '<=', 'minimum_level')
            ->count();

        return Inertia::render('Inventory/StockItems/Index', array_merge(
            compact('stockItems', 'lowStockCount'),
            $this->formOptions(),
            [
                'filters' => $request->only(['search', 'category_id', 'location_id', 'low_stock']),
                'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
            ]
        ));
    }

    public function create()
    {
        $this->authorize('create', InventoryStockItem::class);

        return Inertia::render('Inventory/StockItems/Create', array_merge(
            $this->formOptions(),
            ['inventoryPickerData' => InventoryHierarchy::pickerPayload()]
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryStockItem::class);
        $validated = $this->validatedStockItem($request);
        $received = (int) ($validated['quantity_received'] ?? 0);
        $validated['quantity_issued'] = 0;
        $validated['quantity_remaining'] = $received;

        InventoryStockItem::query()->create($validated);

        return redirect()->route('inventory.stock-items.index')->with('success', 'Stock item created successfully.');
    }

    public function edit(InventoryStockItem $stockItem)
    {
        $this->authorize('update', $stockItem);

        return Inertia::render('Inventory/StockItems/Edit', array_merge(
            compact('stockItem'),
            $this->formOptions(),
            ['inventoryPickerData' => InventoryHierarchy::pickerPayload()]
        ));
    }

    public function update(Request $request, InventoryStockItem $stockItem)
    {
        $this->authorize('update', $stockItem);
        $validated = $this->validatedStockItem($request, $stockItem);
        $received = (int) ($validated['quantity_received'] ?? 0);
        $issued = (int) $stockItem->quantity_issued;
        $validated['quantity_remaining'] = max(0, $received - $issued);
        $validated['quantity_issued'] = $issued;

        $stockItem->update($validated);

        return redirect()->route('inventory.stock-items.index')->with('success', 'Stock item updated successfully.');
    }

    public function issue(Request $request, InventoryStockItem $stockItem)
    {
        $this->authorize('issue', $stockItem);
        $ownership = InventoryHierarchy::resolveOwnership($request, 'issued_to_user_id', 'issued_to_department_id');
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'issued_to_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'issued_to_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'parent_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'sub_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['issued_to_user_id'] = $ownership['user_id'];
        $validated['issued_to_department_id'] = $ownership['department_id'];

        if (empty($validated['issued_to_user_id']) && empty($validated['issued_to_department_id'])) {
            return redirect()->back()->withErrors([
                'parent_department_id' => 'Issue to a department, sub-department, or staff member.',
            ])->withInput();
        }

        if ($validated['quantity'] > $stockItem->quantity_remaining) {
            return redirect()->back()->withErrors([
                'quantity' => 'Cannot issue more than the remaining quantity.',
            ])->withInput();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($stockItem, $validated, $user) {
            InventoryStockIssue::query()->create([
                'stock_item_id' => $stockItem->id,
                'quantity' => $validated['quantity'],
                'issued_to_user_id' => $validated['issued_to_user_id'] ?? null,
                'issued_to_department_id' => $validated['issued_to_department_id'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'issued_by_user_id' => $user->id,
                'issued_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $stockItem->update([
                'quantity_issued' => $stockItem->quantity_issued + $validated['quantity'],
                'quantity_remaining' => $stockItem->quantity_remaining - $validated['quantity'],
            ]);
        });

        return InventoryReturnUrl::redirect($request, route('inventory.stock-items.index'))
            ->with('success', 'Stock issued successfully.')
            ->with('highlight_id', $stockItem->id);
    }

    public function adjust(Request $request, InventoryStockItem $stockItem)
    {
        $this->authorize('adjust', $stockItem);
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', Rule::in(array_keys(InventoryStockAdjustment::reasonOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['direction'] === 'decrease' && $validated['quantity'] > $stockItem->quantity_remaining) {
            return redirect()->back()->withErrors([
                'quantity' => 'Cannot decrease more than the remaining quantity.',
            ])->withInput();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($stockItem, $validated, $user) {
            InventoryStockAdjustment::query()->create([
                'stock_item_id' => $stockItem->id,
                'direction' => $validated['direction'],
                'quantity' => $validated['quantity'],
                'reason_code' => $validated['reason_code'],
                'notes' => $validated['notes'] ?? null,
                'adjusted_by_user_id' => $user->id,
                'adjusted_at' => now(),
            ]);

            $delta = $validated['direction'] === 'increase'
                ? $validated['quantity']
                : -$validated['quantity'];

            $newRemaining = max(0, $stockItem->quantity_remaining + $delta);
            $newReceived = max(0, $stockItem->quantity_received + max(0, $delta));

            $stockItem->update([
                'quantity_received' => $newReceived,
                'quantity_remaining' => $newRemaining,
            ]);
        });

        return InventoryReturnUrl::redirect($request, route('inventory.stock-items.index'))
            ->with('success', 'Stock adjusted successfully.')
            ->with('highlight_id', $stockItem->id);
    }

    private function validatedStockItem(Request $request, ?InventoryStockItem $stockItem = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'short_id', 'exists:inventory_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quantity_received' => ['required', 'integer', 'min:0'],
            'minimum_level' => ['required', 'integer', 'min:0'],
            'location_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
            'supplier_id' => ['nullable', 'string', 'short_id', 'exists:inventory_suppliers,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $validated['location_id'] = InventoryHierarchy::resolveLocationId($request) ?: ($validated['location_id'] ?? null);

        foreach (['category_id', 'location_id', 'supplier_id'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    public function export(Request $request, string $format)
    {
        $this->authorize('viewAny', InventoryStockItem::class);

        $stockItems = $this->filteredStockQuery($request)
            ->orderBy('name')
            ->get();

        $filename = 'inventory-stock-items-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('inventory.exports.stock-pdf', [
                'stockItems' => $stockItems,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(new InventoryStockItemsExport($stockItems), $filename.'.xlsx');
    }

    private function filteredStockQuery(Request $request)
    {
        $query = InventoryStockItem::query()
            ->with(['category', 'location.parent', 'supplier']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            InventoryHierarchy::applyLocationFilter($query, $request->input('location_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity_remaining', '<=', 'minimum_level')
                ->where('minimum_level', '>', 0);
        }

        return $query;
    }

    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'suppliers' => InventorySupplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name', 'department_id']),
            'adjustmentReasons' => InventoryStockAdjustment::reasonOptions(),
        ];
    }
}
