<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_attempts', 'question_ids')) {
                $table->json('question_ids')->nullable()->after('quiz_id');
            }
            if (!Schema::hasColumn('quiz_attempts', 'answers_json')) {
                $table->json('answers_json')->nullable()->after('question_ids');
            }
            if (!Schema::hasColumn('quiz_attempts', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('passed');
            }
            if (!Schema::hasColumn('quiz_attempts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $cols = ['question_ids', 'answers_json', 'started_at', 'completed_at'];
            $drop = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('quiz_attempts', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
