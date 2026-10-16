<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateGitHubRepositoryRequest extends FormRequest
{
    public function authorize(): Response
    {
        return $this->user() instanceof User
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'full_name' => [
                'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/',
                Rule::unique('github_repositories', 'full_name')->where('user_id', $user->id),
            ],
            'branches' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'branches.*' => ['required', 'string', 'min:1', 'max:255', 'distinct'],
        ];
    }

    public function fullName(): string
    {
        return mb_strtolower(mb_trim((string) $this->validated('full_name')));
    }

    /** @return list<string> */
    public function branches(): array
    {
        /** @var list<string> $branches */
        $branches = $this->validated('branches');

        return array_values(array_unique(array_map(mb_trim(...), $branches)));
    }
}
