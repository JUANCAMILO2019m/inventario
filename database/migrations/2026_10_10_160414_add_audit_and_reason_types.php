<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason_type', 30)->nullable()->index();
            $table->decimal('unit_cost', 14, 4)->nullable();
        });

        Schema::table('animal_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('event', 20);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('label')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });

        // Clasificación de los movimientos que ya existían, según su motivo
        DB::table('stock_movements')->where('reason', 'Stock inicial')->update(['reason_type' => 'initial']);
        DB::table('stock_movements')->where('reason', 'Ajuste desde edición')->update(['reason_type' => 'correction']);
        DB::table('stock_movements')->where('reason', 'like', 'Reverso por eliminacion%')->update(['reason_type' => 'reversal']);
        DB::table('stock_movements')
            ->where('type', 'out')
            ->where(function ($q) {
                $q->where('reason', 'like', 'Alimentación - %')
                    ->orWhere('reason', 'like', 'Vacuna/Desparasitación - %')
                    ->orWhere('reason', 'like', 'Uso veterinario - %');
            })
            ->update(['reason_type' => 'animal_use']);

        // Costo aproximado (precio actual del producto) para valorar movimientos anteriores
        DB::table('stock_movements')->orderBy('id')->each(function ($m) {
            $price = DB::table('products')->where('id', $m->product_id)->value('price');

            if ($price !== null) {
                DB::table('stock_movements')->where('id', $m->id)->update(['unit_cost' => $price]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('animal_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['reason_type']);
            $table->dropColumn(['reason_type', 'unit_cost']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};