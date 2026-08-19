<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_reporting_a_problem_sets_asset_under_repair(): void
    {
        $user = $this->inventoryUser(['manage inventory maintenance', 'view inventory']);
        $asset = $this->makeAsset();

        $this->actingAs($user)
            ->post(route('inventory.maintenance.store'), [
                'asset_id' => $asset->id,
                'problem_description' => 'Screen cracked',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'status' => InventoryAsset::STATUS_UNDER_REPAIR,
        ]);
        $this->assertDatabaseHas('inventory_maintenance_records', [
            'asset_id' => $asset->id,
            'status' => InventoryMaintenanceRecord::STATUS_REPORTED,
        ]);
    }

    public function test_repair_completion_records_provider_and_cost(): void
    {
        $user = $this->inventoryUser(['manage inventory maintenance', 'view inventory']);
        $asset = $this->makeAsset(['status' => InventoryAsset::STATUS_UNDER_REPAIR]);
        $record = InventoryMaintenanceRecord::query()->create([
            'record_number' => 'WH-MNT-2026-0001',
            'asset_id' => $asset->id,
            'status' => InventoryMaintenanceRecord::STATUS_REPORTED,
            'problem_description' => 'Fan noise',
            'reported_by_user_id' => $user->id,
            'reported_at' => now(),
            'previous_status' => InventoryAsset::STATUS_AVAILABLE,
            'previous_condition' => InventoryAsset::CONDITION_GOOD,
        ]);

        $this->actingAs($user)
            ->post(route('inventory.maintenance.complete', $record), [
                'repair_provider' => 'City Computers',
                'repaired_on' => now()->toDateString(),
                'repair_cost' => 75000,
                'condition_after' => InventoryAsset::CONDITION_GOOD,
            ])
            ->assertRedirect(route('inventory.maintenance.show', $record));

        $this->assertDatabaseHas('inventory_maintenance_records', [
            'id' => $record->id,
            'status' => InventoryMaintenanceRecord::STATUS_COMPLETED,
            'repair_provider' => 'City Computers',
        ]);
        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'status' => InventoryAsset::STATUS_AVAILABLE,
        ]);
    }

    public function test_requester_cannot_approve_own_borrow_request(): void
    {
        $requester = $this->inventoryUser([
            'manage inventory borrowing',
            'approve inventory requests',
            'view inventory',
        ]);
        $asset = $this->makeAsset();
        $borrow = $this->makeBorrowRequest($asset, $requester);

        $this->actingAs($requester)
            ->post(route('inventory.borrowing.approve', $borrow))
            ->assertForbidden();

        $this->assertSame(InventoryBorrowRequest::STATUS_REQUESTED, $borrow->fresh()->status);
    }

    public function test_another_approver_can_approve_borrow_request(): void
    {
        $requester = $this->inventoryUser(['manage inventory borrowing', 'view inventory']);
        $approver = $this->inventoryUser(['approve inventory requests', 'view inventory']);
        $asset = $this->makeAsset();
        $borrow = $this->makeBorrowRequest($asset, $requester);

        $this->actingAs($approver)
            ->post(route('inventory.borrowing.approve', $borrow))
            ->assertRedirect();

        $this->assertSame(InventoryBorrowRequest::STATUS_APPROVED, $borrow->fresh()->status);
    }

    public function test_disposal_does_not_lock_asset_until_approved(): void
    {
        $requester = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $approver = $this->inventoryUser(['approve inventory requests', 'view inventory']);
        $asset = $this->makeAsset();

        $this->actingAs($requester)
            ->post(route('inventory.disposal.store'), [
                'asset_id' => $asset->id,
                'method' => 'destroyed',
                'reason' => 'Beyond economical repair',
            ])
            ->assertRedirect();

        $this->assertSame(InventoryAsset::STATUS_AVAILABLE, $asset->fresh()->status);

        $disposal = InventoryDisposalRequest::query()->first();
        $this->actingAs($approver)
            ->post(route('inventory.disposal.approve', $disposal))
            ->assertRedirect();

        $this->assertSame(InventoryAsset::STATUS_DISPOSED, $asset->fresh()->status);

        $this->actingAs($requester)
            ->get(route('inventory.assets.edit', $asset))
            ->assertForbidden();
    }

    public function test_user_without_maintenance_permission_cannot_report_problem(): void
    {
        $user = $this->inventoryUser(['view inventory']);
        $asset = $this->makeAsset();

        $this->actingAs($user)
            ->get(route('inventory.maintenance.create', ['asset_id' => $asset->id]))
            ->assertForbidden();
    }

    public function test_admin_can_add_items_to_todays_receipt(): void
    {
        $user = $this->inventoryUser(['manage inventory receipts', 'view inventory']);

        $this->actingAs($user)
            ->post(route('inventory.receipts.store'), [
                'source_type' => 'other',
                'received_at' => now()->toDateString(),
                'lines' => [
                    [
                        'item_type' => 'stock',
                        'name' => 'A4 Paper',
                        'quantity' => 10,
                        'unit' => 'reams',
                    ],
                ],
            ])
            ->assertRedirect();

        $receipt = InventoryReceipt::query()->firstOrFail();
        $stockItem = InventoryStockItem::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('inventory.receipts.add-items', $receipt))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('inventory.receipts.store-items', $receipt), [
                'lines' => [
                    [
                        'item_type' => 'stock',
                        'name' => 'A4 Paper',
                        'quantity' => 5,
                        'stock_item_id' => $stockItem->id,
                    ],
                    [
                        'item_type' => 'asset',
                        'name' => 'Laptop',
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.receipts.show', $receipt));

        $this->assertSame(3, $receipt->fresh()->lines()->count());
        $this->assertSame(15, $stockItem->fresh()->quantity_remaining);
        $this->assertDatabaseHas('inventory_assets', [
            'name' => 'Laptop',
            'receipt_id' => $receipt->id,
        ]);
    }

    public function test_items_cannot_be_added_to_a_past_receipt(): void
    {
        $user = $this->inventoryUser(['manage inventory receipts', 'view inventory']);

        $this->actingAs($user)
            ->post(route('inventory.receipts.store'), [
                'source_type' => 'other',
                'received_at' => now()->subDay()->toDateString(),
                'lines' => [
                    [
                        'item_type' => 'asset',
                        'name' => 'Laptop',
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $receipt = InventoryReceipt::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('inventory.receipts.add-items', $receipt))
            ->assertRedirect(route('inventory.receipts.show', $receipt));

        $this->actingAs($user)
            ->post(route('inventory.receipts.store-items', $receipt), [
                'lines' => [
                    [
                        'item_type' => 'asset',
                        'name' => 'Monitor',
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.receipts.show', $receipt));

        $this->assertSame(1, $receipt->fresh()->lines()->count());
    }

    /**
     * @param  list<string>  $permissions
     */
    private function inventoryUser(array $permissions): User
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAsset(array $overrides = []): InventoryAsset
    {
        return InventoryAsset::query()->create(array_merge([
            'asset_code' => 'WH-INV-2026-'.fake()->unique()->numerify('####'),
            'name' => 'Test Laptop',
            'source_type' => InventoryAsset::SOURCE_OTHER,
            'condition' => InventoryAsset::CONDITION_GOOD,
            'status' => InventoryAsset::STATUS_AVAILABLE,
        ], $overrides));
    }

    private function makeBorrowRequest(InventoryAsset $asset, User $requester): InventoryBorrowRequest
    {
        return InventoryBorrowRequest::query()->create([
            'request_number' => 'WH-BRW-2026-'.fake()->unique()->numerify('####'),
            'item_type' => InventoryBorrowRequest::TYPE_ASSET,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'borrower_user_id' => $requester->id,
            'purpose' => 'Field visit',
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'status' => InventoryBorrowRequest::STATUS_REQUESTED,
            'requested_by_user_id' => $requester->id,
        ]);
    }
}
