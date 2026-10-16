<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\GitHubRepository;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateGitHubRepositoryRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        $repository = $this->route('repository');

        return $user instanceof User && $repository instanceof GitHubRepository && $repository->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function rules(): array
    {
        return [
            'branches' => ['present', 'array', 'list', 'max:100'],
            'branches.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function branches(): array
    {
        /** @var list<string> $branches */
        $branches = $this->validated('branches');

        return array_values(array_unique(array_map(mb_trim(...), $branches)));
    }
}
