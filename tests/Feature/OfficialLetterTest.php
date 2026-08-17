<?php

namespace Tests\Feature;

use App\Models\OfficialLetter;
use App\Models\OfficialLetterSequence;
use App\Models\User;
use App\Notifications\OfficialLetterDownloadDecisionNotification;
use App\Notifications\OfficialLetterDownloadRequestedNotification;
use App\Notifications\OfficialLetterSharedNotification;
use App\Services\OfficialLetterReferenceService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialLetterTest extends TestCase
{
    use RefreshDatabase;

    private User $signatory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->signatory = User::factory()->create([
            'status' => 'active',
            'name' => 'LADISLAUS MUSIBA',
            'title' => 'CHIEF EXECUTIVE OFFICER',
        ]);
    }

    public function test_authenticated_staff_can_access_index(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $this->actingAs($user)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertSee('Official Letters');
    }

    public function test_user_without_permission_cannot_create(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $this->actingAs($user)
            ->get(route('official-letters.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('official-letters.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_manager_can_create_letter_with_expected_reference_format(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $response = $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-29',
            ]));

        $letter = OfficialLetter::query()->first();
        $this->assertNotNull($letter);
        $this->assertSame('REF NGO/PRO/AUG-26/29-001', $letter->reference_number);
        $this->assertSame(1, $letter->sequence);
        $response->assertRedirect(route('official-letters.show', $letter));
    }

    public function test_sequence_restarts_each_month_per_department(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-01',
            ]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-05',
            ]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-09-01',
            ]))
            ->assertRedirect();

        $refs = OfficialLetter::query()->pluck('reference_number')->all();

        $this->assertContains('REF NGO/PRO/AUG-26/01-001', $refs);
        $this->assertContains('REF NGO/PRO/AUG-26/05-002', $refs);
        $this->assertContains('REF NGO/PRO/SEP-26/01-001', $refs);
        $this->assertCount(3, $refs);

        $this->assertSame(2, OfficialLetterSequence::query()->where([
            'department_code' => 'PRO',
            'year_month' => '2026-08',
        ])->value('last_sequence'));
        $this->assertSame(1, OfficialLetterSequence::query()->where([
            'department_code' => 'PRO',
            'year_month' => '2026-09',
        ])->value('last_sequence'));
    }

    public function test_departments_have_independent_monthly_sequences(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        foreach (['ADM', 'FIN', 'HR'] as $code) {
            $this->actingAs($manager)
                ->post(route('official-letters.store'), $this->validPayload([
                    'department_code' => $code,
                    'letter_date' => '2026-08-29',
                ]))
                ->assertRedirect();
        }

        $refs = OfficialLetter::query()->orderBy('department_code')->pluck('reference_number')->all();

        $this->assertSame([
            'REF NGO/ADM/AUG-26/29-001',
            'REF NGO/FIN/AUG-26/29-001',
            'REF NGO/HR/AUG-26/29-001',
        ], $refs);
    }

    public function test_posted_reference_number_is_ignored(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-29',
                'reference_number' => 'REF NGO/HACKED/AUG-26/29-999',
                'sequence' => 999,
            ]))
            ->assertRedirect();

        $letter = OfficialLetter::query()->first();
        $this->assertNotNull($letter);
        $this->assertSame('REF NGO/PRO/AUG-26/29-001', $letter->reference_number);
        $this->assertSame(1, $letter->sequence);
    }

    public function test_signatory_name_and_title_are_pulled_from_users_table(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'signatory_name' => 'FAKE NAME',
                'signatory_title' => 'FAKE TITLE',
            ]))
            ->assertRedirect();

        $letter = OfficialLetter::query()->first();
        $this->assertNotNull($letter);
        $this->assertSame($this->signatory->id, $letter->signatory_id);
        $this->assertSame('LADISLAUS MUSIBA', $letter->signatory_name);
        $this->assertSame('CHIEF EXECUTIVE OFFICER', $letter->signatory_title);
    }

    public function test_recipient_and_subject_are_stored_uppercase(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'recipient' => "mwanza regional office\np.o.box 119",
                'subject' => 'request for continued implementation',
            ]))
            ->assertRedirect();

        $letter = OfficialLetter::query()->first();
        $this->assertNotNull($letter);
        $this->assertSame("MWANZA REGIONAL OFFICE\nP.O.BOX 119", $letter->recipient);
        $this->assertSame('REQUEST FOR CONTINUED IMPLEMENTATION', $letter->subject);
    }

    public function test_reference_service_allocates_unique_sequential_numbers(): void
    {
        $service = app(OfficialLetterReferenceService::class);
        $date = Carbon::parse('2026-08-29');

        $first = $service->allocate('PRO', $date);
        $second = $service->allocate('PRO', $date);

        $this->assertSame('REF NGO/PRO/AUG-26/29-001', $first['reference_number']);
        $this->assertSame('REF NGO/PRO/AUG-26/29-002', $second['reference_number']);
        $this->assertSame(2, OfficialLetterSequence::query()->where([
            'department_code' => 'PRO',
            'year_month' => '2026-08',
        ])->value('last_sequence'));
    }

    public function test_changing_department_or_month_on_update_allocates_new_reference(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-29',
            ]));

        $letter = OfficialLetter::query()->firstOrFail();
        $this->assertSame('REF NGO/PRO/AUG-26/29-001', $letter->reference_number);

        $this->actingAs($manager)
            ->put(route('official-letters.update', $letter), $this->validPayload([
                'department_code' => 'FIN',
                'letter_date' => '2026-08-29',
            ]))
            ->assertRedirect();

        $letter->refresh();
        $this->assertSame('REF NGO/FIN/AUG-26/29-001', $letter->reference_number);
    }

    public function test_pdf_includes_selected_signatory_signature_image(): void
    {
        Storage::fake('public');

        $signatureRelative = 'signatures/ceo-sign.png';
        Storage::disk('public')->put($signatureRelative, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        // Mimic profile storage path used by ProfileController.
        $publicSignature = 'storage/'.$signatureRelative;
        $publicFullPath = public_path($publicSignature);
        @mkdir(dirname($publicFullPath), 0755, true);
        copy(Storage::disk('public')->path($signatureRelative), $publicFullPath);

        $this->signatory->forceFill(['signature' => $publicSignature])->save();

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');
        $manager->givePermissionTo('authorize official letter downloads');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload())
            ->assertRedirect();

        $letter = OfficialLetter::query()->firstOrFail();

        $this->actingAs($manager)
            ->get(route('official-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        @unlink($publicFullPath);
    }

    public function test_pdf_download_returns_pdf_response(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');
        $manager->givePermissionTo('authorize official letter downloads');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload([
                'department_code' => 'PRO',
                'letter_date' => '2026-08-29',
            ]));

        $letter = OfficialLetter::query()->firstOrFail();

        $response = $this->actingAs($manager)
            ->get(route('official-letters.download', $letter));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_shared_staff_can_see_and_edit_letter_and_receive_notification(): void
    {
        Notification::fake();

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $sharee */
        $sharee = User::factory()->create(['status' => 'active', 'email' => 'sharee@example.com']);
        $sharee->assignRole('staff');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload())
            ->assertRedirect();

        $letter = OfficialLetter::query()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('official-letters.share', $letter), [
                'shared_user_ids' => [$sharee->id],
            ])
            ->assertRedirect(route('official-letters.show', $letter));

        Notification::assertSentTo($sharee, OfficialLetterSharedNotification::class);

        $this->actingAs($sharee)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertSee('Shared')
            ->assertSee($letter->reference_number);

        $this->actingAs($sharee)
            ->get(route('official-letters.show', $letter))
            ->assertOk()
            ->assertSee('shared with you');

        $this->actingAs($sharee)
            ->get(route('official-letters.edit', $letter))
            ->assertOk();
    }

    public function test_unshared_staff_scanning_qr_show_route_gets_verification_page(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $outsider */
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('staff');

        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload())
            ->assertRedirect();

        $letter = OfficialLetter::query()->firstOrFail();

        $this->actingAs($outsider)
            ->get(route('official-letters.show', $letter))
            ->assertRedirect(route('official-letters.verify', $letter));

        $this->actingAs($outsider)
            ->get(route('official-letters.verify', $letter))
            ->assertOk()
            ->assertSee('Valid Official Letter')
            ->assertSee($letter->reference_number)
            ->assertDontSee($letter->body);

        $this->actingAs($outsider)
            ->get(route('official-letters.download', $letter))
            ->assertForbidden();
    }

    public function test_index_shows_only_own_and_shared_letters_not_other_managers(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['status' => 'active']);
        $creator->givePermissionTo('manage official letters');

        /** @var User $colleague */
        $colleague = User::factory()->create(['status' => 'active']);
        $colleague->givePermissionTo('manage official letters');

        /** @var User $sharee */
        $sharee = User::factory()->create(['status' => 'active']);
        $sharee->assignRole('staff');

        $this->actingAs($creator)
            ->post(route('official-letters.store'), $this->validPayload([
                'subject' => 'CREATOR PRIVATE LETTER',
            ]))
            ->assertRedirect();

        $letter = OfficialLetter::query()->firstOrFail();

        $this->actingAs($creator)
            ->post(route('official-letters.share', $letter), [
                'shared_user_ids' => [$sharee->id],
            ])
            ->assertRedirect();

        $this->actingAs($creator)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertSee($letter->reference_number)
            ->assertSee('CREATOR PRIVATE LETTER');

        $this->actingAs($sharee)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertSee($letter->reference_number);

        $this->actingAs($colleague)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertDontSee($letter->reference_number)
            ->assertDontSee('CREATOR PRIVATE LETTER');

        $this->actingAs($colleague)
            ->get(route('official-letters.show', $letter))
            ->assertRedirect(route('official-letters.verify', $letter));

        $this->actingAs($colleague)
            ->get(route('official-letters.download', $letter))
            ->assertForbidden();

        $this->actingAs($colleague)
            ->get(route('official-letters.edit', $letter))
            ->assertForbidden();
    }

    public function test_staff_cannot_download_letter_without_authorization(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $letter = $this->createLetterAs($manager);

        $this->actingAs($manager)
            ->get(route('official-letters.show', $letter))
            ->assertOk()
            ->assertSee('Request download authorization');

        $this->actingAs($manager)
            ->get(route('official-letters.download', $letter))
            ->assertForbidden();
    }

    public function test_download_request_notifies_authorizers_and_appears_on_their_panel(): void
    {
        Notification::fake();

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active', 'name' => 'Letter Writer']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $authorizer */
        $authorizer = User::factory()->create(['status' => 'active', 'name' => 'Letter Authorizer']);
        $authorizer->givePermissionTo('authorize official letter downloads');

        $letter = $this->createLetterAs($manager);

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter), [
                'request_note' => 'Needed for partner meeting',
            ])
            ->assertRedirect(route('official-letters.show', $letter));

        Notification::assertSentTo($authorizer, OfficialLetterDownloadRequestedNotification::class);
        Notification::assertNotSentTo($manager, OfficialLetterDownloadRequestedNotification::class);

        $this->actingAs($authorizer)
            ->get(route('official-letters.index'))
            ->assertOk()
            ->assertDontSee($letter->reference_number);

        $this->actingAs($authorizer)
            ->get(route('official-letters.index', ['tab' => 'authorizations']))
            ->assertOk()
            ->assertSee($letter->reference_number)
            ->assertSee('Download authorizations')
            ->assertSee('Letter Writer');

        $this->actingAs($authorizer)
            ->get(route('official-letters.show', $letter))
            ->assertOk()
            ->assertSee('You were asked to authorize download')
            ->assertSee('Authorize download')
            ->assertSee('Reference is made to your letter', false);
    }

    public function test_authorize_unlocks_download_for_owner_and_shared_users(): void
    {
        Notification::fake();

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $sharee */
        $sharee = User::factory()->create(['status' => 'active']);
        $sharee->assignRole('staff');

        /** @var User $authorizer */
        $authorizer = User::factory()->create(['status' => 'active']);
        $authorizer->givePermissionTo('authorize official letter downloads');

        $letter = $this->createLetterAs($manager);

        $this->actingAs($manager)
            ->post(route('official-letters.share', $letter), [
                'shared_user_ids' => [$sharee->id],
            ])
            ->assertRedirect();

        $this->actingAs($sharee)
            ->get(route('official-letters.download', $letter))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertRedirect();

        $this->actingAs($authorizer)
            ->post(route('official-letters.authorize-download', $letter))
            ->assertRedirect(route('official-letters.show', $letter));

        Notification::assertSentTo($manager, OfficialLetterDownloadDecisionNotification::class);

        $this->actingAs($manager)
            ->get(route('official-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($sharee)
            ->get(route('official-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_reject_blocks_download_and_allows_a_new_request(): void
    {
        Notification::fake();

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $authorizer */
        $authorizer = User::factory()->create(['status' => 'active']);
        $authorizer->givePermissionTo('authorize official letter downloads');

        $letter = $this->createLetterAs($manager);

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertRedirect();

        $this->actingAs($authorizer)
            ->post(route('official-letters.reject-download', $letter), [
                'decision_note' => 'Please revise the recipient first.',
            ])
            ->assertRedirect(route('official-letters.show', $letter));

        Notification::assertSentTo($manager, OfficialLetterDownloadDecisionNotification::class);

        $this->actingAs($manager)
            ->get(route('official-letters.download', $letter))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('official-letters.show', $letter))
            ->assertOk()
            ->assertSee('Download rejected');

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertRedirect(route('official-letters.show', $letter));

        $this->assertSame(2, $letter->downloadRequests()->count());
        $this->assertTrue($letter->fresh()->hasPendingDownloadRequest());
    }

    public function test_edit_after_authorize_still_allows_download(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        /** @var User $authorizer */
        $authorizer = User::factory()->create(['status' => 'active']);
        $authorizer->givePermissionTo('authorize official letter downloads');

        $letter = $this->createLetterAs($manager, [
            'subject' => 'ORIGINAL SUBJECT',
        ]);

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertRedirect();

        $this->actingAs($authorizer)
            ->post(route('official-letters.authorize-download', $letter))
            ->assertRedirect();

        $this->actingAs($manager)
            ->put(route('official-letters.update', $letter), $this->validPayload([
                'subject' => 'UPDATED SUBJECT AFTER AUTHORIZATION',
            ]))
            ->assertRedirect();

        $letter->refresh();
        $this->assertSame('UPDATED SUBJECT AFTER AUTHORIZATION', $letter->subject);

        $this->actingAs($manager)
            ->get(route('official-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_with_authorize_permission_can_download_own_letter_without_requesting(): void
    {
        /** @var User $authorizer */
        $authorizer = User::factory()->create(['status' => 'active']);
        $authorizer->givePermissionTo('manage official letters');
        $authorizer->givePermissionTo('authorize official letter downloads');

        $letter = $this->createLetterAs($authorizer);

        $this->actingAs($authorizer)
            ->get(route('official-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pending_request_cannot_be_submitted_twice(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage official letters');

        $letter = $this->createLetterAs($manager);

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('official-letters.request-download', $letter))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLetterAs(User $manager, array $overrides = []): OfficialLetter
    {
        $this->actingAs($manager)
            ->post(route('official-letters.store'), $this->validPayload($overrides))
            ->assertRedirect();

        return OfficialLetter::query()->latest('created_at')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'department_code' => 'PRO',
            'letter_date' => '2026-08-29',
            'recipient' => "MWANZA REGIONAL OFFICE\nTHE REGIONAL EDUCATION OFFICER,\nP.O.BOX 119,\nMWANZA",
            'subject' => 'REQUEST FOR CONTINUED IMPLEMENTATION',
            'salutation' => 'Dear Sir/Madam,',
            'body' => '<p>Reference is made to your letter.</p><p>We request continued implementation.</p>',
            'closing' => 'In service,',
            'signatory_id' => $this->signatory->id,
            'signature_mode' => 'digital',
        ], $overrides);
    }
}
