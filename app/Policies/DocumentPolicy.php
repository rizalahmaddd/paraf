<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Documents are private to their owner; Superadmin passes via Gate::before.
     */
    public function manage(User $user, Document $document): bool
    {
        return $user->id === $document->user_id && $user->can('documents.manage');
    }
}
