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
        Schema::create('server_readings', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->unsignedBigInteger('server_id');
            $table->decimal('cpu_percent', 5, 1);
            $table->decimal('memory_percent', 5, 1);
            $table->decimal('disk_percent', 5, 1);
            $table->decimal('swap_percent', 5, 1)->nullable();
            $table->decimal('load_1', 8, 2);
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['source', 'server_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_readings');
    }
};
