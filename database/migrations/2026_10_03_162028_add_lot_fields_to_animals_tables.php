<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->unsignedInteger('initial_quantity')->nullable();
            $table->date('entry_date')->nullable();
            $table->decimal('initial_weight', 8, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
        });

        Schema::table('animal_records', function (Blueprint $table) {
            $table->unsignedInteger('heads')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
        });

        // Los lotes que ya existen toman su cantidad actual como cantidad inicial
        DB::table('animals')->where('type', 'lot')->update(['initial_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn(['initial_quantity', 'entry_date', 'initial_weight', 'supplier', 'purchase_cost']);
        });

        Schema::table('animal_records', function (Blueprint $table) {
            $table->dropColumn(['heads', 'amount']);
        });
    }
};
