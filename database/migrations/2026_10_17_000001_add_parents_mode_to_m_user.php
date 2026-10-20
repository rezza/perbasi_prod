<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parents mode: an account can be registered as a parent (tipe_akun = 'ortu')
 * and manage child accounts (m_user rows whose id_parents points at the parent).
 * A child may have no email/password until allowed to hold the account.
 *
 * Additive only: existing rows become tipe_akun = 'member', id_parents = NULL.
 */
class AddParentsModeToMUser extends Migration
{
    public function up()
    {
        Schema::table('m_user', function (Blueprint $table) {
            if (!Schema::hasColumn('m_user', 'id_parents')) {
                $table->unsignedBigInteger('id_parents')->nullable()->index();
            }
            if (!Schema::hasColumn('m_user', 'tipe_akun')) {
                $table->string('tipe_akun', 10)->default('member');
            }
        });

        // Make email & password nullable while keeping their existing type/length.
        foreach (['email', 'password'] as $column) {
            $col = DB::selectOne(
                'SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['m_user', $column]
            );
            if ($col && $col->nullable === 'NO') {
                DB::statement('ALTER TABLE m_user MODIFY `' . $column . '` ' . $col->type . ' NULL');
            }
        }
    }

    public function down()
    {
        Schema::table('m_user', function (Blueprint $table) {
            $table->dropIndex(['id_parents']);
            $table->dropColumn(['id_parents', 'tipe_akun']);
        });
        // email/password stay nullable: rows with NULL may exist, so NOT NULL can't be restored safely.
    }
}
