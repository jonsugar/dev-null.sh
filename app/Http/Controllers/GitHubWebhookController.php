<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateGitHubWebhookRequest;
use App\Jobs\ProcessGitHubPush;
use App\Models\GitHubRepository;
use App\Models\GitHubWebhookDelivery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response;

final class GitHubWebhookController
{
    public function __invoke(CreateGitHubWebhookRequest $request, GitHubRepository $repository): Response
    {
        if ($request->event() !== 'push') {
            return response('', 202);
        }

        try {
            $delivery = GitHubWebhookDelivery::query()->create([
                'delivery_id' => $request->deliveryId(),
                'event' => $request->event(),
                'payload' => $request->payload(),
                'github_repository_watch_id' => $repository->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('', 202);
        }

        dispatch(new ProcessGitHubPush($delivery->delivery_id))->afterCommit();

        return response('', 202);
    }
}
