<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\News;
use App\Banner;
use App\BannerProgram;
use App\Sponsor;
use App\VisiMisi;
use App\Struktur;
use App\Perkumpulan;
use App\PengajuanPemain;
use App\PengajuanNonAnggota;
use App\User;

/**
 * ComproController
 * Halaman company profile (server-side render via Blade).
 * Mengambil data langsung dari model Eloquent yang sama dipakai oleh API.
 */
class ComproController extends Controller
{
    protected $news;
    protected $banner;
    protected $bannerProgram;
    protected $sponsor;
    protected $visiMisi;
    protected $struktur;
    protected $perkumpulan;
    protected $pengajuanPemain;
    protected $pengajuanNonAnggota;

    public function __construct()
    {
        $this->news                = new News();
        $this->banner              = new Banner();
        $this->bannerProgram       = new BannerProgram();
        $this->sponsor             = new Sponsor();
        $this->visiMisi            = new VisiMisi();
        $this->struktur            = new Struktur();
        $this->perkumpulan         = new Perkumpulan();
        $this->pengajuanPemain     = new PengajuanPemain();
        $this->pengajuanNonAnggota = new PengajuanNonAnggota();
    }

    /* ==================== DASHBOARD ==================== */
    public function dashboard()
    {
        $banners        = $this->news->getActiveBanner();
        $programs       = $this->bannerProgram->getBannerProgramList();
        $latestNews     = array_slice($this->news->getActiveNews(1), 0, 3);
        $upcomingAgenda = array_slice($this->news->getActiveNews(2), 0, 3);
        $sponsors       = array_slice($this->sponsor->getSponsorList(), 0, 6);

        $countPerkumpulanByKategori = fn(string $nama) => DB::table('m_perkumpulan AS pk')
            ->join('m_status_anggota AS sa', 'pk.id_status_anggota', '=', 'sa.id')
            ->where('pk.status', 1)
            ->where('sa.nama', 'LIKE', $nama)
            ->count();

        $stats = [
            'jumlah_anggota_perbasi'  => $countPerkumpulanByKategori('Anggota Perbasi'),
            'jumlah_mitra_pembinaan'  => $countPerkumpulanByKategori('Mitra Pembinaan Perbasi'),
            'jumlah_mitra_binaan'     => $countPerkumpulanByKategori('Mitra Binaan Perbasi'),
            'jumlah_anggota'          => DB::table('m_pengajuan_pemain AS pp')
                ->joinSub(
                    DB::table('m_pengajuan_pemain')
                        ->select('id_user', DB::raw('MAX(tahun) as last_tahun'), DB::raw('MAX(id) as last_id'))
                        ->groupBy('id_user'),
                    'latest',
                    fn($j) => $j->on('pp.id_user', '=', 'latest.id_user')
                                ->on('pp.tahun', '=', 'latest.last_tahun')
                                ->on('pp.id', '=', 'latest.last_id')
                )
                ->where('pp.status', 2)
                ->count(),
            'jumlah_event'            => News::where('kategori', 2)->where('status', 1)->count(),
            'jumlah_sdm'              => DB::table('m_pengajuan_nonaggota AS pn')
                ->joinSub(
                    DB::table('m_pengajuan_nonaggota')
                        ->select('id_user', DB::raw('MAX(tahun) as last_tahun'), DB::raw('MAX(id) as last_id'))
                        ->whereNotIn('status', [-1, -2])
                        ->groupBy('id_user'),
                    'latest',
                    fn($j) => $j->on('pn.id_user', '=', 'latest.id_user')
                                ->on('pn.tahun', '=', 'latest.last_tahun')
                                ->on('pn.id', '=', 'latest.last_id')
                )
                ->where('pn.status', 2)
                ->count(),
        ];

        return view('compro.dashboard', compact('banners', 'programs', 'latestNews', 'upcomingAgenda', 'sponsors', 'stats'));
    }

    /* ==================== PROGRAM ==================== */
    public function bannerProgramDetail($id)
    {
        $program = $this->bannerProgram->getDetailBannerProgram($id);
        if (!$program) {
            abort(404, 'Program tidak ditemukan');
        }

        return view('compro.program-detail', compact('program'));
    }

    /* ==================== TENTANG KAMI ==================== */
    public function visiMisiDetail($id)
    {
        $item = $this->visiMisi->getVisiMisiByEncId($id);
        if (!$item) {
            abort(404, 'Data tidak ditemukan');
        }

        $related = array_filter(
            $this->visiMisi->getVisiMisiList(null, $item->tipe),
            fn($r) => $r->id !== $id
        );

        return view('compro.visi-misi-detail', compact('item', 'related'));
    }

    public function tentang()
    {
        $visi     = $this->visiMisi->getVisiMisiList(null, 'visi');
        $misi     = $this->visiMisi->getVisiMisiList(null, 'misi');
        $struktur = $this->struktur->getStrukturList();

        return view('compro.tentang', compact('visi', 'misi', 'struktur'));
    }

    public function perkumpulan(Request $request)
    {
        $keyword           = $request->get('q');
        $id_status_anggota = $request->get('id_status_anggota');
        $kategori          = $request->get('kategori');

        $statusAnggotaList = DB::table('m_status_anggota')->select('id', 'nama')->orderBy('nama')->get();

        if ($kategori && !$id_status_anggota) {
            $sa = DB::table('m_status_anggota')->where('nama', 'LIKE', $kategori)->first();
            if ($sa) $id_status_anggota = $sa->id;
        }

        $perkumpulan = $this->perkumpulan->getPerkumpulans($keyword, null, $id_status_anggota);

        return view('compro.perkumpulan', compact('perkumpulan', 'statusAnggotaList', 'keyword', 'id_status_anggota'));
    }

    public function perkumpulanDetail(Request $request, $id)
    {
        $perkumpulan = $this->perkumpulan->getPerkumpulanDetails($id);
        if (!$perkumpulan) {
            abort(404, 'Perkumpulan tidak ditemukan');
        }

        $pemain = $this->pengajuanPemain->getApprovedPemain($perkumpulan->id);
        foreach ($pemain as $item) {
            $item->foto_url = !empty($item->filename_foto) ? asset("profil/{$item->filename_foto}") : null;
        }

        $sdm = $this->pengajuanNonAnggota->getApprovedNonAnggota($perkumpulan->id);
        foreach ($sdm as $item) {
            $item->foto_url = !empty($item->filename_foto) ? asset("profil/{$item->filename_foto}") : null;
        }

        return view('compro.perkumpulan-detail', compact('perkumpulan', 'pemain', 'sdm'));
    }

    public function daftarPemain(Request $request)
    {
        $perkumpulanList = DB::table('m_perkumpulan')
            ->select('id', 'nama')->where('status', 1)->orderBy('nama')->get();
        $posisiList      = DB::table('m_posisi')
            ->where('kategori', 'pemain')->orderBy('nama')->pluck('nama');

        return view('compro.daftar-pemain', compact('perkumpulanList', 'posisiList'));
    }

    public function daftarPengurus(Request $request)
    {
        $perkumpulanList = DB::table('m_perkumpulan')
            ->select('id', 'nama')->where('status', 1)->orderBy('nama')->get();
        $posisiList      = DB::table('m_posisi')
            ->where('kategori', 'sdm')->orderBy('nama')->pluck('nama');

        return view('compro.daftar-pengurus', compact('perkumpulanList', 'posisiList'));
    }

    public function apiDaftarPengurus(Request $request)
    {
        $keyword        = $request->get('q');
        $jenis_kelamin  = $request->get('jenis_kelamin');
        $posisi         = $request->get('posisi');
        $id_perkumpulan = $request->get('id_perkumpulan');
        $page           = max(1, (int)$request->get('page', 1));
        $perPage        = 12;

        $total = $this->pengajuanNonAnggota->countDaftarSdmPublik($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);
        $data  = $this->pengajuanNonAnggota->getDaftarSdmPublik($keyword, $jenis_kelamin, $posisi, $id_perkumpulan, $page, $perPage);

        foreach ($data as $item) {
            $item->foto_url = !empty($item->filename_foto)
                ? asset("profil/{$item->filename_foto}")
                : null;
        }

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function apiDaftarPemain(Request $request)
    {
        $keyword        = $request->get('q');
        $jenis_kelamin  = $request->get('jenis_kelamin');
        $posisi         = $request->get('posisi');
        $id_perkumpulan = $request->get('id_perkumpulan');
        $page           = max(1, (int)$request->get('page', 1));
        $perPage        = 12;

        $total = $this->pengajuanPemain->countDaftarPemainPublik($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);
        $data  = $this->pengajuanPemain->getDaftarPemainPublik($keyword, $jenis_kelamin, $posisi, $id_perkumpulan, $page, $perPage);

        foreach ($data as $item) {
            $item->foto_url = !empty($item->filename_foto)
                ? asset("profil/{$item->filename_foto}")
                : null;
        }

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    /* ==================== AGENDA ==================== */
    public function agenda(Request $request)
    {
        $keyword         = $request->get('q');
        // kategori 2 = jadwal pertandingan (sesuai konvensi APINewsController)
        $events          = $this->news->getNews($keyword, 2, 1);
        $allAgenda       = $this->news->getActiveNews(2);
        $eventDates      = collect($allAgenda)
            ->map(fn($e) => \Carbon\Carbon::parse($e->tanggal)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
        $popularArticles = array_slice($this->news->getActiveNews(1), 0, 5);

        return view('compro.agenda', compact('events', 'eventDates', 'popularArticles'));
    }

    public function agendaDetail($id)
    {
        $event = $this->news->getNewsDetails($id);
        if (!$event) {
            abort(404, 'Agenda tidak ditemukan');
        }

        $related = array_slice($this->news->getActiveNews(2), 0, 4);

        return view('compro.agenda-detail', compact('event', 'related'));
    }

    /* ==================== SPONSOR ==================== */
    public function sponsor(Request $request)
    {
        $keyword  = $request->get('q');
        $sponsors = $this->sponsor->getSponsorList($keyword);

        return view('compro.sponsor', compact('sponsors'));
    }

    public function sponsorDetail($id)
    {
        $sponsor = $this->sponsor->getSponsorByEncId($id);
        if (!$sponsor) {
            abort(404, 'Sponsor tidak ditemukan');
        }

        $other = array_slice(
            array_filter($this->sponsor->getSponsorList(), function ($s) use ($id) {
                // getSponsorList mengembalikan id ter-encrypt
                return md5($s->nama) !== md5($id); // selalu lolos; cukup ambil 6 lainnya
            }),
            0,
            6
        );

        return view('compro.sponsor-detail', compact('sponsor', 'other'));
    }

    /* ==================== BERITA ==================== */
    public function berita(Request $request)
    {
        $keyword  = $request->get('q');
        $kategori = $request->get('kategori', 1); // default berita
        $news     = $this->news->getNews($keyword, $kategori, 1);
        $popular  = array_slice($this->news->getActiveNews(1), 0, 4);
        $featured = !empty($news) ? $news[0] : null;
        $rest     = !empty($news) ? array_slice($news, 1) : [];

        return view('compro.berita', compact('news', 'featured', 'rest', 'popular', 'keyword'));
    }

    public function beritaDetail($id)
    {
        $article = $this->news->getNewsDetails($id);
        if (!$article) {
            abort(404, 'Berita tidak ditemukan');
        }

        $related = array_slice($this->news->getActiveNews($article->kategori), 0, 4);

        return view('compro.berita-detail', compact('article', 'related'));
    }
}
