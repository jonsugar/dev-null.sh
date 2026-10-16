<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('github_repositories', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'github_repository_id']);
            $table->dropIndex(['github_app_installation_id', 'github_repository_id']);
            $table->dropConstrainedForeignId('github_app_installation_id');
            $table->unsignedBigInteger('github_repository_id')->nullable()->change();
            $table->text('webhook_secret')->nullable();
            $table->unique(['user_id', 'full_name']);
        });

        DB::table('github_repositories')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($repositories): void {
                foreach ($repositories as $repository) {
                    DB::table('github_repositories')
                        ->where('id', $repository->id)
                        ->update(['webhook_secret' => Crypt::encryptString(Str::random(64))]);
                }
            });

        Schema::dropIfExists('github_app_installations');

        Schema::table('github_webhook_deliveries', function (Blueprint $table): void {
            $table->foreignUuid('github_repository_watch_id')->nullable()->constrained('github_repositories')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('github_webhook_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('github_repository_watch_id');
        });

        Schema::create('github_app_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_installation_id')->unique();
            $table->unsignedBigInteger('github_account_id');
            $table->string('github_account_login');
            $table->timestamps();
        });

        Schema::table('github_repositories', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'full_name']);
            $table->foreignUuid('github_app_installation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_repository_id')->nullable(false)->change();
            $table->dropColumn('webhook_secret');
            $table->unique(['user_id', 'github_repository_id']);
            $table->index(['github_app_installation_id', 'github_repository_id']);
        });
    }
};
