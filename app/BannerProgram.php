<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BannerProgram extends Model
{
    protected $table = 'm_banner_program';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'id', 'judul', 'short_description', 'artikel',
        'cover_type', 'image_url', 'youtube_url', 'icon_name',
        'external_link', 'id_admin', 'created_at', 'updated_at',
    ];
    public $imbuhan = 'B4NNR_PROG';

    public static $iconMap = [
        'sports_basketball'    => 'fa-basketball',
        'emoji_events'         => 'fa-trophy',
        'sports_score'         => 'fa-flag-checkered',
        'star'                 => 'fa-star',
        'workspace_premium'    => 'fa-crown',
        'flag'                 => 'fa-flag',
        'groups'               => 'fa-users',
        'military_tech'        => 'fa-medal',
        'shield'               => 'fa-shield-halved',
        'favorite'             => 'fa-heart',
        'bolt'                 => 'fa-bolt',
        'local_fire_department'=> 'fa-fire',
        'celebration'          => 'fa-champagne-glasses',
        'sports'               => 'fa-dumbbell',
        'trending_up'          => 'fa-chart-line',
    ];

    public static $validIcons = [
        'sports_basketball', 'emoji_events', 'sports_score', 'star',
        'workspace_premium', 'flag', 'groups', 'military_tech', 'shield',
        'favorite', 'bolt', 'local_fire_department', 'celebration',
        'sports', 'trending_up',
    ];

    function getBannerProgramList()
    {
        return DB::select(
            'SELECT
                md5(concat(bp.id, "' . $this->imbuhan . '")) AS id,
                bp.judul,
                bp.short_description,
                bp.artikel,
                bp.cover_type,
                bp.image_url,
                bp.youtube_url,
                bp.icon_name,
                bp.external_link,
                bp.id_admin,
                bp.created_at,
                bp.updated_at
            FROM m_banner_program bp
            ORDER BY bp.created_at DESC
            LIMIT 3'
        );
    }

    function getBannerProgramByEncId($encryptedid)
    {
        $data = DB::select(
            'SELECT
                bp.id,
                bp.judul,
                bp.short_description,
                bp.artikel,
                bp.cover_type,
                bp.image_url,
                bp.youtube_url,
                bp.icon_name,
                bp.external_link,
                bp.id_admin,
                bp.created_at,
                bp.updated_at
            FROM m_banner_program bp
            WHERE md5(concat(bp.id, "' . $this->imbuhan . '")) = ?',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getDetailBannerProgram($encryptedid)
    {
        $data = DB::select(
            'SELECT
                md5(concat(bp.id, "' . $this->imbuhan . '")) AS id,
                bp.judul,
                bp.short_description,
                bp.artikel,
                bp.cover_type,
                bp.image_url,
                bp.youtube_url,
                bp.icon_name,
                bp.external_link,
                bp.id_admin,
                bp.created_at,
                bp.updated_at
            FROM m_banner_program bp
            WHERE md5(concat(bp.id, "' . $this->imbuhan . '")) = ?',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function countBannerProgram()
    {
        $result = DB::select('SELECT COUNT(*) AS total FROM m_banner_program');
        return $result[0]->total ?? 0;
    }
}
