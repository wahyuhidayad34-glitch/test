<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchasingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $finance;
    private User $director;
    private User $requester;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->role('manager')->create();
        $this->finance = User::factory()->role('finance', 'Finance')->create();
        $this->director = User::factory()->role('director', 'Management')->create();
        $this->requester = User::factory()->role('requester')->create(['manager_id' => $this->manager->id]);
        $this->stranger = User::factory()->role('requester', 'HR')->create();
    }

    private function createPr(float $unitPrice): int
    {
        Sanctum::actingAs($this->requester);

        $res = $this->postJson('/api/purchase-requests', [
            'department' => 'IT',
            'required_date' => now()->addWeek()->toDateString(),
            'description' => 'Pengadaan uji coba',
            'items' => [[
                'item_name' => 'Laptop',
                'quantity' => 2,
                'unit' => 'unit',
                'unit_price' => $unitPrice,
            ]],
        ]);
        $res->assertCreated();

        return $res->json('data.id');
    }

    private function submitPr(float $unitPrice): int
    {
        $id = $this->createPr($unitPrice);
        Sanctum::actingAs($this->requester);
        $this->postJson("/api/purchase-requests/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        return $id;
    }

    public function test_login_and_me_with_real_token(): void
    {
        $res = $this->postJson('/api/login', ['email' => $this->manager->email, 'password' => 'password']);
        $res->assertOk()->assertJsonPath('user.role', 'manager');

        $this->withToken($res->json('token'))->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.email', $this->manager->email);
    }

    public function test_unauthenticated_request_gets_json_401(): void
    {
        $this->getJson('/api/purchase-requests')->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_small_amount_needs_manager_only(): void
    {
        $id = $this->submitPr(5_000_000); // total 10 jt

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_large_amount_requires_sequential_manager_finance_director(): void
    {
        $id = $this->submitPr(300_000_000); // total 600 jt -> 3 level

        // Belum giliran Finance / Director
        Sanctum::actingAs($this->finance);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertStatus(403);
        Sanctum::actingAs($this->director);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertStatus(403);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved_manager');

        Sanctum::actingAs($this->director);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertStatus(403); // finance belum

        Sanctum::actingAs($this->finance);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved_finance');

        Sanctum::actingAs($this->director);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $id = $this->submitPr(1_000_000);

        Sanctum::actingAs($this->requester);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertStatus(403);
    }

    public function test_reject_then_edit_and_resubmit(): void
    {
        $id = $this->submitPr(1_000_000);

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/purchase-requests/{$id}/reject", ['reason' => 'Harga terlalu tinggi, mohon revisi'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        Sanctum::actingAs($this->requester);
        $this->putJson("/api/purchase-requests/{$id}", ['description' => 'Sudah direvisi'])
            ->assertOk()->assertJsonPath('data.status', 'draft');
        $this->postJson("/api/purchase-requests/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        Sanctum::actingAs($this->manager);
        $this->postJson("/api/purchase-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_other_users_cannot_view_or_delete_a_draft(): void
    {
        $id = $this->createPr(1_000_000);

        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/purchase-requests/{$id}")->assertStatus(403);
        $this->deleteJson("/api/purchase-requests/{$id}")->assertStatus(403);
        $this->getJson('/api/purchase-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_update_validation_returns_422_not_500(): void
    {
        $id = $this->createPr(1_000_000);

        Sanctum::actingAs($this->requester);
        $this->putJson("/api/purchase-requests/{$id}", [
            'items' => [['item_name' => 'X', 'quantity' => 0, 'unit' => 'pcs', 'unit_price' => 10]],
        ])->assertStatus(422);
    }

    public function test_pr_numbers_stay_unique_after_deleting_a_draft(): void
    {
        $first = $this->createPr(1_000_000);
        $second = $this->createPr(1_000_000);

        Sanctum::actingAs($this->requester);
        $this->deleteJson("/api/purchase-requests/{$first}")->assertOk();

        $third = $this->createPr(1_000_000); // dulu bentrok karena count()+1
        $this->assertNotEquals($second, $third);
    }

    public function test_dashboard_and_pending_lists_only_show_actionable_items(): void
    {
        $this->submitPr(300_000_000); // 600 jt

        Sanctum::actingAs($this->finance);   // belum giliran finance
        $this->getJson('/api/approvals/pending')->assertOk()->assertJsonPath('count', 0);

        Sanctum::actingAs($this->manager);
        $this->getJson('/api/approvals/pending')->assertOk()->assertJsonPath('count', 1);
        $this->getJson('/api/dashboard/metrics')->assertOk()->assertJsonPath('data.my_pending_approvals', 1);
    }
}
