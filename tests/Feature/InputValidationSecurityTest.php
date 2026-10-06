<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputValidationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_rejects_invalid_foreign_keys_and_negative_stock_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('products.create'))->post(route('products.store'), [
            'name' => 'Producto inválido',
            'category_id' => 999999,
            'quantity' => -1,
            'min_stock' => -1,
            'unit' => 'kg',
        ])->assertRedirect(route('products.create'))
            ->assertSessionHasErrors(['category_id', 'quantity', 'min_stock']);

        $this->assertDatabaseMissing('products', ['name' => 'Producto inválido']);
    }

    public function test_product_creation_rejects_a_non_file_value_for_the_photo_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('products.create'))->post(route('products.store'), [
            'name' => 'Archivo malicioso',
            'quantity' => 0,
            'min_stock' => 0,
            'unit' => 'unidad',
            'photo' => 'payload.php',
        ])->assertRedirect(route('products.create'))
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('products', ['name' => 'Archivo malicioso']);
    }

    public function test_stock_cannot_be_reduced_below_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Vacuna', 'quantity' => 2, 'min_stock' => 0, 'unit' => 'dosis',
        ]);

        $this->actingAs($admin)->from(route('products.movements.index', $product))
            ->post(route('products.movements.store', $product), [
                'type' => 'out', 'amount' => 3, 'reason' => 'Intento de inventario negativo',
            ])->assertRedirect(route('products.movements.index', $product))
            ->assertSessionHasErrors('amount');

        $this->assertSame(2.0, (float) $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_lot_sale_cannot_exceed_available_animals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = Animal::create([
            'type' => 'lot', 'name' => 'Lote A', 'species' => 'Bovino', 'quantity' => 3,
            'initial_quantity' => 3, 'status' => 'active',
        ]);

        $this->actingAs($admin)->from(route('animals.show', $lot))
            ->post(route('animals.records.store', $lot), [
                'type' => 'sale', 'recorded_at' => now()->toDateString(), 'heads' => 4,
                'amount' => 400, 'payment_status' => 'paid',
            ])->assertRedirect(route('animals.show', $lot))
            ->assertSessionHas('error');

        $this->assertSame(3, $lot->fresh()->quantity);
        $this->assertDatabaseCount('animal_records', 0);
    }

    public function test_category_names_are_html_escaped_when_rendered(): void
    {
        $user = User::factory()->create(['role' => 'personal']);
        $payload = '<script>alert("xss")</script>';
        Category::create(['name' => $payload]);

        $this->actingAs($user)->get(route('categories.index'))
            ->assertOk()
            ->assertSee($payload)
            ->assertDontSee($payload, false);
    }
}
