<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GitHubRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read int|null $github_repository_id
 * @property-read string $full_name
 * @property-read list<string> $branches
 * @property-read string|null $webhook_secret
 */
#[Hidden(['github_repository_id', 'webhook_secret'])]
#[Table(name: 'github_repositories')]
final class GitHubRepository extends Model
{
    /** @use HasFactory<GitHubRepositoryFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'github_repository_id' => 'integer',
            'full_name' => 'string',
            'branches' => 'array',
            'webhook_secret' => 'encrypted',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
