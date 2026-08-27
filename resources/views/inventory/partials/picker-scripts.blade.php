@once
    <script>
        window.inventoryPickerData = @json($inventoryPickerData ?? \App\Support\InventoryHierarchy::pickerPayload());
    </script>
    <script src="{{ asset('js/inventory-pickers.js') }}"></script>
@endonce
