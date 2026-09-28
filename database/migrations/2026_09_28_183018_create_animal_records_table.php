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
        Schema::create('animal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // feeding | vaccine | weight | treatment
            $table->date('recorded_at');
            $table->string('title')->nullable();             // nombre de la vacuna, diagnóstico...
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('product_quantity')->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight', 8, 2)->nullable();
            $table->date('next_due_date')->nullable();       // próxima dosis o control
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animal_records');
    }
};
