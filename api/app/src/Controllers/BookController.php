<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\BookAccess;

/**
 * Giáo trình đọc theo ảnh trang: HSK 1–3 (Hanu) và Hán ngữ 1–6, kèm file nghe và đáp án nếu sách có.
 *
 * Mỗi quyển là một thư mục `uploads/giao-trinh/<mã>/` do `data/giao-trinh/dung-sach.py` dựng, trong đó `sach.json`
 * mô tả tên, mục lục, file nghe, đáp án. Quyển nào không có file nghe hay đáp án thì mảng rỗng / null, web tự ẩn.
 * Sách có bản quyền: .htaccess chặn thẳng thư mục này, chỉ còn lối qua đây. Khách chưa đăng nhập (11/11) chỉ đọc
 * quyển đầu của bộ sách thuộc giáo trình mặc định (BookAccess::trangThaiKhach); mở bài bằng điểm vẫn đòi đăng nhập.
 *
 * Học viên chỉ thấy bộ sách của giáo trình mình học (Hán ngữ cho giáo trình gọi cấp "Quyển", HSK cho "HSK"),
 * biên tập và quản trị thấy cả hai bộ và đọc tự do. Học viên chỉ nhận được file của bài đã mở bằng điểm (BookAccess),
 * và mọi đường dẫn file phải kèm mã ký `?t=` của chính tài khoản đó.
 *
 * Cây Giáo trình → Ẩn/hiện nội dung áp cả vào đây (BookAccess::an): bài ẩn không có tên, file nghe, ảnh trang với bất
 * kỳ ai, kể cả biên tập/quản trị; quyển thuộc cấp hoặc giáo trình đang ẩn thì biến khỏi kệ sách.
 */
final class BookController
{
    /** GET /learn/books — giá sách: phần tóm tắt và quyển nào đang khoá; mục lục đầy đủ lấy khi mở từng quyển. */
    public function index(Request $r): array
    {
        $ra = [];
        $ttBo = [];
        foreach ($this->sachCho($r) as $s) {
            $t = $ttBo[$s['bo']] ??= $this->trangThai($r, $s['bo']);
            $ma = (string) $s['ma'];
            if (BookAccess::quyenAn($t, $ma)) {
                continue;
            }
            $an = array_flip(BookAccess::baiAn($t, $s));
            $ra[] = [
                'ma'       => $ma,
                'bo'       => $s['bo'],
                'tenBo'    => BookAccess::BO[$s['bo']],
                'cap'      => $s['cap'],
                'ten'      => $s['ten'],
                'phu'      => $s['phu'],
                'phien'    => (string) ($s['phien'] ?? ''),
                'soTrang'  => $s['soTrang'],
                'soBai'    => count($s['bai']) - count($an),
                'soNghe'   => count($nghe = self::ngheHien($s, $an)),
                'phutNghe' => (int) round(array_sum(array_column($nghe, 'giay')) / 60),
                'coDapAn'  => $s['dapAn'] !== null,
                'khoa'     => !isset($t['toi'][$ma]),
                'baiMo'    => $t['tuDo'] ? count($s['bai']) - count($an) : count(array_diff_key($t['mo'][$ma] ?? [], $an)),
            ];
        }
        return $ra;
    }

    /** GET /learn/books/{ma} — mô tả đủ của một quyển kèm quyền đọc, để màn đọc dựng mục lục, file nghe, đáp án. */
    public function show(Request $r): array
    {
        [$s, $tt] = $this->doc($r, $r->param('ma'));
        $s['quyen'] = $this->quyen($r, $s, $tt);
        // Bài ẩn vẫn giữ số và trang bắt đầu (màn đọc cần để biết trang nào thuộc bài nào) nhưng không lộ tên, file nghe.
        $an = array_flip($s['quyen']['an']);
        foreach ($s['bai'] as &$b) {
            if (isset($an[(int) $b['so']])) {
                $b['ten'] = '';
                $b['tenVi'] = '';
            }
        }
        unset($b);
        $s['nghe'] = self::ngheHien($s, $an);
        $s['tenBo'] = BookAccess::BO[$s['bo']];
        return $s;
    }

    /**
     * File nghe của các bài không ẩn.
     * @param array<string,mixed> $s @param array<int,int> $an số bài ẩn → bất kỳ
     * @return list<array<string,mixed>>
     */
    private static function ngheHien(array $s, array $an): array
    {
        return array_values(array_filter($s['nghe'], static fn (array $n): bool => !isset($an[(int) $n['bai']])));
    }

    /** GET /learn/books/{ma}/quyen — làm mới quyền đọc và mã ký (sau khi mở bài, nhận điểm, hay mã sắp hết hạn). */
    public function access(Request $r): array
    {
        [$s, $tt] = $this->doc($r, $r->param('ma'));
        return $this->quyen($r, $s, $tt);
    }

    /** POST /learn/books/{ma}/mo/{bai} — dùng 1 điểm mở bài tiếp theo. */
    public function unlock(Request $r): array
    {
        [$s, $tt] = $this->doc($r, $r->param('ma'));
        $bai = (int) $r->param('bai');
        if (in_array($bai, BookAccess::baiAn($tt, $s), true)) {
            throw self::an();
        }
        if ($r->user === null) {
            throw new HttpError(401, 'UNAUTHENTICATED', 'Đăng nhập để mở bài bằng điểm.');
        }
        if (!$r->isStaff()) {
            BookAccess::moBai($r->userId(), (string) $s['bo'], (string) $s['ma'], $bai);
        }
        return $this->quyen($r, $s);
    }

    /** GET /learn/books/{ma}/{trang} — trang là số (1, 2…) hoặc "bia". */
    public function page(Request $r): Response
    {
        $ma = $r->param('ma');
        [$s, $tt] = $this->doc($r, $ma);
        $trang = $r->param('trang');
        // Bìa hiện ở kệ sách cho cả quyển còn khoá, không có gì để giấu.
        if ($trang === 'bia') {
            return $this->anh($this->file($ma, 'bia.webp'));
        }
        $this->kiemVe($r, $ma);
        $this->chanBaiAn($tt, $s, BookAccess::baiCuaTrang($s['bai'], (int) $trang));
        if (!BookAccess::moTrang($tt, $s, (int) $trang)) {
            throw self::khoa();
        }
        return $this->anh($this->file($ma, sprintf('%03d.webp', (int) $trang)));
    }

    /** GET /learn/books/{ma}/dap-an/{trang} */
    public function answer(Request $r): Response
    {
        $ma = $r->param('ma');
        [$s, $tt] = $this->doc($r, $ma);
        if ($s['dapAn'] === null) {
            throw HttpError::notFound();
        }
        $this->kiemVe($r, $ma);
        $this->chanBaiAn($tt, $s, BookAccess::baiCuaTrang($s['dapAn']['bai'], (int) $r->param('trang')));
        if (!BookAccess::moDapAn($tt, $s, (int) $r->param('trang'))) {
            throw self::khoa();
        }
        return $this->anh($this->file($ma, sprintf('dap-an/%03d.webp', (int) $r->param('trang'))));
    }

    /**
     * GET /learn/books/{ma}/nghe/{so} — có trả từng đoạn (Range): thiếu nó thì Chrome không tua được thanh thời gian
     * và Safari trên iPhone không phát file âm thanh.
     */
    public function audio(Request $r): Response
    {
        $ma = $r->param('ma');
        [$s, $tt] = $this->doc($r, $ma);
        $this->kiemVe($r, $ma);
        foreach ($s['nghe'] as $n) {
            if ((int) $n['so'] === (int) $r->param('so')) {
                $this->chanBaiAn($tt, $s, (int) $n['bai']);
            }
        }
        if (!BookAccess::moNghe($tt, $s, (int) $r->param('so'))) {
            throw self::khoa();
        }
        $file = $this->file($ma, sprintf('nghe/%02d.mp3', (int) $r->param('so')));
        return Response::fileRange($file, 'audio/mpeg', $r->header('range'), [
            'Cache-Control' => 'private, max-age=' . BookAccess::VE_GIAY,
        ]);
    }

    /**
     * Quyền đọc gửi cho màn đọc: bài nào đã mở, bài nào mở tiếp được, còn bao nhiêu điểm, và mã ký đường dẫn.
     * @param array<string,mixed> $s
     * @return array<string,mixed>
     */
    private function quyen(Request $r, array $s, ?array $tt = null): array
    {
        $ma = (string) $s['ma'];
        $tt ??= $this->trangThai($r, (string) $s['bo']);
        $ke = $tt['ke'];
        if ($ke !== null) {
            foreach (BookAccess::sachCuaBo((string) $s['bo']) as $q) {
                if ($q['ma'] === $ke['ma']) {
                    $ke['tenSach'] = $q['ten'];
                }
            }
        }
        $an = BookAccess::baiAn($tt, $s);
        return [
            'tuDo'  => $tt['tuDo'],
            'khoa'  => !isset($tt['toi'][$ma]),
            'mo'    => array_values(array_diff($tt['tuDo'] ? array_map('intval', array_column($s['bai'], 'so')) : array_keys($tt['mo'][$ma] ?? []), $an)),
            'an'    => $an,
            'ke'    => $ke,
            'diem'  => $tt['tuDo'] || $r->user === null ? 0 : BookAccess::soDiem($r->userId()),
            've'    => BookAccess::ve(self::chuVe($r), $ma),
            'veGiay' => BookAccess::VE_GIAY,
        ];
    }

    /** @return array<string,mixed> */
    private function trangThai(Request $r, string $bo): array
    {
        // Khách học thử đọc trọn quyển đầu của bộ sách thuộc giáo trình mặc định, quyển sau phải đăng nhập.
        return $r->user === null ? BookAccess::trangThaiKhach($bo) : BookAccess::trangThai($r->userId(), $bo, $r->isStaff());
    }

    /** Mã ký đường dẫn gắn với tài khoản; khách dùng chung một chủ thể vì phần khách đọc được là như nhau. */
    private static function chuVe(Request $r): int|string
    {
        return $r->user === null ? BookAccess::KHACH : $r->userId();
    }

    private function kiemVe(Request $r, string $ma): void
    {
        if (!BookAccess::veHopLe((string) $r->q('t', ''), self::chuVe($r), $ma)) {
            throw new HttpError(403, 'TOKEN_INVALID', 'Đường dẫn trang sách đã hết hạn. Tải lại trang để đọc tiếp.');
        }
    }

    private static function khoa(): HttpError
    {
        return new HttpError(403, 'LOCKED', 'Bài này chưa mở. Dùng điểm mở bài để xem.');
    }

    /** Bài ẩn trong cây Ẩn/hiện nội dung: 404 như 6 phần học, khác 403 của bài chưa mở bằng điểm. */
    private static function an(): HttpError
    {
        return HttpError::notFound('Bài này đang ẩn.');
    }

    /** @param array<string,mixed> $tt @param array<string,mixed> $s */
    private function chanBaiAn(array $tt, array $s, ?int $bai): void
    {
        if ($bai !== null && in_array($bai, BookAccess::baiAn($tt, $s), true)) {
            throw self::an();
        }
    }

    /**
     * Quyển `ma` kèm trạng thái đọc. Quyển ẩn (ẩn giáo trình hoặc ẩn cấp ở cây nội dung) báo 404 như quyển không có.
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    private function doc(Request $r, string $ma): array
    {
        $s = $this->sach($r, $ma);
        $tt = $this->trangThai($r, (string) $s['bo']);
        if (BookAccess::quyenAn($tt, $ma)) {
            throw HttpError::notFound();
        }
        return [$s, $tt];
    }

    /** @return list<array<string,mixed>> các quyển người này được thấy, Hán ngữ trước rồi HSK, mỗi bộ theo cấp. */
    private function sachCho(Request $r): array
    {
        return BookAccess::sachCuaBo($r->isStaff() ? null : self::boCua($r));
    }

    private static function boCua(Request $r): string
    {
        return $r->user === null ? BookAccess::boKhach() : BookAccess::boCuaNguoi($r->userId());
    }

    /** @return array<string,mixed> */
    private function sach(Request $r, string $ma): array
    {
        foreach ($this->sachCho($r) as $s) {
            if ($s['ma'] === $ma) {
                return $s;
            }
        }
        // Quyển của bộ khác cũng báo 404 chứ không 403: học viên không cần biết bộ kia tồn tại.
        throw HttpError::notFound();
    }

    private function file(string $ma, string $ten): string
    {
        $root = realpath(Config::uploadsDir('giao-trinh'));
        $file = realpath(Config::uploadsDir("giao-trinh/$ma/$ten"));
        if ($root === false || $file === false || !str_starts_with($file, $root) || !is_file($file)) {
            throw HttpError::notFound();
        }
        return $file;
    }

    private function anh(string $file): Response
    {
        return new Response(200, '', [
            'Content-Type'   => 'image/webp',
            'Content-Length' => (string) filesize($file),
            // private: proxy/CDN không được giữ bản sao. Giữ đúng bằng tuổi mã ký: đường dẫn đổi mã là tải lại.
            'Cache-Control'  => 'private, max-age=' . BookAccess::VE_GIAY,
        ], $file);
    }
}
