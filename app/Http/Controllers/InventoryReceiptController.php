<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryDonor;
use App\Models\InventoryLocation;
use App\Models\InventoryProject;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptLine;
use App\Models\InventoryStockItem;
use App\Models\InventorySupplier;
use App\Services\Inventory\InventoryReceiptProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryReceiptController extends Controller
{
    public function __construct(
        private readonly InventoryReceiptProcessor $receiptProcessor,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryReceipt::class);
        $query = InventoryReceipt::query()
            ->with(['supplier', 'donor', 'project', 'location', 'receivedByUser'])
            ->withCount('lines');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        $receipts = $query->orderByDesc('received_at')->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('inventory.receipts.index', [
            'receipts' => $receipts,
            'filters' => $request->only(['search', 'source_type']),
            'sourceTypeOptions' => InventoryReceipt::sourceTypeOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', InventoryReceipt::class);

        return view('inventory.receipts.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryReceipt::class);
        $validated = $this->validatedReceipt($request);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $receipt = DB::transaction(function () use ($validated, $user) {
            return $this->receiptProcessor->process(
                $validated,
                $validated['lines'],
                $user,
            );
        });

        return redirect()
            ->route('inventory.receipts.show', $receipt)
            ->with('success', 'Receipt recorded. Assets and stock quantities were updated.');
    }

    public function show(InventoryReceipt $receipt)
    {
        $this->authorize('view', $receipt);
        $receipt->load([
            'supplier', 'donor', 'project', 'location', 'receivedByUser',
            'lines.category', 'lines.stockItem',
            'assets',
        ]);

        return view('inventory.receipts.show', [
            'receipt' => $receipt,
            'sourceTypeOptions' => InventoryReceipt::sourceTypeOptions(),
        ]);
    }

    public function addItems(InventoryReceipt $receipt)
    {
        $this->authorize('addItems', $receipt);

        if ($redirect = $this->redirectUnlessReceivedToday($receipt)) {
            return $redirect;
        }

        $receipt->load([
            'supplier', 'donor', 'project', 'location',
            'lines.category', 'lines.stockItem',
        ]);

        return view('inventory.receipts.add-items', array_merge(
            $this->formOptions(),
            ['receipt' => $receipt]
        ));
    }

    public function storeItems(Request $request, InventoryReceipt $receipt)
    {
        $this->authorize('addItems', $receipt);

        if ($redirect = $this->redirectUnlessReceivedToday($receipt)) {
            return $redirect;
        }

        $lines = $this->validatedLines($request);

        DB::transaction(function () use ($receipt, $lines) {
            $this->receiptProcessor->addLines($receipt, $lines);
        });

        return redirect()
            ->route('inventory.receipts.show', $receipt)
            ->with('success', 'Items added to this receipt. Assets and stock quantities were updated.');
    }

    private function redirectUnlessReceivedToday(InventoryReceipt $receipt)
    {
        if ($receipt->isReceivedToday()) {
            return null;
        }

        return redirect()
            ->route('inventory.receipts.show', $receipt)
            ->with('error', 'Items can only be added to receipts received today. Record a new receipt for another date.');
    }

    private function validatedReceipt(Request $request): array
    {
        $validated = $request->validate(array_merge([
            'source_type' => ['required', Rule::in(array_keys(InventoryReceipt::sourceTypeOptions()))],
            'received_at' => ['required', 'date'],
            'supplier_id' => ['nullable', 'string', 'short_id', 'exists:inventory_suppliers,id'],
            'donor_id' => ['nullable', 'string', 'short_id', 'exists:inventory_donors,id'],
            'project_id' => ['nullable', 'string', 'short_id', 'exists:inventory_projects,id'],
            'location_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->lineValidationRules()));

        $this->assertSourceLinks($validated);
        $validated['lines'] = $this->normalizeLines($validated['lines']);

        foreach (['supplier_id', 'donor_id', 'project_id', 'location_id'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validatedLines(Request $request): array
    {
        $validated = $request->validate($this->lineValidationRules());

        return $this->normalizeLines($validated['lines']);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineValidationRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_type' => ['required', Rule::in([InventoryReceiptLine::TYPE_ASSET, InventoryReceiptLine::TYPE_STOCK])],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.category_id' => ['nullable', 'string', 'short_id', 'exists:inventory_categories,id'],
            'lines.*.stock_item_id' => ['nullable', 'string', 'short_id', 'exists:inventory_stock_items,id'],
            'lines.*.serial_number' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.minimum_level' => ['nullable', 'integer', 'min:0'],
            'lines.*.unit_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.condition' => ['nullable', Rule::in(array_keys(\App\Models\InventoryAsset::conditionOptions()))],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        foreach ($lines as $index => $line) {
            foreach (['category_id', 'stock_item_id', 'serial_number', 'unit', 'minimum_level', 'unit_value', 'condition', 'notes'] as $field) {
                if (($line[$field] ?? '') === '') {
                    $lines[$index][$field] = null;
                }
            }
        }

        return $lines;
    }

    private function assertSourceLinks(array $validated): void
    {
        $source = $validated['source_type'];

        if ($source === InventoryReceipt::SOURCE_PURCHASE && empty($validated['supplier_id'])) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Select a supplier for purchase receipts.',
            ]);
        }

        if ($source === InventoryReceipt::SOURCE_DONOR && empty($validated['donor_id'])) {
            throw ValidationException::withMessages([
                'donor_id' => 'Select a donor for donor receipts.',
            ]);
        }

        if ($source === InventoryReceipt::SOURCE_GRANT_PROJECT && empty($validated['project_id'])) {
            throw ValidationException::withMessages([
                'project_id' => 'Select a project for grant/project receipts.',
            ]);
        }
    }

    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'suppliers' => InventorySupplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'donors' => InventoryDonor::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'projects' => InventoryProject::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'stockItems' => InventoryStockItem::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'unit']),
            'sourceTypeOptions' => InventoryReceipt::sourceTypeOptions(),
            'conditionOptions' => \App\Models\InventoryAsset::conditionOptions(),
        ];
    }
}
