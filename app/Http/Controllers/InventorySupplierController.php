<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\InventorySupplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventorySupplierController extends Controller
{
    public function index()
    {
        $suppliers = InventorySupplier::query()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Suppliers/Index', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $supplierId = $request->filled('supplier_id') ? $request->input('supplier_id') : null;

        $validated = $this->validated($request, $supplierId);

        if ($supplierId) {
            InventorySupplier::query()->findOrFail($supplierId)->update($validated);

            return redirect()->route('inventory.suppliers.index')->with('success', 'Supplier updated successfully.');
        }

        InventorySupplier::query()->create($validated);

        return redirect()->route('inventory.suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, InventorySupplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier->id));

        return redirect()->route('inventory.suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(InventorySupplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('inventory.suppliers.index')->with('success', 'Supplier deleted successfully.');
    }

    private function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_suppliers', 'name')->ignore($ignoreId),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
