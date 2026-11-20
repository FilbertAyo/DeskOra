<?php

namespace App\Http\Controllers;

use App\Models\InventorySupplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventorySupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = InventorySupplier::query()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Inventory/Suppliers/Index', [
            'suppliers' => $query->get(),
            'filters' => $request->only(['search']),
        ]);
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
