<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section G demanded a handover officer and a signed note from everyone, so a
 * traveller who genuinely has nobody to cover their duties had to invent one —
 * putting a false name and a meaningless document on an official permit.
 *
 * Let them say so instead, on the record: a flag they must set deliberately and
 * a declaration in their own words. The declaration replaces the handover note
 * as the account of what happens to the duties, so it is shown to the approver
 * and printed on the permit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_requests', function (Blueprint $table) {
            $table->boolean('g_no_handover_officer')
                ->default(false)
                ->after('g_handover_document');

            $table->text('g_no_handover_declaration')
                ->nullable()
                ->after('g_no_handover_officer');
        });
    }

    public function down(): void
    {
        Schema::table('travel_requests', function (Blueprint $table) {
            $table->dropColumn(['g_no_handover_officer', 'g_no_handover_declaration']);
        });
    }
};
