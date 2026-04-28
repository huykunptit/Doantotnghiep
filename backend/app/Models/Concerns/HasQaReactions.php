<?php

namespace App\Models\Concerns;

use App\Models\QaReaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Mix-in for any model (CourseQa, CourseQaReply) that supports polymorphic
 * like/dislike reactions via the qa_reactions table.
 */
trait HasQaReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(QaReaction::class, 'reactable');
    }

    /**
     * Append computed counts + the current user's reaction so the frontend
     * can render the like/dislike buttons in their correct toggled state.
     */
    public function appendReactionState(?int $userId = null): array
    {
        $reactions = $this->reactions;
        $likes = $reactions->where('kind', 'like')->count();
        $dislikes = $reactions->where('kind', 'dislike')->count();
        $mine = $userId
            ? optional($reactions->firstWhere('user_id', $userId))->kind
            : null;

        return [
            'like_count'    => $likes,
            'dislike_count' => $dislikes,
            'my_reaction'   => $mine,
        ];
    }
}
