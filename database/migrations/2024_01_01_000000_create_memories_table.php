<?php

declare(strict_types=1);

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
        $supportsNativeVectors = Schema::getConnection()->getDriverName() !== 'sqlite';

        if ($supportsNativeVectors) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('memories', function (Blueprint $table) use ($supportsNativeVectors) {
            $table->id();
            $table->string('user_id')->nullable()->index();
            $table->text('content');

            if (! $supportsNativeVectors) {
                $table->json('embedding');
            } else {
                $table->vector('embedding', dimensions: config('memory.dimensions'))->index();
            }

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
