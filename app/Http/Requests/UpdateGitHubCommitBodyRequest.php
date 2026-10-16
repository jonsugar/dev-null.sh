<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateGitHubCommitBodyRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        $commit = $this->route('commit');

        return $user instanceof User && $commit instanceof Commit
            && $commit->user_id === $user->id
            && $commit->status === CommitStatus::Pending
            && $commit->source_url !== null
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
        ];
    }
}
