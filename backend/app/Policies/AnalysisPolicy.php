<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owner-only access; non-owners get 404 (docs/API.md §1).
 */
class AnalysisPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Analysis $analysis): Response
    {
        return $this->ownerOnly($user, $analysis);
    }

    public function update(User $user, Analysis $analysis): Response
    {
        return $this->ownerOnly($user, $analysis);
    }

    public function delete(User $user, Analysis $analysis): Response
    {
        return $this->ownerOnly($user, $analysis);
    }

    private function ownerOnly(User $user, Analysis $analysis): Response
    {
        return $analysis->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
