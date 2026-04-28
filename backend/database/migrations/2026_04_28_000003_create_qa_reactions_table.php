<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Polymorphic reactions: a user can like/dislike either a question
        // (CourseQa) or a reply (CourseQaReply). One reaction per (user, item).
        Schema::create('qa_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable'); // reactable_type + reactable_id (+ index)
            $table->enum('kind', ['like', 'dislike']);
            $table->timestamps();
            $table->unique(['user_id', 'reactable_type', 'reactable_id'], 'qa_reactions_unique_per_user_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_reactions');
    }
};
