<template id="lineTemplate">
    <div class="border rounded p-3 mb-3 receipt-line">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="line-title">Item</strong>
            <button type="button" class="btn btn-sm btn-light remove-line">Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">Type</label>
                <select class="form-control form-control-sm line-type" name="lines[__INDEX__][item_type]">
                    <option value="asset">Tracked Asset</option>
                    <option value="stock">Stock / Consumable</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small">Name</label>
                <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][name]" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Qty</label>
                <input type="number" min="1" value="1" class="form-control form-control-sm" name="lines[__INDEX__][quantity]" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Category</label>
                <select class="form-control form-control-sm" name="lines[__INDEX__][category_id]">
                    <option value="">--</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Project <span class="text-muted">(optional)</span></label>
                <select class="form-control form-control-sm" name="lines[__INDEX__][project_id]">
                    <option value="">-- Select project --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 asset-only">
                <label class="form-label small">Condition</label>
                <select class="form-control form-control-sm" name="lines[__INDEX__][condition]">
                    @foreach($conditionOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'new')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 asset-only">
                <label class="form-label small">Unit Value (TZS)</label>
                <input type="number" min="0" step="0.01" class="form-control form-control-sm" name="lines[__INDEX__][unit_value]">
            </div>
            <div class="col-md-4 stock-only d-none">
                <label class="form-label small">Existing Stock Item</label>
                <select class="form-control form-control-sm" name="lines[__INDEX__][stock_item_id]">
                    <option value="">Create new stock item</option>
                    @foreach($stockItems as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}{{ $item->unit ? ' ('.$item->unit.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 stock-only d-none">
                <label class="form-label small">Unit</label>
                <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][unit]" placeholder="boxes, pcs">
            </div>
            <div class="col-md-4 stock-only d-none">
                <label class="form-label small">Minimum Level</label>
                <input type="number" min="0" class="form-control form-control-sm" name="lines[__INDEX__][minimum_level]" value="0">
            </div>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const linesWrap = document.getElementById('receiptLines');
        const template = document.getElementById('lineTemplate');
        let lineIndex = 0;

        function toggleLineType(line) {
            const type = line.querySelector('.line-type').value;
            line.querySelectorAll('.asset-only').forEach(el => el.classList.toggle('d-none', type !== 'asset'));
            line.querySelectorAll('.stock-only').forEach(el => el.classList.toggle('d-none', type !== 'stock'));
        }

        function addLine() {
            const html = template.innerHTML.replaceAll('__INDEX__', String(lineIndex++));
            const holder = document.createElement('div');
            holder.innerHTML = html.trim();
            const line = holder.firstElementChild;
            linesWrap.appendChild(line);
            line.querySelector('.line-type').addEventListener('change', () => toggleLineType(line));
            line.querySelector('.remove-line').addEventListener('click', () => {
                if (linesWrap.children.length > 1) line.remove();
            });
            toggleLineType(line);
        }

        document.getElementById('addLineBtn').addEventListener('click', addLine);
        addLine();
    });
</script>
