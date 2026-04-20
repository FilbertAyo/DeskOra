<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * All authenticated users may open the documents catalogue; the controller lists
     * submitted items organization-wide and scopes returned drafts appropriately.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * Rules:
     * - Submitted documents: Any authenticated user may view (org-wide transparency).
     * - Drafts: Owner, weekly collaborators, reviewers/admins, or action-log assignees/managers as below.
     */
    public function view(User $user, Document $document): bool
    {
        if ($document->isSubmitted()) {
            return true;
        }

        if ($document->type === Document::TYPE_ACTION_LOG) {
            if ($user->hasPermissionTo('view all documents') || $this->isActionLogManager($user) || $document->user_id === $user->id) {
                return true;
            }

            return $this->isUserAssignedToActionLog($user, $document);
        }

        // User owns the document
        if ($document->user_id === $user->id) {
            return true;
        }

        if ($this->isSharedWeeklyMember($user, $document)) {
            return true;
        }

        // Users with 'view all documents' permission can view all documents
        if ($user->hasPermissionTo('view all documents')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * Rules:
     * - Employees: Can create weekly_plan, weekly_report, monthly_report
     * - Reviewers: Cannot create (read-only)
     * - Admin: Can create any type
     */
    public function create(User $user): bool
    {
        // Admin can create any type
        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        // Users who only have 'view all documents' permission cannot create (read-only)
        if ($user->hasPermissionTo('view all documents') && ! $user->hasAnyPermission(['create weekly plan', 'create weekly report', 'create monthly report', 'create action logs'])) {
            return false;
        }

        // Check if user has any available document types
        $availableTypes = Document::getAvailableTypesForUser($user);

        return ! empty($availableTypes);
    }

    /**
     * Determine whether the user can create a specific document type.
     */
    public function createType(User $user, string $type): bool
    {
        // Admin can create any type
        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        // Users who only have 'view all documents' permission cannot create (read-only)
        if ($user->hasPermissionTo('view all documents') && ! $user->hasAnyPermission(['create weekly plan', 'create weekly report', 'create monthly report', 'create action logs'])) {
            return false;
        }

        // Check if user can create this specific type
        return Document::canUserCreateType($user, $type);
    }

    /**
     * Determine whether the user can update the model.
     *
     * Rules:
     * - Employees: Can update their own draft documents
     * - Reviewers: Cannot update (read-only)
     * - Admin: Can update any draft document
     */
    public function update(User $user, Document $document): bool
    {
        if ($document->type === Document::TYPE_ACTION_LOG) {
            if (! $this->hasOpenPeriod($document)) {
                return false;
            }

            if ($this->isActionLogManager($user)) {
                // Managers can keep updating shared weekly logs while period remains open.
                return true;
            }

            // Assigned staff can update their own rows even after submission.
            return $this->isUserAssignedToActionLog($user, $document);
        }

        // Users who only have 'view all documents' permission cannot update (read-only)
        if ($user->hasPermissionTo('view all documents') && ! $user->hasAnyPermission(['create weekly plan', 'create weekly report', 'create monthly report', 'create action logs', 'approve documents'])) {
            return false;
        }

        // Only draft documents can be updated
        if (! $document->isDraft()) {
            return false;
        }

        if (! $this->hasOpenPeriod($document)) {
            return false;
        }

        if ($document->user_id === $user->id) {
            return true;
        }

        if ($this->isSharedWeeklyMember($user, $document)) {
            return true;
        }

        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Rules:
     * - Employees: Can delete their own draft documents
     * - Reviewers: Cannot delete (read-only)
     * - Admin: Can delete any draft document
     */
    public function delete(User $user, Document $document): bool
    {
        if ($document->type === Document::TYPE_ACTION_LOG) {
            if (! $document->isDraft() || ! $this->hasOpenPeriod($document)) {
                return false;
            }

            return $this->isActionLogManager($user);
        }

        // Users who only have 'view all documents' permission cannot delete (read-only)
        if ($user->hasPermissionTo('view all documents') && ! $user->hasAnyPermission(['create weekly plan', 'create weekly report', 'create monthly report', 'create action logs', 'approve documents'])) {
            return false;
        }

        // Only draft documents can be deleted
        if (! $document->isDraft()) {
            return false;
        }

        if (! $this->hasOpenPeriod($document)) {
            return false;
        }

        if ($document->user_id === $user->id) {
            return true;
        }

        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can submit the document.
     *
     * Rules:
     * - Employees: Can submit their own draft documents
     * - Reviewers: Cannot submit (read-only)
     * - Admin: Can submit any draft document
     */
    public function submit(User $user, Document $document): bool
    {
        if ($document->type === Document::TYPE_ACTION_LOG) {
            if (! $document->isDraft() || ! $this->hasOpenPeriod($document)) {
                return false;
            }

            return $this->isActionLogManager($user);
        }

        // Users who only have 'view all documents' permission cannot submit (read-only)
        if ($user->hasPermissionTo('view all documents') && ! $user->hasAnyPermission(['create weekly plan', 'create weekly report', 'create monthly report', 'create action logs', 'approve documents'])) {
            return false;
        }

        // Only draft documents can be submitted
        if (! $document->isDraft()) {
            return false;
        }

        if (! $this->hasOpenPeriod($document)) {
            return false;
        }

        if ($document->user_id === $user->id) {
            return true;
        }

        if ($this->isSharedWeeklyMember($user, $document)) {
            return true;
        }

        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether reviewer/admin can return a submitted weekly document to draft.
     */
    public function reject(User $user, Document $document): bool
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return false;
        }

        if (! $document->isSubmitted()) {
            return false;
        }

        if (! $this->hasOpenPeriod($document)) {
            return false;
        }

        return $user->hasAnyPermission(['view all documents', 'approve documents']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Document $document): bool
    {
        // Only users with 'approve documents' permission can restore deleted documents
        return $user->hasPermissionTo('approve documents');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        // Only users with 'approve documents' permission can permanently delete documents
        return $user->hasPermissionTo('approve documents');
    }

    /**
     * Ensure documents tied to work periods can only be modified when the period is open.
     */
    private function hasOpenPeriod(Document $document): bool
    {
        if (! $document->period) {
            return true;
        }

        if (in_array($document->type, [
            Document::TYPE_WEEKLY_PLAN,
            Document::TYPE_WEEKLY_REPORT,
            Document::TYPE_MONTHLY_REPORT,
            Document::TYPE_ACTION_LOG,
        ])) {
            return $document->period->isOpen();
        }

        return true;
    }

    private function isActionLogManager(User $user): bool
    {
        return $user->hasAnyPermission(['create action logs', 'approve documents']);
    }

    private function isUserAssignedToActionLog(User $user, Document $document): bool
    {
        $content = is_array($document->content) ? $document->content : [];
        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (! is_array($responsibleIds)) {
                continue;
            }

            $ids = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
            if (in_array((string) $user->id, $ids, true)) {
                return true;
            }
        }

        return false;
    }

    private function isSharedWeeklyMember(User $user, Document $document): bool
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return false;
        }

        return $document->isSharedWith($user->id);
    }
}
