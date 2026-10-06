<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_all_inventory_and_user_management_areas(): void
    {
        foreach ([
            '/dashboard', '/products', '/categories', '/animals', '/movements', '/users', '/profile',
        ] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_personal_role_cannot_change_inventory_or_register_animal_activity(): void
    {
        $personal = User::factory()->create(['role' => 'personal']);
        $category = Category::create(['name' => 'Insumos']);
        $product = Product::create([
            'name' => 'Alimento', 'category_id' => $category->id, 'quantity' => 10,
            'min_stock' => 1, 'unit' => 'kg',
        ]);
        $animal = Animal::create([
            'type' => 'individual', 'name' => 'Luna', 'species' => 'Bovino', 'status' => 'active',
        ]);

        $this->actingAs($personal)->post(route('categories.store'), ['name' => 'No autorizado'])->assertForbidden();
        $this->actingAs($personal)->post(route('products.movements.store', $product), [
            'type' => 'in', 'amount' => 1,
        ])->assertForbidden();
        $this->actingAs($personal)->post(route('animals.records.store', $animal), [
            'type' => 'weight', 'recorded_at' => now()->toDateString(), 'weight' => 100, 'weight_type' => 'scale',
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'No autorizado']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('animal_records', 0);
    }

    public function test_only_superadmin_can_access_user_administration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $personal = User::factory()->create(['role' => 'personal']);

        $this->actingAs($admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($personal)->get(route('users.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('users.store'), $this->userPayload())->assertForbidden();
    }

    public function test_profile_update_cannot_be_used_to_escalate_the_current_users_role(): void
    {
        $personal = User::factory()->create(['role' => 'personal']);

        $this->actingAs($personal)->patch(route('profile.update'), [
            'name' => 'Usuario personal',
            'email' => $personal->email,
            'role' => 'superadmin',
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('personal', $personal->fresh()->role);
    }

    /** @return array<string, string> */
    private function userPayload(): array
    {
        return [
            'name' => 'Cuenta no autorizada',
            'email' => 'blocked@example.test',
            'password' => 'password123',
            'role' => 'superadmin',
        ];
    }
}
