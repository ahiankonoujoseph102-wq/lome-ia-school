<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Accès à une leçon et à ses ressources : élève ayant accès à la formation (même provisoire),
 * admin, ou tout visiteur pour une leçon d'aperçu gratuite publiée.
 */
class LessonPolicy
{
    public function view(?User $user, Lesson $lesson): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }

        if (! $lesson->is_published) {
            return false;
        }

        return $lesson->is_preview || ($user !== null && $user->isEnrolledIn($lesson->module->formation));
    }

    /**
     * Suivi de progression : réservé aux élèves inscrits à la formation.
     */
    public function track(User $user, Lesson $lesson): bool
    {
        return $lesson->is_published && $user->isEnrolledIn($lesson->module->formation);
    }
}
