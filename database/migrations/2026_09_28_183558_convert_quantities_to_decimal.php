<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->unsigned()->default(0)->change();
            $table->decimal('min_stock', 14, 3)->unsigned()->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('change', 14, 3)->change();
            $table->decimal('quantity_after', 14, 3)->unsigned()->change();
        });

        Schema::table('animal_records', function (Blueprint $table) {
            $table->decimal('product_quantity', 14, 3)->unsigned()->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
            $table->unsignedInteger('min_stock')->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('change')->change();
            $table->unsignedInteger('quantity_after')->change();
        });

        Schema::table('animal_records', function (Blueprint $table) {
            $table->unsignedInteger('product_quantity')->nullable()->change();
        });
    }
};
