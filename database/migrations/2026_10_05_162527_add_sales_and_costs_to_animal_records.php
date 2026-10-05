<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_records', function (Blueprint $table) {
            $table->string('weight_type')->nullable();            // scale | estimated
            $table->decimal('total_weight', 10, 2)->nullable();   // kg totales vendidos
            $table->string('price_mode')->nullable();             // per_kg | per_head
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->string('payment_status')->nullable();         // paid | pending | partial
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();      // costo unitario del insumo al consumirlo
        });

        // Ventas anteriores: se asumen cobradas
        DB::table('animal_records')->where('type', 'sale')->update([
            'payment_status' => 'paid',
            'amount_paid'    => DB::raw('COALESCE(amount, 0)'),
        ]);
        
        // Consumos anteriores: costo aproximado con el precio actual del producto
        DB::table('animal_records')->whereNotNull('product_id')->orderBy('id')->each(function ($r) {
            $price = DB::table('products')->where('id', $r->product_id)->value('price');
            DB::table('animal_records')->where('id', $r->id)->update(['unit_cost' => $price]);
        });
    }

    public function down(): void
    {
        Schema::table('animal_records', function (Blueprint $table) {
            $table->dropColumn([
                'weight_type', 'total_weight', 'price_mode', 'unit_price',
                'payment_status', 'amount_paid', 'unit_cost',
            ]);
        });
    }
};
