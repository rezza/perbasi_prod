<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes up `m_pertandingan` for sites where the original migration
 * (2026_09_20_100000) already ran with the old match-specific schema
 * (mandatory id_perkumpulan_a/id_perkumpulan_b, no judul) before the
 * feature was generalized to any check-in session (match, seminar, etc).
 * No-op on a fresh install where the table was created straight from the
 * already-updated 2026_09_20_100000 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('m_pertandingan')) {
            return;
        }

        if (Schema::hasColumn('m_pertandingan', 'id_perkumpulan_a')) {
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->dropIndex(['id_perkumpulan_a', 'id_perkumpulan_b']);
            });
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->dropColumn(['id_perkumpulan_a', 'id_perkumpulan_b']);
            });
        }

        if (!Schema::hasColumn('m_pertandingan', 'judul')) {
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->string('judul', 255)->nullable()->after('id_event_pendaftaran');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('m_pertandingan')) {
            return;
        }

        if (Schema::hasColumn('m_pertandingan', 'judul')) {
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->dropColumn('judul');
            });
        }

        if (!Schema::hasColumn('m_pertandingan', 'id_perkumpulan_a')) {
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_perkumpulan_a')->nullable()->after('id_event_pendaftaran');
                $table->unsignedBigInteger('id_perkumpulan_b')->nullable();
            });
            Schema::table('m_pertandingan', function (Blueprint $table) {
                $table->index(['id_perkumpulan_a', 'id_perkumpulan_b']);
            });
        }
    }
};
