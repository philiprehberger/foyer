<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        // Leave the extension installed; dropping it cascades to anything else using it.
    }
};
