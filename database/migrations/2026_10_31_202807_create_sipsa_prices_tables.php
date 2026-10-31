<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sipsa_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reg_id')->unique();
            $table->string('city', 80);
            $table->unsignedSmallInteger('product_code');
            $table->string('product', 120);
            $table->decimal('price', 12, 2);
            $table->date('captured_on');
            $table->timestamps();

            $table->index(['city', 'captured_on']);
            $table->index(['product_code', 'city', 'captured_on']);
        });

        Schema::create('sipsa_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source')->nullable();
            $table->unsignedInteger('records_read')->default(0);
            $table->unsignedInteger('records_new')->default(0);
            $table->date('first_date')->nullable();
            $table->date('last_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sipsa_imports');
        Schema::dropIfExists('sipsa_prices');
    }
};
