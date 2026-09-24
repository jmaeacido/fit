<?php
namespace Tests\Feature;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class ShirtTest extends TestCase
{
    use RefreshDatabase;
    private function payload(array $overrides = []): array { return array_replace(['name' => 'Alex Morgan', 'display_name' => 'MORGAN', 'display_number' => '07', 'size' => 'M', 'designs' => ['1', '2'], 'request_key' => (string) Str::uuid()], $overrides); }
    public function test_public_form_loads(): void { $this->withoutVite()->get('/')->assertOk(); }
    public function test_required_and_allowed_values(): void {
        $this->postJson('/submissions', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'display_name', 'display_number', 'size', 'designs', 'request_key']);
        foreach ([['display_name' => '  '], ['display_name' => str_repeat('a', 151)], ['display_number' => '7'], ['display_number' => '007'], ['display_number' => 'ab'], ['display_number' => '-1'], ['display_number' => 7], ['name' => '  '], ['size' => '4XL'], ['designs' => []], ['designs' => ['3']], ['designs' => ['1', '1']]] as $invalid) $this->postJson('/submissions', $this->payload($invalid))->assertUnprocessable();
        $this->assertDatabaseCount('submissions', 0);
    }
    public function test_submissions_and_retries_are_idempotent(): void {
        $payload = $this->payload();
        $id = $this->postJson('/submissions', $payload)->assertCreated()->json('id');
        $this->postJson('/submissions', $payload)->assertOk()->assertJson(['id' => $id]);
        $this->postJson('/submissions', array_replace($payload, ['size' => 'L']))->assertConflict();
        foreach (['display_name' => 'ALEX', 'display_number' => '08'] as $field => $value) $this->postJson('/submissions', array_replace($payload, [$field => $value]))->assertConflict();
        $this->assertSame('07', Submission::first()->display_number);
        $this->assertSame('MORGAN', Submission::first()->display_name);
        $this->assertDatabaseCount('submissions', 1);
        $this->assertSame(['1', '2'], Submission::first()->designs);
        $this->assertNotNull(Submission::first()->created_at);
    }
    public function test_all_sizes_and_single_design_are_accepted(): void {
        foreach (config('shirts.sizes') as $size) $this->postJson('/submissions', $this->payload(['size' => $size, 'designs' => ['2']]))->assertCreated();
        $this->assertDatabaseCount('submissions', 7);
    }
    public function test_browser_entry_can_be_created_and_edited_without_login(): void {
        $payload = $this->payload();
        $id = $this->postJson('/submissions/save', $payload)->assertCreated()->json('id');
        $changed = array_replace($payload, ['name' => 'Alex Updated', 'display_name' => 'ALEX', 'display_number' => '00', 'size' => 'XL', 'designs' => ['2']]);
        $this->postJson('/submissions/save', $changed)->assertOk()->assertJson(['id' => $id]);
        $this->postJson('/submissions/save', $changed)->assertOk()->assertJson(['id' => $id]);
        $this->assertGuest();
        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submissions', ['id' => $id, 'display_number' => '00', 'display_name' => 'ALEX', 'size' => 'XL']);
        $this->assertSame(['2'], Submission::find($id)->designs);
    }
    public function test_browser_keys_isolate_entries_and_are_not_exposed(): void {
        $first = $this->payload();
        $id = $this->postJson('/submissions/save', $first)->assertCreated()->json('id');
        $this->postJson('/submissions/save', $this->payload(['name' => 'Another person', 'id' => $id]))->assertCreated();
        $this->assertSame($first['name'], Submission::find($id)->name);
        $this->postJson('/submissions/save', array_replace($first, ['request_key' => null]))->assertUnprocessable();
        $this->postJson('/submissions/save', array_replace($first, ['display_number' => '7']))->assertUnprocessable();
        $this->assertDatabaseCount('submissions', 2);
        $response = $this->actingAs(User::factory()->create())->getJson('/admin/submissions')->assertOk();
        $this->assertStringNotContainsString($first['request_key'], $response->getContent());
        $this->assertStringNotContainsString('request_key', $response->getContent());
    }
    public function test_admin_routes_require_authentication(): void {
        $this->get('/admin')->assertRedirect('/login');
        $this->getJson('/admin/submissions')->assertUnauthorized();
        $this->getJson('/admin/export')->assertUnauthorized();
        $record = Submission::create($this->payload());
        $this->postJson('/admin/submissions/'.$record->id, $this->payload(['name' => 'Unauthorized']))->assertUnauthorized();
        $this->assertSame('Alex Morgan', $record->fresh()->name);
    }
    public function test_admin_can_edit_a_record_without_changing_its_identity(): void {
        $record = Submission::create($this->payload());
        $other = Submission::create($this->payload());
        $createdAt = $record->created_at;
        $key = $record->request_key;
        $this->actingAs(User::factory()->create());
        $changed = ['name' => 'Updated Person', 'display_name' => 'UPDATED', 'display_number' => '00', 'size' => 'XL', 'designs' => ['2']];
        $this->postJson('/admin/submissions/'.$record->id, $changed + ['request_key' => (string) Str::uuid(), 'id' => $other->id, 'created_at' => '2000-01-01'])->assertOk()->assertExactJson(['id' => $record->id]);
        $record->refresh();
        foreach ($changed as $field => $value) $this->assertSame($value, $record->$field);
        $this->assertSame($key, $record->request_key);
        $this->assertTrue($createdAt->equalTo($record->created_at));
        $this->assertSame('Alex Morgan', $other->fresh()->name);
        $this->assertDatabaseCount('submissions', 2);
        $this->getJson('/admin/submissions?size=M')->assertJsonPath('total', 1);
        $this->getJson('/admin/submissions?size=XL&design=2')->assertJsonPath('total', 1)->assertJsonPath('sizes.XL', 1)->assertJsonPath('designs.2', 1);
        $this->assertStringContainsString('Updated Person', $this->get('/admin/export?size=XL')->assertOk()->streamedContent());
    }
    public function test_admin_edits_validate_fields_and_require_an_existing_record(): void {
        $record = Submission::create($this->payload());
        $this->actingAs(User::factory()->create());
        $this->postJson('/admin/submissions/'.$record->id, [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'display_name', 'display_number', 'size', 'designs']);
        foreach ([['name' => ' '], ['display_name' => ' '], ['display_number' => '7'], ['size' => 'invalid'], ['designs' => []], ['designs' => ['3']], ['designs' => ['1', '1']]] as $invalid) {
            $this->postJson('/admin/submissions/'.$record->id, $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertSame('07', $record->fresh()->display_number);
        $this->postJson('/admin/submissions/99999', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('submissions', 1);
    }
    public function test_login_logout_and_invalid_password(): void {
        $user = User::factory()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->postJson('/logout')->assertOk();
        $this->assertGuest();
    }
    public function test_unified_login_accepts_username_and_email(): void {
        $user = User::factory()->create(['username' => 'testadmin']);
        foreach ([$user->username, $user->email] as $login) {
            $this->postJson('/login', ['login' => $login, 'password' => 'password'])->assertOk();
            $this->assertAuthenticatedAs($user);
            $this->postJson('/logout')->assertOk();
        }
        $this->postJson('/login', ['login' => 'testadmin', 'password' => 'incorrect'])->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->assertGuest();
    }
    public function test_username_only_account_can_sign_in(): void {
        $user = User::factory()->create(['username' => 'usernameonly', 'email' => null]);
        $this->postJson('/login', ['login' => $user->username, 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
    }
    public function test_filters_totals_and_csv(): void {
        Submission::create($this->payload());
        Submission::create($this->payload(['name' => '=SUM(1,2)', 'display_name' => '=2+2', 'size' => 'L', 'designs' => ['2']]));
        $this->actingAs(User::factory()->create());
        $this->getJson('/admin/submissions')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('sizes.M', 1)->assertJsonPath('designs.2', 2);
        $this->getJson('/admin/submissions?size=M&design=1')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/admin/submissions?design=1')->assertJsonPath('total', 1);
        $this->getJson('/admin/submissions?size=XS')->assertJsonPath('total', 0);
        $this->getJson('/admin/submissions?size=invalid')->assertUnprocessable();
        $csv = $this->get('/admin/export?size=L')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString('Alex Morgan', $csv);
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringContainsString(',07,', $csv);
        $this->assertStringContainsString('Design 2', $csv);
    }
    public function test_admin_can_paginate(): void {
        for ($i = 0; $i < 21; $i++) Submission::create($this->payload());
        $this->actingAs(User::factory()->create())->getJson('/admin/submissions?page=2')->assertOk()->assertJsonCount(1, 'submissions.data')->assertJsonPath('total', 21);
    }
}
