<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\InventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryCategoryController extends Controller
{
    public function index()
    {
        $categories = InventoryCategory::query()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Categories/Index', compact('categories'));
    }

    public function store(Request $request)
    {
        $categoryId = $request->filled('category_id') ? $request->input('category_id') : null;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_categories', 'name')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($categoryId) {
            $category = InventoryCategory::query()->findOrFail($categoryId);
            $category->update($validated);

            return redirect()->route('inventory.categories.index')
                ->toastSuccess('Category updated', $category->name.' was saved.');
        }

        InventoryCategory::query()->create($validated);

        return redirect()->route('inventory.categories.index')
            ->toastSuccess('Category created', $validated['name'].' was added.');
    }

    public function update(Request $request, InventoryCategory $category)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_categories', 'name')->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $category->update($validated);

        return redirect()->route('inventory.categories.index')
            ->toastSuccess('Category updated', $category->name.' was saved.');
    }

    public function destroy(InventoryCategory $category)
    {
        $name = $category->name;
        $category->delete();

        return redirect()->route('inventory.categories.index')
            ->toastSuccess('Category deleted', $name.' was removed.');
    }
}
