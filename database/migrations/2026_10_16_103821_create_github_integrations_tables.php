<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('github_posts_require_approval')->default(true);
        });

        Schema::create('github_app_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_installation_id')->unique();
            $table->unsignedBigInteger('github_account_id');
            $table->string('github_account_login');
            $table->timestamps();
        });

        Schema::create('github_repositories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('github_app_installation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_repository_id');
            $table->string('full_name');
            $table->json('branches');
            $table->timestamps();

            $table->unique(['user_id', 'github_repository_id']);
            $table->index(['github_app_installation_id', 'github_repository_id']);
        });

        Schema::create('github_webhook_deliveries', function (Blueprint $table): void {
            $table->string('delivery_id')->primary();
            $table->string('event');
            $table->longText('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('commits', function (Blueprint $table): void {
            $table->string('source_url')->nullable();
            $table->string('github_delivery_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->dropUnique(['github_delivery_id']);
            $table->dropColumn(['source_url', 'github_delivery_id']);
        });

        Schema::dropIfExists('github_webhook_deliveries');
        Schema::dropIfExists('github_repositories');
        Schema::dropIfExists('github_app_installations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('github_posts_require_approval');
        });
    }
};
