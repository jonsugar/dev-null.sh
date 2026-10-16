<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GitHubWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'github_webhook_deliveries', key: 'delivery_id')]
#[WithoutIncrementing]
final class GitHubWebhookDelivery extends Model
{
    /** @use HasFactory<GitHubWebhookDeliveryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'delivery_id' => 'string',
            'event' => 'string',
            'payload' => 'array',
            'github_repository_watch_id' => 'string',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GitHubRepository, $this> */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'github_repository_watch_id');
    }
}
