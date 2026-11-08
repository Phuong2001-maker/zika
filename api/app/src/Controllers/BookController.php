<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\BookAccess;
use Zika\Services\Curricula;

/**
 * Giáo trình đọc theo ảnh trang: HSK 1–3 (Hanu) và Hán ngữ 1–6, kèm file nghe và đáp án nếu sách có.
 *
 * Mỗi quyển là một thư mục `uploads/giao-trinh/<mã>/` do `data/giao-trinh/dung-sach.py` dựng, trong đó `sach.json`
 * mô tả tên, mục lục, file nghe, đáp án. Quyển nào không có file nghe hay đáp án thì mảng rỗng / null, web tự ẩn.
 * Sách có bản quyền: .htaccess chặn thẳng thư mục này, chỉ còn lối qua đây, và mọi route đòi đăng nhập.
 *
 * Học viên chỉ thấy bộ sách của giáo trình mình học (Hán ngữ cho giáo trình gọi cấp "Quyển", HSK cho "HSK"),
 * biên tập và quản trị thấy cả hai bộ và đọc tự do. Học viên chỉ nhận được file của bài đã mở bằng điểm (BookAccess),
 * và mọi đường dẫn file phải kèm mã ký `?t=` của chính tài khoản đó.
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
            $ra[] = [
                'ma'       => $ma,
                'bo'       => $s['bo'],
                'tenBo'    => BookAccess::BO[$s['bo']],
                'cap'      => $s['cap'],
                'ten'      => $s['ten'],
                'phu'      => $s['phu'],
                'phien'    => (string) ($s['phien'] ?? ''),
                'soTrang'  => $s['soTrang'],
                'soBai'    => count($s['bai']),
                'soNghe'   => count($s['nghe']),
                'phutNghe' => (int) round(array_sum(array_column($s['nghe'], 'giay')) / 60),
                'coDapAn'  => $s['dapAn'] !== null,
                'khoa'     => !isset($t['toi'][$ma]),
                'baiMo'    => $t['tuDo'] ? count($s['bai']) : count($t['mo'][$ma] ?? []),
            ];
        }
        return $ra;
    }

    /** GET /learn/books/{ma} — mô tả đủ của một quyển kèm quyền đọc, để màn đọc dựng mục lục, file nghe, đáp án. */
    public function show(Request $r): array
    {
        $s = $this->sach($r, $r->param('ma'));
        $s['tenBo'] = BookAccess::BO[$s['bo']];
        $s['quyen'] = $this->quyen($r, $s);
        return $s;
    }

    /** GET /learn/books/{ma}/quyen — làm mới quyền đọc và mã ký (sau khi mở bài, nhận điểm, hay mã sắp hết hạn). */
    public function access(Request $r): array
    {
        return $this->quyen($r, $this->sach($r, $r->param('ma')));
    }

    /** POST /learn/books/{ma}/mo/{bai} — dùng 1 điểm mở bài tiếp theo. */
    public function unlock(Request $r): array
    {
        $s = $this->sach($r, $r->param('ma'));
        if (!$r->isStaff()) {
            BookAccess::moBai($r->userId(), (string) $s['bo'], (string) $s['ma'], (int) $r->param('bai'));
        }
        return $this->quyen($r, $s);
    }

    /** GET /learn/books/{ma}/{trang} — trang là số (1, 2…) hoặc "bia". */
    public function page(Request $r): Response
    {
        $ma = $r->param('ma');
        $s = $this->sach($r, $ma);
        $trang = $r->param('trang');
        // Bìa hiện ở kệ sách cho cả quyển còn khoá, không có gì để giấu.
        if ($trang === 'bia') {
            return $this->anh($this->file($ma, 'bia.webp'));
        }
        $this->kiemVe($r, $ma);
        if (!BookAccess::moTrang($this->trangThai($r, $s['bo']), $s, (int) $trang)) {
            throw self::khoa();
        }
        return $this->anh($this->file($ma, sprintf('%03d.webp', (int) $trang)));
    }

    /** GET /learn/books/{ma}/dap-an/{trang} */
    public function answer(Request $r): Response
    {
        $ma = $r->param('ma');
        $s = $this->sach($r, $ma);
        if ($s['dapAn'] === null) {
            throw HttpError::notFound();
        }
        $this->kiemVe($r, $ma);
        if (!BookAccess::moDapAn($this->trangThai($r, $s['bo']), $s, (int) $r->param('trang'))) {
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
        $s = $this->sach($r, $ma);
        $this->kiemVe($r, $ma);
        if (!BookAccess::moNghe($this->trangThai($r, $s['bo']), $s, (int) $r->param('so'))) {
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
    private function quyen(Request $r, array $s): array
    {
        $ma = (string) $s['ma'];
        $tt = $this->trangThai($r, (string) $s['bo']);
        $ke = $tt['ke'];
        if ($ke !== null) {
            foreach (BookAccess::sachCuaBo((string) $s['bo']) as $q) {
                if ($q['ma'] === $ke['ma']) {
                    $ke['tenSach'] = $q['ten'];
                }
            }
        }
        $uid = $r->userId();
        return [
            'tuDo'  => $tt['tuDo'],
            'khoa'  => !isset($tt['toi'][$ma]),
            'mo'    => $tt['tuDo'] ? array_map('intval', array_column($s['bai'], 'so')) : array_keys($tt['mo'][$ma] ?? []),
            'ke'    => $ke,
            'diem'  => $tt['tuDo'] ? 0 : BookAccess::soDiem($uid),
            've'    => BookAccess::ve($uid, $ma),
            'veGiay' => BookAccess::VE_GIAY,
        ];
    }

    /** @return array<string,mixed> */
    private function trangThai(Request $r, string $bo): array
    {
        return BookAccess::trangThai($r->userId(), $bo, $r->isStaff());
    }

    private function kiemVe(Request $r, string $ma): void
    {
        if (!BookAccess::veHopLe((string) $r->q('t', ''), $r->userId(), $ma)) {
            throw new HttpError(403, 'TOKEN_INVALID', 'Đường dẫn trang sách đã hết hạn. Tải lại trang để đọc tiếp.');
        }
    }

    private static function khoa(): HttpError
    {
        return new HttpError(403, 'LOCKED', 'Bài này chưa mở. Dùng điểm mở bài để xem.');
    }

    /** @return list<array<string,mixed>> các quyển người này được thấy, Hán ngữ trước rồi HSK, mỗi bộ theo cấp. */
    private function sachCho(Request $r): array
    {
        return BookAccess::sachCuaBo($r->isStaff() ? null : self::boCua($r));
    }

    /** Bộ sách của giáo trình người học: giáo trình gọi cấp "Quyển" là Hán ngữ, còn lại là HSK. */
    private static function boCua(Request $r): string
    {
        return Curricula::levelLabel(Curricula::ofUser($r->userId())) === 'Quyển' ? 'hanngu' : 'hsk';
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
