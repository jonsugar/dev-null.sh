<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\GitHubRepository;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class CreateGitHubWebhookRequest extends FormRequest
{
    public function authorize(): Response
    {
        $repository = $this->route('repository');
        $secret = $repository instanceof GitHubRepository ? $repository->webhook_secret : null;
        $signature = $this->header('X-Hub-Signature-256');
        $deliveryId = $this->header('X-GitHub-Delivery');
        $event = $this->header('X-GitHub-Event');

        if (! is_string($secret) || $secret === '' || ! is_string($signature) || ! is_string($deliveryId)
            || ! Str::isUuid($deliveryId) || ! is_string($event) || $event === '') {
            return Response::denyWithStatus(401, 'Invalid webhook.');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $this->getContent(), $secret);

        return hash_equals($expectedSignature, $signature)
            ? Response::allow()
            : Response::denyWithStatus(401, 'Invalid webhook.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->event() !== 'push') {
            return [];
        }

        return [
            'repository.id' => ['required', 'integer'],
            'repository.full_name' => ['required', 'string', 'max:255'],
            'sender.id' => ['required', 'integer'],
            'ref' => ['required', 'string'],
            'commits' => ['sometimes', 'array'],
            'commits.*.message' => ['sometimes', 'string'],
            'before' => ['sometimes', 'string'],
            'after' => ['sometimes', 'string'],
            'deleted' => ['sometimes', 'boolean'],
        ];
    }

    public function deliveryId(): string
    {
        return (string) $this->header('X-GitHub-Delivery');
    }

    public function event(): string
    {
        return (string) $this->header('X-GitHub-Event');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->json()->all();
    }
}
