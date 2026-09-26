<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One banner/berita can now carry many events (see docs/EVENT_MULTI_SCHEDULE_PLANNING.md
 * in perbasi_admin):
 *  1. m_event_pendaftaran.jam_mulai (TIME) — canonical start time of the event,
 *     independent of whether check-in is enabled. Backfilled from m_pertandingan.
 *  2. index (id_news, status) — "active schedules of news X".
 *  3. m_pertandingan is keyed per EVENT, not per news: dedupe (keep the newest
 *     row per event) then UNIQUE(id_event_pendaftaran). Its id_news column is
 *     no longer read (derived from the event); left in place, nullable, so a
 *     rollback stays safe.
 * Idempotent: every step is guarded, so it is safe on a partially-migrated DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('m_event_pendaftaran')) {
            if (!Schema::hasColumn('m_event_pendaftaran', 'jam_mulai')) {
                Schema::table('m_event_pendaftaran', function (Blueprint $table) {
                    $table->time('jam_mulai')->nullable()->after('tanggal_selesai');
                });
            }

            $hasIndex = collect(DB::select("SHOW INDEX FROM m_event_pendaftaran WHERE Key_name = 'm_event_pendaftaran_id_news_status_index'"))->isNotEmpty();
            if (!$hasIndex) {
                Schema::table('m_event_pendaftaran', function (Blueprint $table) {
                    $table->index(['id_news', 'status']);
                });
            }
        }

        if (Schema::hasTable('m_pertandingan') && Schema::hasTable('m_event_pendaftaran')) {
            // Backfill the event's start time from its existing check-in session.
            DB::statement('
                UPDATE m_event_pendaftaran ep
                JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
                SET ep.jam_mulai = TIME(pt.jam_mulai)
                WHERE ep.jam_mulai IS NULL
            ');

            // Old upsert key was id_news, so an event that moved between news
            // may own several sessions. Keep the newest per event; re-point any
            // check-ins from the dropped duplicates onto it (skipping users who
            // already checked in on the survivor, to respect its unique key).
            $dupes = DB::select('
                SELECT id_event_pendaftaran, MAX(id) AS keep_id
                FROM m_pertandingan
                GROUP BY id_event_pendaftaran
                HAVING COUNT(*) > 1
            ');
            foreach ($dupes as $d) {
                $losers = DB::table('m_pertandingan')
                    ->where('id_event_pendaftaran', $d->id_event_pendaftaran)
                    ->where('id', '<>', $d->keep_id)
                    ->pluck('id')->all();

                if (Schema::hasTable('m_pertandingan_checkin')) {
                    // Read the survivor's users first: MySQL forbids updating a
                    // table while selecting from it in a subquery (error 1093).
                    $alreadyIn = DB::table('m_pertandingan_checkin')
                        ->where('id_pertandingan', $d->keep_id)
                        ->pluck('id_user')->all();
                    DB::table('m_pertandingan_checkin')
                        ->whereIn('id_pertandingan', $losers)
                        ->whereNotIn('id_user', $alreadyIn)
                        ->update(['id_pertandingan' => $d->keep_id]);
                    DB::table('m_pertandingan_checkin')->whereIn('id_pertandingan', $losers)->delete();
                }
                DB::table('m_pertandingan')->whereIn('id', $losers)->delete();
            }

            $hasUnique = collect(DB::select("SHOW INDEX FROM m_pertandingan WHERE Key_name = 'm_pertandingan_id_event_pendaftaran_unique'"))->isNotEmpty();
            if (!$hasUnique) {
                // Replace the plain index with the unique one.
                $hasPlain = collect(DB::select("SHOW INDEX FROM m_pertandingan WHERE Key_name = 'm_pertandingan_id_event_pendaftaran_index'"))->isNotEmpty();
                Schema::table('m_pertandingan', function (Blueprint $table) use ($hasPlain) {
                    if ($hasPlain) $table->dropIndex(['id_event_pendaftaran']);
                    $table->unique('id_event_pendaftaran');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('m_pertandingan')) {
            $hasUnique = collect(DB::select("SHOW INDEX FROM m_pertandingan WHERE Key_name = 'm_pertandingan_id_event_pendaftaran_unique'"))->isNotEmpty();
            if ($hasUnique) {
                Schema::table('m_pertandingan', function (Blueprint $table) {
                    $table->dropUnique(['id_event_pendaftaran']);
                    $table->index('id_event_pendaftaran');
                });
            }
        }

        if (Schema::hasTable('m_event_pendaftaran')) {
            $hasIndex = collect(DB::select("SHOW INDEX FROM m_event_pendaftaran WHERE Key_name = 'm_event_pendaftaran_id_news_status_index'"))->isNotEmpty();
            if ($hasIndex) {
                Schema::table('m_event_pendaftaran', function (Blueprint $table) {
                    $table->dropIndex(['id_news', 'status']);
                });
            }
            if (Schema::hasColumn('m_event_pendaftaran', 'jam_mulai')) {
                Schema::table('m_event_pendaftaran', function (Blueprint $table) {
                    $table->dropColumn('jam_mulai');
                });
            }
        }
    }
};
