<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RpsDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owner-only access. Non-owners get 404 so document existence is not leaked
 * (docs/API.md §1).
 */
class RpsDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, RpsDocument $document): Response
    {
        return $this->ownerOnly($user, $document);
    }

    public function update(User $user, RpsDocument $document): Response
    {
        return $this->ownerOnly($user, $document);
    }

    public function delete(User $user, RpsDocument $document): Response
    {
        return $this->ownerOnly($user, $document);
    }

    private function ownerOnly(User $user, RpsDocument $document): Response
    {
        return $document->isOwnedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
