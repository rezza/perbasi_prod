<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeteranganToMPerkumpulan extends Migration
{
    public function up()
    {
        Schema::table('m_perkumpulan', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('no_sk');
        });
    }

    public function down()
    {
        Schema::table('m_perkumpulan', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
}
