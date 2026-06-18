<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKategoriToMPosisi extends Migration
{
    public function up()
    {
        Schema::table('m_posisi', function (Blueprint $table) {
            $table->enum('kategori', ['sdm', 'pemain'])->default('pemain')->after('nama');
        });
    }

    public function down()
    {
        Schema::table('m_posisi', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
}
