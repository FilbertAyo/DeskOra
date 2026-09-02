<?php

namespace Tests\Feature;

use App\Mail\InventoryAssignmentReportMail;
use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockIssue;
use App\Models\InventoryStockItem;
use App\Models\InventoryLocation;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_manager_can_delete_mistaken_asset(): void
    {
        $user = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $asset = $this->makeAsset();

        $this->actingAs($user)
            ->delete(route('inventory.assets.destroy', $asset))
            ->assertRedirect(route('inventory.assets.index'));

        $this->assertDatabaseMissing('inventory_assets', ['id' => $asset->id]);
    }

    public function test_asset_with_disposal_history_cannot_be_deleted(): void
    {
        $user = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $asset = $this->makeAsset();

        InventoryDisposalRequest::query()->create([
            'request_number' => 'WH-DSP-2026-0001',
            'asset_id' => $asset->id,
            'reason' => 'End of life',
            'method' => InventoryDisposalRequest::METHOD_DESTROYED,
            'status' => InventoryDisposalRequest::STATUS_REQUESTED,
            'requested_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('inventory.assets.index'))
            ->delete(route('inventory.assets.destroy', $asset))
            ->assertRedirect(route('inventory.assets.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inventory_assets', ['id' => $asset->id]);
    }

    public function test_assign_modal_saves_department_sub_department_or_user(): void
    {
        $officer = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $department = Department::factory()->create(['name' => 'Finance']);
        $subDepartment = Department::factory()->create([
            'name' => 'Accounts',
            'parent_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'status' => 'active',
            'department_id' => $department->id,
        ]);
        $staff->subDepartments()->attach($subDepartment->id);
        $asset = $this->makeAsset();

        $this->actingAs($officer)
            ->post(route('inventory.assets.assign', $asset), [
                'parent_department_id' => $department->id,
                'assign_whole_department' => '1',
            ])
            ->assertRedirect(route('inventory.assets.show', $asset));

        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'department_id' => $department->id,
            'responsible_user_id' => null,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);

        $this->actingAs($officer)
            ->post(route('inventory.assets.assign', $asset), [
                'parent_department_id' => $department->id,
                'sub_department_id' => $subDepartment->id,
                'assign_whole_sub_department' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'department_id' => $subDepartment->id,
            'responsible_user_id' => null,
        ]);

        $this->actingAs($officer)
            ->post(route('inventory.assets.assign', $asset), [
                'parent_department_id' => $department->id,
                'sub_department_id' => $subDepartment->id,
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_assets', [
            'id' => $asset->id,
            'department_id' => $subDepartment->id,
            'responsible_user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('inventory_asset_assignments', [
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $staff->id,
            'assigned_to_department_id' => $subDepartment->id,
            'ended_at' => null,
        ]);
    }

    public function test_assign_returns_to_the_same_paginated_assets_list(): void
    {
        $officer = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $department = Department::factory()->create(['name' => 'Finance']);
        $asset = $this->makeAsset();
        $listUrl = route('inventory.assets.index', ['page' => 4, 'search' => 'Laptop']);

        $this->actingAs($officer)
            ->from($listUrl)
            ->post(route('inventory.assets.assign', $asset), [
                'parent_department_id' => $department->id,
                'assign_whole_department' => '1',
                'return_url' => $listUrl,
            ])
            ->assertRedirect($listUrl)
            ->assertSessionHas('success')
            ->assertSessionHas('highlight_id', $asset->id);
    }

    public function test_assign_from_asset_view_stays_on_the_view_page(): void
    {
        $officer = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $department = Department::factory()->create(['name' => 'Finance']);
        $asset = $this->makeAsset();
        $showUrl = route('inventory.assets.show', $asset);

        $this->actingAs($officer)
            ->from($showUrl)
            ->post(route('inventory.assets.assign', $asset), [
                'parent_department_id' => $department->id,
                'assign_whole_department' => '1',
                'return_url' => $showUrl,
            ])
            ->assertRedirect($showUrl);
    }

    public function test_stock_issue_returns_to_the_same_paginated_list(): void
    {
        $officer = $this->inventoryUser(['manage inventory stock', 'view inventory']);
        $department = Department::factory()->create(['name' => 'Finance']);
        $stock = InventoryStockItem::query()->create([
            'name' => 'A4 Paper',
            'quantity_received' => 20,
            'quantity_issued' => 0,
            'quantity_remaining' => 20,
            'minimum_level' => 2,
            'status' => 'active',
        ]);
        $listUrl = route('inventory.stock-items.index', ['page' => 3]);

        $this->actingAs($officer)
            ->from($listUrl)
            ->post(route('inventory.stock-items.issue', $stock), [
                'quantity' => 1,
                'parent_department_id' => $department->id,
                'assign_whole_department' => '1',
                'return_url' => $listUrl,
            ])
            ->assertRedirect($listUrl)
            ->assertSessionHas('highlight_id', $stock->id);
    }

    public function test_asset_can_be_placed_in_a_room_under_a_location(): void
    {
        $officer = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $hq = InventoryLocation::query()->create([
            'name' => 'Adilisha HQ',
            'status' => 'active',
        ]);
        $room = InventoryLocation::query()->create([
            'name' => 'Board Room',
            'status' => 'active',
            'parent_id' => $hq->id,
        ]);

        $this->actingAs($officer)
            ->post(route('inventory.assets.store'), [
                'name' => 'Projector',
                'source_type' => InventoryAsset::SOURCE_OTHER,
                'condition' => InventoryAsset::CONDITION_GOOD,
                'status' => InventoryAsset::STATUS_AVAILABLE,
                'location_id' => $room->id,
            ])
            ->assertRedirect(route('inventory.assets.index'));

        $asset = InventoryAsset::query()->where('name', 'Projector')->firstOrFail();
        $this->assertSame($room->id, $asset->location_id);

        $this->actingAs($officer)
            ->get(route('inventory.assets.index', ['location_id' => $hq->id]))
            ->assertOk()
            ->assertSee('Projector')
            ->assertSee('Adilisha HQ › Board Room');
    }

    public function test_staff_only_see_assets_and_stock_in_their_custody(): void
    {
        $department = Department::factory()->create(['name' => 'Programmes']);
        $otherDepartment = Department::factory()->create(['name' => 'Finance Unit']);
        $staff = User::factory()->create([
            'status' => 'active',
            'department_id' => $department->id,
        ]);
        $staff->givePermissionTo('view own inventory items');
        $officer = $this->inventoryUser(['manage inventory assets', 'manage inventory stock', 'view inventory']);

        $mine = $this->makeAsset([
            'name' => 'My Laptop',
            'responsible_user_id' => $staff->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $deptAsset = $this->makeAsset([
            'name' => 'Department Camera',
            'department_id' => $department->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $otherAsset = $this->makeAsset([
            'name' => 'Other Printer',
            'department_id' => $otherDepartment->id,
            'responsible_user_id' => $officer->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);

        $stock = InventoryStockItem::query()->create([
            'name' => 'A4 Paper',
            'quantity_received' => 20,
            'quantity_issued' => 4,
            'quantity_remaining' => 16,
            'minimum_level' => 2,
            'status' => 'active',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 4,
            'issued_to_user_id' => $staff->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 1,
            'issued_to_department_id' => $otherDepartment->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('inventory.assets.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('inventory.my-items.index'))
            ->assertOk()
            ->assertSee('My Laptop')
            ->assertSee('Department Camera')
            ->assertDontSee('Other Printer');

        $this->actingAs($staff)
            ->get(route('inventory.assets.show', $mine))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('inventory.assets.show', $otherAsset))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('inventory.my-items.index', ['tab' => 'stock']))
            ->assertOk()
            ->assertSee('A4 Paper');

        $this->actingAs($staff)
            ->get(route('inventory.my-items.export', ['type' => 'assets', 'format' => 'xlsx']))
            ->assertOk();
    }

    public function test_staff_see_whole_unit_assignments_for_their_department_and_sub_department_only(): void
    {
        $finance = Department::factory()->create(['name' => 'Finance']);
        $accounts = Department::factory()->create([
            'name' => 'Accounts',
            'parent_id' => $finance->id,
        ]);
        $procurement = Department::factory()->create([
            'name' => 'Procurement',
            'parent_id' => $finance->id,
        ]);
        $colleague = User::factory()->create([
            'status' => 'active',
            'department_id' => $finance->id,
        ]);
        $colleague->subDepartments()->attach($accounts->id);
        $staff = User::factory()->create([
            'status' => 'active',
            'department_id' => $finance->id,
        ]);
        $staff->subDepartments()->attach($accounts->id);
        $staff->givePermissionTo('view own inventory items');
        $officer = $this->inventoryUser(['manage inventory stock', 'view inventory']);

        $this->makeAsset([
            'name' => 'Staff Laptop',
            'department_id' => $accounts->id,
            'responsible_user_id' => $staff->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $this->makeAsset([
            'name' => 'Finance Projector',
            'department_id' => $finance->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $this->makeAsset([
            'name' => 'Accounts Camera',
            'department_id' => $accounts->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $this->makeAsset([
            'name' => 'Procurement Printer',
            'department_id' => $procurement->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);
        $this->makeAsset([
            'name' => 'Colleague Monitor',
            'department_id' => $accounts->id,
            'responsible_user_id' => $colleague->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);

        $stock = InventoryStockItem::query()->create([
            'name' => 'Shared Toner',
            'quantity_received' => 20,
            'quantity_issued' => 8,
            'quantity_remaining' => 12,
            'minimum_level' => 2,
            'status' => 'active',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 2,
            'issued_to_department_id' => $finance->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
            'purpose' => 'Finance unit issue',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 2,
            'issued_to_department_id' => $accounts->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
            'purpose' => 'Accounts unit issue',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 2,
            'issued_to_department_id' => $procurement->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
            'purpose' => 'Procurement unit issue',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 1,
            'issued_to_user_id' => $colleague->id,
            'issued_to_department_id' => $accounts->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
            'purpose' => 'Colleague personal issue',
        ]);
        InventoryStockIssue::query()->create([
            'stock_item_id' => $stock->id,
            'quantity' => 1,
            'issued_to_user_id' => $staff->id,
            'issued_to_department_id' => $accounts->id,
            'issued_by_user_id' => $officer->id,
            'issued_at' => now(),
            'purpose' => 'Staff personal issue',
        ]);

        $this->actingAs($staff)
            ->get(route('inventory.my-items.index'))
            ->assertOk()
            ->assertSee('Staff Laptop')
            ->assertSee('Finance Projector')
            ->assertSee('Accounts Camera')
            ->assertDontSee('Procurement Printer')
            ->assertDontSee('Colleague Monitor');

        $this->actingAs($staff)
            ->get(route('inventory.my-items.index', ['tab' => 'stock']))
            ->assertOk()
            ->assertSee('Finance unit issue')
            ->assertSee('Accounts unit issue')
            ->assertSee('Staff personal issue')
            ->assertDontSee('Procurement unit issue')
            ->assertDontSee('Colleague personal issue');
    }

    public function test_assets_list_can_be_exported_as_excel_and_pdf(): void
    {
        $user = $this->inventoryUser(['view inventory']);
        $this->makeAsset(['name' => 'Export Laptop']);

        $this->actingAs($user)
            ->get(route('inventory.assets.export', ['format' => 'xlsx']))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('inventory.assets.export', ['format' => 'pdf']))
            ->assertOk();
    }

    public function test_assets_export_can_filter_by_department_and_room(): void
    {
        $user = $this->inventoryUser(['view inventory']);
        $department = Department::query()->create(['name' => 'Finance', 'status' => 'active']);
        $hq = InventoryLocation::query()->create(['name' => 'HQ', 'status' => 'active']);
        $room = InventoryLocation::query()->create([
            'name' => 'Room A',
            'status' => 'active',
            'parent_id' => $hq->id,
        ]);

        $this->makeAsset(['name' => 'Finance Laptop', 'department_id' => $department->id]);
        $this->makeAsset(['name' => 'Room Asset', 'location_id' => $room->id]);

        $this->actingAs($user)
            ->get(route('inventory.assets.export', [
                'format' => 'xlsx',
                'department_id' => $department->id,
            ]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('inventory.assets.export', [
                'format' => 'xlsx',
                'location_id' => $room->id,
            ]))
            ->assertOk();
    }

    public function test_qr_labels_require_a_room_selection(): void
    {
        $user = $this->inventoryUser(['view inventory']);
        $hq = InventoryLocation::query()->create(['name' => 'HQ', 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('inventory.assets.labels'))
            ->assertRedirect(route('inventory.assets.index'));

        $this->actingAs($user)
            ->get(route('inventory.assets.labels', ['location_id' => $hq->id]))
            ->assertRedirect(route('inventory.assets.index'));
    }

    public function test_qr_labels_show_assets_for_selected_room(): void
    {
        $user = $this->inventoryUser(['view inventory']);
        $hq = InventoryLocation::query()->create(['name' => 'HQ', 'status' => 'active']);
        $room = InventoryLocation::query()->create([
            'name' => 'Room A',
            'status' => 'active',
            'parent_id' => $hq->id,
        ]);
        $otherRoom = InventoryLocation::query()->create([
            'name' => 'Room B',
            'status' => 'active',
            'parent_id' => $hq->id,
        ]);

        $this->makeAsset(['name' => 'Room A Laptop', 'location_id' => $room->id]);
        $this->makeAsset(['name' => 'Room B Laptop', 'location_id' => $otherRoom->id]);

        $this->actingAs($user)
            ->get(route('inventory.assets.labels', ['location_id' => $room->id]))
            ->assertOk()
            ->assertSee('Room A Laptop')
            ->assertSee('Room A')
            ->assertDontSee('Room B Laptop');
    }

    public function test_admin_can_email_staff_assignment_report(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $admin = $this->inventoryUser(['manage inventory assets', 'view inventory']);
        $department = Department::query()->create(['name' => 'Finance', 'status' => 'active']);
        $staff = User::factory()->create([
            'status' => 'active',
            'department_id' => $department->id,
        ]);

        $this->makeAsset([
            'name' => 'Assigned Laptop',
            'department_id' => $department->id,
            'responsible_user_id' => $staff->id,
            'status' => InventoryAsset::STATUS_ASSIGNED,
        ]);

        $this->actingAs($admin)
            ->post(route('inventory.reports.notify-assignments'), [
                'user_id' => $staff->id,
            ])
            ->assertRedirect(route('inventory.reports.index'))
            ->assertSessionHas('success');

        Mail::assertSent(InventoryAssignmentReportMail::class, function (InventoryAssignmentReportMail $mail) use ($staff) {
            return $mail->hasTo($staff->email) && $mail->assets->count() === 1;
        });
    }

    public function test_stock_list_can_be_exported(): void
    {
        $user = $this->inventoryUser(['view inventory', 'manage inventory stock']);
        InventoryStockItem::query()->create([
            'name' => 'Staples',
            'quantity_received' => 10,
            'quantity_issued' => 0,
            'quantity_remaining' => 10,
            'minimum_level' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('inventory.stock-items.export', ['format' => 'xlsx']))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('inventory.stock-items.export', ['format' => 'pdf']))
            ->assertOk();
    }

    public function test_locations_page_separates_sites_and_rooms(): void
    {
        $user = $this->inventoryUser(['manage inventory settings', 'view inventory']);
        $hq = InventoryLocation::query()->create([
            'name' => 'Adilisha HQ',
            'status' => 'active',
        ]);
        InventoryLocation::query()->create([
            'name' => 'Conference Suite',
            'status' => 'active',
            'parent_id' => $hq->id,
        ]);

        $this->actingAs($user)
            ->get(route('inventory.locations.index'))
            ->assertOk()
            ->assertSee('Adilisha HQ')
            ->assertDontSee('Conference Suite');

        $this->actingAs($user)
            ->get(route('inventory.locations.index', ['tab' => 'rooms']))
            ->assertOk()
            ->assertSee('Conference Suite')
            ->assertSee('Adilisha HQ')
            ->assertSee('New Room');
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
