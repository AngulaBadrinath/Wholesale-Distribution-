<?php

namespace Tests\Feature\Tax;

use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TaxProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function createSalesman(): User
    {
        return User::factory()->salesman()->create();
    }

    // ==========================================
    // 1. TAX PROFILE CREATION
    // ==========================================

    public function test_admin_can_create_tax_profile(): void
    {
        $admin = $this->createAdmin();

        Log::shouldReceive('info')
            ->once()
            ->with('Tax profile master record created', \Mockery::on(function (array $payload) use ($admin) {
                return $payload['action'] === 'TAX_PROFILE_CREATED'
                    && $payload['actor_id'] === $admin->id
                    && $payload['code'] === 'TAX-STD-600'
                    && $payload['rate'] === '6.0000';
            }));

        $response = $this->actingAs($admin)->post('/tax-profiles', [
            'name' => 'State Sales Tax 6%',
            'code' => 'tax-std-600',
            'rate' => '6.00',
            'description' => 'Standard statewide retail sales tax',
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect('/tax-profiles');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_profiles', [
            'name' => 'State Sales Tax 6%',
            'code' => 'TAX-STD-600',
            'rate' => '6.0000',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $admin = $this->createAdmin();

        TaxProfile::create([
            'name' => 'Existing Tax',
            'code' => 'TAX-DUP',
            'rate' => '5.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)->post('/tax-profiles', [
            'name' => 'Another Tax',
            'code' => 'TAX-DUP',
            'rate' => '7.0000',
            'status' => 'ACTIVE',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_create_rejects_invalid_rate_ranges_and_scale(): void
    {
        $admin = $this->createAdmin();

        // Negative rate
        $responseNeg = $this->actingAs($admin)->post('/tax-profiles', [
            'name' => 'Negative Tax',
            'code' => 'TAX-NEG',
            'rate' => '-2.5',
            'status' => 'ACTIVE',
        ]);
        $responseNeg->assertSessionHasErrors(['rate']);

        // Rate > 100%
        $responseHigh = $this->actingAs($admin)->post('/tax-profiles', [
            'name' => 'High Tax',
            'code' => 'TAX-HIGH',
            'rate' => '105.00',
            'status' => 'ACTIVE',
        ]);
        $responseHigh->assertSessionHasErrors(['rate']);

        // Rate with > 4 decimal places
        $responseScale = $this->actingAs($admin)->post('/tax-profiles', [
            'name' => 'Precision Tax',
            'code' => 'TAX-PREC',
            'rate' => '6.12345',
            'status' => 'ACTIVE',
        ]);
        $responseScale->assertSessionHasErrors(['rate']);
    }

    // ==========================================
    // 2. TAX PROFILE UPDATE & LIFECYCLE
    // ==========================================

    public function test_admin_can_update_tax_profile(): void
    {
        $admin = $this->createAdmin();

        $profile = TaxProfile::create([
            'name' => 'Original Name',
            'code' => 'TAX-ORIG',
            'rate' => '5.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Tax profile master record updated', \Mockery::on(function (array $payload) use ($profile) {
                return $payload['action'] === 'TAX_PROFILE_UPDATED'
                    && $payload['tax_profile_id'] === $profile->id
                    && $payload['previous_values']['rate'] === '5.0000'
                    && $payload['new_values']['rate'] === '5.5000';
            }));

        $response = $this->actingAs($admin)->put("/tax-profiles/{$profile->id}", [
            'name' => 'Revised Tax Name',
            'code' => 'TAX-ORIG', // same code
            'rate' => '5.5',
            'description' => 'Updated rate',
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect('/tax-profiles');

        $profile->refresh();
        $this->assertSame('Revised Tax Name', $profile->name);
        $this->assertSame('5.5000', (string) $profile->rate);
    }

    public function test_admin_can_deactivate_tax_profile(): void
    {
        $admin = $this->createAdmin();

        $profile = TaxProfile::create([
            'name' => 'Active Profile',
            'code' => 'TAX-DEACT',
            'rate' => '7.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        Log::shouldReceive('info')->once();

        $response = $this->actingAs($admin)->put("/tax-profiles/{$profile->id}", [
            'name' => 'Active Profile',
            'code' => 'TAX-DEACT',
            'rate' => '7.0000',
            'status' => 'INACTIVE',
        ]);

        $response->assertRedirect('/tax-profiles');

        $profile->refresh();
        $this->assertSame(TaxProfileStatus::INACTIVE, $profile->status);
    }

    // ==========================================
    // 3. DELETION PROTECTION
    // ==========================================

    public function test_admin_can_delete_unreferenced_tax_profile(): void
    {
        $admin = $this->createAdmin();

        $profile = TaxProfile::create([
            'name' => 'Unused Tax Profile',
            'code' => 'TAX-UNUSED',
            'rate' => '4.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Tax profile master record deleted', \Mockery::on(function (array $payload) use ($profile) {
                return $payload['action'] === 'TAX_PROFILE_DELETED'
                    && $payload['tax_profile_id'] === $profile->id;
            }));

        $response = $this->actingAs($admin)->delete("/tax-profiles/{$profile->id}");

        $response->assertRedirect('/tax-profiles');
        $this->assertDatabaseMissing('tax_profiles', ['id' => $profile->id]);
    }

    public function test_deleting_referenced_tax_profile_is_blocked(): void
    {
        $admin = $this->createAdmin();

        $profile = TaxProfile::create([
            'name' => 'In-Use Tax Profile',
            'code' => 'TAX-INUSE',
            'rate' => '6.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $category = Category::create([
            'name' => 'Tools',
            'code' => 'TOOLS',
            'status' => 'ACTIVE',
        ]);

        Product::create([
            'sku' => 'TOOL-100',
            'name' => 'Hammer',
            'category_id' => $category->id,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
            'tax_profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($admin)->delete("/tax-profiles/{$profile->id}");

        $response->assertSessionHasErrors(['tax_profile']);
        $this->assertDatabaseHas('tax_profiles', ['id' => $profile->id]);
    }

    // ==========================================
    // 4. RBAC & SECURITY
    // ==========================================

    public function test_salesman_cannot_access_tax_profile_management(): void
    {
        $salesman = $this->createSalesman();

        // Index
        $this->actingAs($salesman)->get('/tax-profiles')->assertForbidden();

        // Create
        $this->actingAs($salesman)->post('/tax-profiles', [
            'name' => 'Hacked Tax',
            'code' => 'TAX-HACK',
            'rate' => '1.0000',
            'status' => 'ACTIVE',
        ])->assertForbidden();
    }
}
