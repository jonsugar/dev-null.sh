<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\GitHubRepository;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteGitHubRepositoryRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        $repository = $this->route('repository');

        return $user instanceof User && $repository instanceof GitHubRepository && $repository->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
