<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($q) {
                $q->whereNull('face_url')->orWhere('face_url', '');
            })
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->update(['face_url' => DB::raw('avatar')]);
    }

    public function down(): void
    {
        // Non-destructive backfill.
    }
};
