<?php

namespace Tests\Unit;

use App\Support\InventoryReturnUrl;
use Tests\TestCase;

class InventoryReturnUrlTest extends TestCase
{
    public function test_keeps_inventory_list_pagination_and_filters(): void
    {
        $this->assertSame(
            '/inventory/assets?page=4&search=chair',
            InventoryReturnUrl::sanitize(url('/inventory/assets?page=4&search=chair'))
        );

        $this->assertSame(
            '/inventory/stock-items?page=2',
            InventoryReturnUrl::sanitize('/inventory/stock-items?page=2')
        );
    }

    public function test_rejects_open_redirects_and_non_inventory_paths(): void
    {
        $this->assertNull(InventoryReturnUrl::sanitize('https://evil.test/inventory/assets'));
        $this->assertNull(InventoryReturnUrl::sanitize('/dashboard'));
        $this->assertNull(InventoryReturnUrl::sanitize('//evil.test/inventory'));
        $this->assertNull(InventoryReturnUrl::sanitize(''));
        $this->assertNull(InventoryReturnUrl::sanitize(null));
    }
}
