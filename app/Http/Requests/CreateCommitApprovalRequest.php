<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitApprovalRequest extends FormRequest
{
    public function authorize(): bool|Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');

        if ($user->isAdmin()) {
            return true;
        }

        if ($commit instanceof Commit && $commit->source_url !== null) {
            return $commit->user_id === $user->id && $commit->status === CommitStatus::Pending
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['body' => ['sometimes', 'string', 'min:3', 'max:280']];
    }
}
