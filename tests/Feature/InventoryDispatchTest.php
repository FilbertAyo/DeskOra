<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryDispatch;
use App\Models\InventoryDispatchItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryStockItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_full_dispatch_workflow_preserves_custodian_and_updates_stock(): void
    {
        $admin = $this->inventoryUser(['manage inventory dispatches', 'view inventory']);
        $ayo = $this->staffUser('Ayo');
        $john = $this->staffUser('John');
        $sarah = $this->staffUser('Sarah');
        $location = InventoryLocation::query()->create([
            'name' => 'Main Office',
            'status' => 'active',
        ]);

        $assignedLaptop = $this->makeAsset([
            'name' => 'Laptop #001',
            'status' => InventoryAsset::STATUS_ASSIGNED,
            'responsible_user_id' => $ayo->id,
            'location_id' => $location->id,
        ]);
        $availableProjector = $this->makeAsset([
            'name' => 'Projector #001',
            'status' => InventoryAsset::STATUS_AVAILABLE,
            'location_id' => $location->id,
        ]);
        $damagedCamera = $this->makeAsset([
            'name' => 'Camera #001',
            'status' => InventoryAsset::STATUS_ASSIGNED,
            'responsible_user_id' => $sarah->id,
            'location_id' => $location->id,
        ]);
        $missingMic = $this->makeAsset([
            'name' => 'Microphone #001',
            'status' => InventoryAsset::STATUS_AVAILABLE,
            'location_id' => $location->id,
        ]);
        $markers = $this->makeStockItem([
            'name' => 'Markers',
            'unit' => 'pcs',
            'quantity_received' => 40,
            'quantity_remaining' => 40,
        ]);

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.store'), [
                'title' => 'Mwanza STEM Workshop',
                'purpose' => 'STEM Workshop',
                'destination' => 'Mwanza',
                'dispatch_date' => '2026-09-17',
                'expected_return_date' => '2026-09-19',
                'responsible_user_ids' => [$ayo->id, $john->id, $sarah->id],
                'notes' => 'Workshop kit',
            ])
            ->assertRedirect();

        $dispatch = InventoryDispatch::query()->firstOrFail();
        $this->assertSame(InventoryDispatch::STATUS_DRAFT, $dispatch->status);
        $this->assertEqualsCanonicalizing(
            [$ayo->id, $john->id, $sarah->id],
            $dispatch->responsibleUsers()->pluck('users.id')->all()
        );

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.assets.add', $dispatch), [
                'asset_ids' => [$assignedLaptop->id, $availableProjector->id, $damagedCamera->id, $missingMic->id],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.stock.add', $dispatch), [
                'stock_item_id' => $markers->id,
                'quantity' => 30,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.confirm', $dispatch))
            ->assertRedirect(route('inventory.dispatches.show', $dispatch));

        $dispatch->refresh();
        $this->assertSame(InventoryDispatch::STATUS_DISPATCHED, $dispatch->status);

        $assignedLaptop->refresh();
        $this->assertSame(InventoryAsset::STATUS_DISPATCHED, $assignedLaptop->status);
        $this->assertSame($ayo->id, $assignedLaptop->responsible_user_id);
        $this->assertSame($location->id, $assignedLaptop->location_id);

        $markers->refresh();
        $this->assertSame(30, $markers->quantity_issued);
        $this->assertSame(10, $markers->quantity_remaining);

        $this->actingAs($admin)
            ->get(route('inventory.dispatches.return', $dispatch))
            ->assertOk();

        $dispatch->refresh();
        $this->assertSame(InventoryDispatch::STATUS_RETURNING, $dispatch->status);

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.return.mark-all-good', $dispatch))
            ->assertRedirect();

        $cameraItem = $dispatch->items()->where('asset_id', $damagedCamera->id)->firstOrFail();
        $missingItem = $dispatch->items()->where('asset_id', $missingMic->id)->firstOrFail();
        $stockLine = $dispatch->items()->where('stock_item_id', $markers->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.return.save', $dispatch), [
                'items' => [
                    $cameraItem->id => [
                        'return_outcome' => InventoryDispatchItem::OUTCOME_RECEIVED_DAMAGED,
                        'return_notes' => 'Lens cracked during setup',
                    ],
                    $missingItem->id => [
                        'return_outcome' => InventoryDispatchItem::OUTCOME_MISSING,
                    ],
                    $stockLine->id => [
                        'quantity_returned' => 20,
                        'quantity_consumed' => 10,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.complete-return', $dispatch))
            ->assertRedirect(route('inventory.dispatches.show', $dispatch));

        $dispatch->refresh();
        $this->assertSame(InventoryDispatch::STATUS_RETURNED, $dispatch->status);

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.close', $dispatch))
            ->assertRedirect(route('inventory.dispatches.show', $dispatch));

        $dispatch->refresh();
        $this->assertSame(InventoryDispatch::STATUS_CLOSED, $dispatch->status);

        $assignedLaptop->refresh();
        $this->assertSame(InventoryAsset::STATUS_ASSIGNED, $assignedLaptop->status);
        $this->assertSame($ayo->id, $assignedLaptop->responsible_user_id);
        $this->assertSame($location->id, $assignedLaptop->location_id);

        $availableProjector->refresh();
        $this->assertSame(InventoryAsset::STATUS_AVAILABLE, $availableProjector->status);
        $this->assertNull($availableProjector->responsible_user_id);

        $damagedCamera->refresh();
        $this->assertSame(InventoryAsset::STATUS_UNDER_REPAIR, $damagedCamera->status);
        $this->assertSame(InventoryAsset::CONDITION_DAMAGED, $damagedCamera->condition);
        $this->assertSame($sarah->id, $damagedCamera->responsible_user_id);
        $this->assertDatabaseHas('inventory_maintenance_records', [
            'asset_id' => $damagedCamera->id,
            'status' => InventoryMaintenanceRecord::STATUS_REPORTED,
        ]);

        $missingMic->refresh();
        $this->assertSame(InventoryAsset::STATUS_MISSING, $missingMic->status);

        $markers->refresh();
        $this->assertSame(10, $markers->quantity_issued);
        $this->assertSame(30, $markers->quantity_remaining);

        $this->actingAs($admin)
            ->get(route('inventory.assets.show', $assignedLaptop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Assets/Show')
                ->where('asset.name', $assignedLaptop->name)
                ->has('asset'));
    }

    public function test_viewer_cannot_create_a_dispatch(): void
    {
        $viewer = $this->inventoryUser(['view inventory']);

        $this->actingAs($viewer)
            ->get(route('inventory.dispatches.create'))
            ->assertForbidden();
    }

    public function test_borrowed_asset_cannot_be_added_to_a_dispatch(): void
    {
        $admin = $this->inventoryUser(['manage inventory dispatches', 'view inventory']);
        $borrowed = $this->makeAsset(['status' => InventoryAsset::STATUS_BORROWED]);
        $dispatch = $this->makeDispatch($admin);

        $this->actingAs($admin)
            ->post(route('inventory.dispatches.assets.add', $dispatch), [
                'asset_ids' => [$borrowed->id],
            ])
            ->assertSessionHasErrors('asset_ids');

        $this->assertSame(0, $dispatch->items()->count());
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

    private function staffUser(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAsset(array $overrides = []): InventoryAsset
    {
        return InventoryAsset::query()->create(array_merge([
            'asset_code' => 'WH-INV-2026-'.fake()->unique()->numerify('####'),
            'name' => 'Test Asset',
            'source_type' => InventoryAsset::SOURCE_OTHER,
            'condition' => InventoryAsset::CONDITION_GOOD,
            'status' => InventoryAsset::STATUS_AVAILABLE,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeStockItem(array $overrides = []): InventoryStockItem
    {
        return InventoryStockItem::query()->create(array_merge([
            'name' => 'Test Stock',
            'unit' => 'pcs',
            'quantity_received' => 10,
            'quantity_issued' => 0,
            'quantity_remaining' => 10,
            'minimum_level' => 0,
            'status' => 'active',
        ], $overrides));
    }

    private function makeDispatch(User $user): InventoryDispatch
    {
        $dispatch = InventoryDispatch::query()->create([
            'dispatch_number' => 'WH-DSP-2026-'.fake()->unique()->numerify('####'),
            'title' => 'Field activity',
            'purpose' => 'Outreach',
            'destination' => 'Arusha',
            'dispatch_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'status' => InventoryDispatch::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ]);
        $dispatch->responsibleUsers()->sync([$user->id]);

        return $dispatch;
    }
}
