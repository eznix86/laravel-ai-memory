<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Embedder;
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
        Schema::ensureVectorExtensionExists();

        Schema::create(config('memory.table'), function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('kind');
            $table->string('subject')->nullable();
            $table->text('content');
            $table->float('importance')->default(0.5);
            $table->string('source');
            $table->string('creator_type')->nullable();
            $table->string('creator_id')->nullable();
            $table->unsignedInteger('recall_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained(config('memory.table'))->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->vector('embedding', dimensions: app(Embedder::class)->dimensions())->nullable();
            $table->timestamps();

            $table->vectorIndex('embedding');
            $table->fullText('content')->language(config('memory.fulltext_language'));
            $table->index(['owner_type', 'owner_id', 'user_type', 'user_id', 'kind']);
            $table->index(['owner_type', 'owner_id', 'subject']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('memory.table'));
    }
};
