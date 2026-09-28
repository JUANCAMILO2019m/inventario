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
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('individual');   // individual | lot
            $table->string('name');
            $table->string('code')->nullable()->unique();    // arete, chapa, número de lote
            $table->string('species');
            $table->string('breed')->nullable();
            $table->string('sex')->nullable();               // male | female | mixed
            $table->date('birth_date')->nullable();
            $table->unsignedInteger('quantity')->default(1); // cabezas (1 si es individual)
            $table->string('status')->default('active');     // active | sold | dead
            $table->string('photo')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
