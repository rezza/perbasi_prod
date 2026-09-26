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
use App\Pertandingan;
use App\Helpers\JWTHelper;
use Illuminate\Support\Facades\Cache;

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
    protected $user;
    protected $pertandingan;

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
        $this->user                = new User();
        $this->pertandingan        = new Pertandingan();
    }

    /* ==================== DASHBOARD ==================== */
    public function dashboard()
    {
        // Covers show a date/time/check-in/location strip when the post is linked to a check-in session.
        $banners        = $this->pertandingan->attachSummaryToNewsRows($this->news->getActiveBanner());
        $programs       = $this->bannerProgram->getBannerProgramList();
        $latestNews     = $this->pertandingan->attachSummaryToNewsRows(array_slice($this->news->getActiveNews(1), 0, 3));
        $upcomingAgenda = $this->pertandingan->attachSummaryToNewsRows(array_slice($this->news->getActiveNews(2), 0, 3));
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
        $events          = $this->pertandingan->attachSummaryToNewsRows($this->news->getNews($keyword, 2, 1));
        $allAgenda       = $this->news->getActiveNews(2);
        $eventDates      = collect($allAgenda)
            ->map(fn($e) => \Carbon\Carbon::parse($e->tanggal)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
        $popularArticles = array_slice($this->news->getActiveNews(1), 0, 5);

        return view('compro.agenda', compact('events', 'eventDates', 'popularArticles'));
    }

    public function agendaDetail(Request $request, $id)
    {
        $event = $this->news->getNewsDetails($id);
        if (!$event) {
            abort(404, 'Agenda tidak ditemukan');
        }

        $related = array_slice($this->news->getActiveNews(2), 0, 4);

        [$schedules, $pertandingan] = $this->scheduleInfo($request, $event);

        return view('compro.agenda-detail', compact('event', 'related', 'schedules', 'pertandingan'));
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
        $news     = $this->pertandingan->attachSummaryToNewsRows($this->news->getNews($keyword, $kategori, 1));
        $popular  = array_slice($this->news->getActiveNews(1), 0, 4);
        $featured = !empty($news) ? $news[0] : null;
        $rest     = !empty($news) ? array_slice($news, 1) : [];

        return view('compro.berita', compact('news', 'featured', 'rest', 'popular', 'keyword'));
    }

    public function beritaDetail(Request $request, $id)
    {
        $article = $this->news->getNewsDetails($id);
        if (!$article) {
            abort(404, 'Berita tidak ditemukan');
        }

        $related = array_slice($this->news->getActiveNews($article->kategori), 0, 4);

        [$schedules, $pertandingan] = $this->scheduleInfo($request, $article);

        return view('compro.berita-detail', compact('article', 'related', 'schedules', 'pertandingan'));
    }

    /**
     * A news/banner can carry MANY events. Returns [$schedules, $pertandingan]:
     *  - $schedules: every aktif/selesai event of the news (oldest first) with
     *    the visitor's own registration + check-in state; for events the
     *    visitor may check in to right now, also `role` + `prefill` so the
     *    form can render pemain fields (one detail-for-member call each, only
     *    for those events).
     *  - $pertandingan: session summary of the primary event, ids encoded —
     *    only feeds the info strip on the cover image.
     */
    private function scheduleInfo(Request $request, $newsRow): array
    {
        $userId = session('jwt_token')
            ? (JWTHelper::validateToken(session('jwt_token'))['data']['user_id'] ?? null)
            : null;

        $eventModel = new \App\EventPendaftaran();
        $schedules = $eventModel->getMemberSchedulesByNewsId($newsRow->id, $userId ?? 0);

        foreach ($schedules as &$s) {
            $s['role'] = null;
            $s['prefill'] = null;
            $approved = (int) ($s['my_registration']['status'] ?? -99) === 1;
            if ($userId && $approved && $s['checkin_enabled'] && !$s['my_checkin']) {
                $request->merge(['id_event_pendaftaran' => $s['id']]);
                $result = json_decode(
                    app(APIPertandinganController::class)->detailForMember($this->withAuthHeader($request)),
                    true
                );
                if (($result['status'] ?? null) === '00') {
                    $s['role'] = $result['data']['role'] ?? null;
                    $s['prefill'] = $result['data']['prefill'] ?? null;
                }
            }
        }
        unset($s);

        $primaryRawId = $eventModel->getActiveEventIdByNewsId($newsRow->id);
        $pertandingan = $primaryRawId ? $this->pertandingan->getByEventId($primaryRawId) : null;
        if ($pertandingan) {
            $this->pertandingan->encodeForClient($pertandingan);
        }

        return [$schedules, $pertandingan];
    }

    /* ==================== AUTH (session-based; bridges to the JWT API) ====================
     * Compro visitors identify themselves via the same email+password login as the
     * mobile app. The JWT returned by APIUserController::submitLogin is stored
     * server-side in the Laravel session (never exposed to the browser as JS-readable
     * storage) instead of being sent as a bearer header on every request.
     */

    public function loginForm()
    {
        if (session('jwt_token') && (JWTHelper::validateToken(session('jwt_token'))['valid'] ?? false)) {
            return redirect()->to(session('compro_intended_url', route('compro.dashboard')));
        }

        return view('compro.login');
    }

    public function loginSubmit(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = json_decode(app(APIUserController::class)->submitLogin($request), true);

        if (($result['status'] ?? null) !== '00') {
            return back()->withInput($request->only('username'))->with('error', $result['message'] ?? 'Masuk gagal');
        }

        session(['jwt_token' => $result['token']]);
        $intended = session()->pull('compro_intended_url', route('compro.dashboard'));

        return redirect()->to($intended)->with('success', 'Berhasil masuk.');
    }

    public function registerForm()
    {
        return view('compro.register');
    }

    public function registerSubmit(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'id_kota' => 'required|string',
        ]);

        $result = json_decode(app(APIUserController::class)->registerUser($request), true);

        if (($result['status'] ?? null) !== '00') {
            return back()->withInput($request->except('password'))->with('error', $result['message'] ?? 'Registrasi gagal');
        }

        // Auto-login so the visitor lands straight on the event form they came from.
        $loginRequest = Request::create('', 'POST', [
            'username' => $request->email,
            'password' => $request->password,
        ]);
        $loginResult = json_decode(app(APIUserController::class)->submitLogin($loginRequest), true);
        if (($loginResult['status'] ?? null) === '00') {
            session(['jwt_token' => $loginResult['token']]);
        }

        $intended = session()->pull('compro_intended_url', route('compro.dashboard'));

        return redirect()->to($intended)->with('success', 'Akun berhasil dibuat.');
    }

    public function logout()
    {
        session()->forget(['jwt_token', 'compro_intended_url']);

        return redirect()->route('compro.dashboard')->with('success', 'Berhasil keluar.');
    }

    /**
     * SSO handoff to the separate member web app (member.perbasipemalang.org):
     * mint a single-use, short-lived code for the logged-in compro visitor
     * and hand it off via URL so they land there already signed in, instead
     * of having to log in a second time. The member app exchanges the code
     * for a JWT via POST /api/sso/exchange (APIUserController::ssoExchange).
     */
    public function ssoMemberRedirect()
    {
        $token = session('jwt_token');
        $validation = $token ? JWTHelper::validateToken($token) : ['valid' => false];
        if (!($validation['valid'] ?? false)) {
            session(['compro_intended_url' => route('compro.sso.member')]);
            return redirect()->route('compro.login');
        }

        $code = bin2hex(random_bytes(32));
        Cache::put('sso_member_' . $code, $validation['data']['user_id'], now()->addSeconds(60));

        return redirect()->away('https://member.perbasipemalang.org/login?sso=' . $code);
    }

    /* ==================== EVENT REGISTRATION (bridges to the JWT API) ====================
     * These delegate straight into APIEventPendaftaranController's existing,
     * already-battle-tested methods (used by the mobile member app) -- no business
     * logic is duplicated here. The only bridging step is attaching the session's
     * JWT as the Authorization header the API methods already expect.
     */

    private function withAuthHeader(Request $request): Request
    {
        $request->headers->set('Authorization', 'Bearer ' . session('jwt_token'));
        return $request;
    }

    private function eventDetail(Request $request, $id): array
    {
        $request->merge(['id' => $id]);
        $result = json_decode(app(APIEventPendaftaranController::class)->detailEventForMember($this->withAuthHeader($request)), true);

        if (($result['status'] ?? null) !== '00') {
            abort(404, $result['message'] ?? 'Event tidak ditemukan');
        }

        return $result['data'];
    }

    /**
     * Values of requirements whose source is the member profile (sumber = profil) and
     * that the profile already has. These are shown read-only on the form and injected
     * server-side on submit, since disabled inputs are not posted by the browser.
     * A requirement the profile has no value for stays editable.
     */
    private function profileLockedValues(array $definitions): array
    {
        $userId = JWTHelper::validateToken(session('jwt_token'))['data']['user_id'] ?? null;
        $profile = $userId ? $this->user->getLoginData($userId) : null;
        if (!$profile) {
            return [];
        }

        $locked = [];
        foreach ($definitions as $def) {
            if (($def['sumber'] ?? null) !== 'profil' || empty($def['profil_field_map'])) {
                continue;
            }
            $value = $profile->{$def['profil_field_map']} ?? null;
            if ($value !== null && $value !== '') {
                $locked[$def['key']] = (string) $value;
            }
        }

        return $locked;
    }

    public function eventForm(Request $request, $id)
    {
        $data = $this->eventDetail($request, $id);

        return view('compro.event-form', [
            'eventId' => $id,
            'event' => $data['event'],
            'myPerkumpulan' => $data['my_perkumpulan'],
            'registration' => $data['registration'],
            'lockedValues' => $this->profileLockedValues($data['event']['persyaratan_definitions']),
        ]);
    }

    public function eventUploadFile(Request $request)
    {
        $result = json_decode(app(APIEventPendaftaranController::class)->uploadFilePeserta($this->withAuthHeader($request)), true);

        return response()->json($result);
    }

    public function eventSubmit(Request $request, $id)
    {
        $data = $this->eventDetail($request, $id);
        $request->merge([
            'id_event' => $id,
            'data_persyaratan' => array_merge(
                (array) $request->input('data_persyaratan', []),
                $this->profileLockedValues($data['event']['persyaratan_definitions'])
            ),
        ]);
        $result = json_decode(app(APIEventPendaftaranController::class)->daftarPeserta($this->withAuthHeader($request)), true);

        if (($result['status'] ?? null) !== '00') {
            $message = $result['message'] ?? 'Pendaftaran gagal';
            if (!empty($result['data']['missing_fields'])) {
                $message .= ': ' . implode(', ', $result['data']['missing_fields']);
            }
            return back()->withInput()->with('error', $message);
        }

        return redirect()->route('compro.event.form', $id)->with('success', $result['message']);
    }

    /* ==================== PERTANDINGAN CHECK-IN ==================== */

    public function pertandinganCheckinSubmit(Request $request, $id)
    {
        $request->merge(['id_news' => $id]);
        $result = json_decode(
            app(APIPertandinganController::class)->submitCheckin($this->withAuthHeader($request)),
            true
        );

        if (($result['status'] ?? null) !== '00') {
            return back()->withInput()->with('error', $result['message'] ?? 'Check-in gagal');
        }

        return redirect()->route('compro.agenda.detail', $id)->with('success', $result['message']);
    }

    public function pertandinganAttendanceList(Request $request, $id)
    {
        $request->merge(['id_news' => $id]);
        $result = json_decode(
            app(APIPertandinganController::class)->getAttendanceList($this->withAuthHeader($request)),
            true
        );

        return response()->json($result);
    }

    /** Check-in to ONE event of a multi-schedule banner/berita ({id} = encoded event id). */
    public function eventCheckinSubmit(Request $request, $id)
    {
        $request->merge(['id_event_pendaftaran' => $id]);
        $result = json_decode(
            app(APIPertandinganController::class)->submitCheckin($this->withAuthHeader($request)),
            true
        );

        if (($result['status'] ?? null) !== '00') {
            return back()->withInput()->with('error', $result['message'] ?? 'Check-in gagal');
        }

        return back()->with('success', $result['message']);
    }

    public function eventAttendanceList(Request $request, $id)
    {
        $request->merge(['id_event_pendaftaran' => $id]);
        $result = json_decode(
            app(APIPertandinganController::class)->getAttendanceList($this->withAuthHeader($request)),
            true
        );

        return response()->json($result);
    }
}
