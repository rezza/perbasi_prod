<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roster fields on an event registration, filled when an admin imports
 * participants from Excel or adds one manually (see
 * docs/EVENT_PESERTA_IMPORT_PLANNING.md in perbasi_admin):
 *  - no_punggung / posisi_event / no_hp: per-event values, never written back
 *    to the member's profile.
 *  - sumber: mandiri (member registered themselves) | import | admin.
 *  - id_admin_input: admin who imported/added the row.
 * Idempotent: every column/index is guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'no_punggung' => function (Blueprint $t) { $t->string('no_punggung', 10)->nullable()->after('id_perkumpulan'); },
            'posisi_event' => function (Blueprint $t) { $t->string('posisi_event', 100)->nullable()->after('no_punggung'); },
            'no_hp' => function (Blueprint $t) { $t->string('no_hp', 20)->nullable()->after('posisi_event'); },
            'sumber' => function (Blueprint $t) { $t->string('sumber', 20)->default('mandiri')->after('status'); },
            'id_admin_input' => function (Blueprint $t) { $t->unsignedBigInteger('id_admin_input')->nullable()->after('id_admin_approval'); },
        ];
        foreach ($columns as $name => $add) {
            if (!Schema::hasColumn('m_event_pendaftaran_peserta', $name)) {
                Schema::table('m_event_pendaftaran_peserta', $add);
            }
        }

        $hasIndex = collect(DB::select("SHOW INDEX FROM m_event_pendaftaran_peserta WHERE Key_name = 'event_peserta_no_punggung_index'"))->isNotEmpty();
        if (!$hasIndex) {
            Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) {
                $table->index(['id_event_pendaftaran', 'id_perkumpulan', 'no_punggung'], 'event_peserta_no_punggung_index');
            });
        }
    }

    public function down(): void
    {
        $hasIndex = collect(DB::select("SHOW INDEX FROM m_event_pendaftaran_peserta WHERE Key_name = 'event_peserta_no_punggung_index'"))->isNotEmpty();
        if ($hasIndex) {
            Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) {
                $table->dropIndex('event_peserta_no_punggung_index');
            });
        }
        foreach (['no_punggung', 'posisi_event', 'no_hp', 'sumber', 'id_admin_input'] as $name) {
            if (Schema::hasColumn('m_event_pendaftaran_peserta', $name)) {
                Schema::table('m_event_pendaftaran_peserta', function (Blueprint $table) use ($name) {
                    $table->dropColumn($name);
                });
            }
        }
    }
};
