<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Curated postings (created_by = null): readable by every logged-in user,
 * never modifiable via the API (docs/SECURITY.md). Manual postings: owner only;
 * others get 404 so existence is not leaked.
 */
class JobPostingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, JobPosting $posting): Response
    {
        return $posting->isCurated() || $posting->created_by === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, JobPosting $posting): Response
    {
        return $this->ownManualOnly($user, $posting);
    }

    public function delete(User $user, JobPosting $posting): Response
    {
        return $this->ownManualOnly($user, $posting);
    }

    private function ownManualOnly(User $user, JobPosting $posting): Response
    {
        if ($posting->isCurated()) {
            return Response::deny('Lowongan dari dataset terkurasi tidak dapat diubah.');
        }

        return $posting->created_by === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
