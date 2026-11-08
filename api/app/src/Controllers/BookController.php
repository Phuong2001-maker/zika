<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\Curricula;

/**
 * Giáo trình đọc theo ảnh trang: HSK 1–3 (Hanu) và Hán ngữ 1–6, kèm file nghe và đáp án nếu sách có.
 *
 * Mỗi quyển là một thư mục `uploads/giao-trinh/<mã>/` do `data/giao-trinh/dung-sach.py` dựng, trong đó `sach.json`
 * mô tả tên, mục lục, file nghe, đáp án. Quyển nào không có file nghe hay đáp án thì mảng rỗng / null, web tự ẩn —
 * không có danh sách viết cứng nào phải sửa khi thêm sách. Sách có bản quyền: .htaccess chặn thẳng thư mục này,
 * chỉ còn lối qua đây, và mọi route đòi đăng nhập.
 *
 * Học viên chỉ thấy bộ sách của giáo trình mình học (Hán ngữ cho giáo trình gọi cấp "Quyển", HSK cho "HSK"),
 * biên tập và quản trị thấy cả hai bộ.
 */
final class BookController
{
    /** Tên bộ sách hiện ở đầu nhóm trên giá sách, theo thứ tự hiện. */
    private const BO = ['hanngu' => 'Hán ngữ 6 tập', 'hsk' => 'Giáo trình HSK 3.0'];

    /** GET /learn/books — giá sách: chỉ phần tóm tắt, mục lục đầy đủ lấy khi mở từng quyển. */
    public function index(Request $r): array
    {
        $ra = [];
        foreach ($this->sachCho($r) as $s) {
            $ra[] = [
                'ma'       => $s['ma'],
                'bo'       => $s['bo'],
                'tenBo'    => self::BO[$s['bo']] ?? $s['bo'],
                'cap'      => $s['cap'],
                'ten'      => $s['ten'],
                'phu'      => $s['phu'],
                'phien'    => (string) ($s['phien'] ?? ''),
                'soTrang'  => $s['soTrang'],
                'soBai'    => count($s['bai']),
                'soNghe'   => count($s['nghe']),
                'phutNghe' => (int) round(array_sum(array_column($s['nghe'], 'giay')) / 60),
                'coDapAn'  => $s['dapAn'] !== null,
            ];
        }
        return $ra;
    }

    /** GET /learn/books/{ma} — mô tả đủ của một quyển để màn đọc dựng mục lục, danh sách nghe, đáp án. */
    public function show(Request $r): array
    {
        $s = $this->sach($r, $r->param('ma'));
        $s['tenBo'] = self::BO[$s['bo']] ?? $s['bo'];
        return $s;
    }

    /** GET /learn/books/{ma}/{trang} (và /admin/books/… cũ) — trang là số (1, 2…) hoặc "bia". */
    public function page(Request $r): Response
    {
        $ma = $r->param('ma');
        $this->sach($r, $ma);
        $trang = $r->param('trang');
        $file = $this->file($ma, $trang === 'bia' ? 'bia.webp' : sprintf('%03d.webp', (int) $trang));
        return $this->anh($file);
    }

    /** GET /learn/books/{ma}/dap-an/{trang} */
    public function answer(Request $r): Response
    {
        $ma = $r->param('ma');
        if ($this->sach($r, $ma)['dapAn'] === null) {
            throw HttpError::notFound();
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
        $this->sach($r, $ma);
        $file = $this->file($ma, sprintf('nghe/%02d.mp3', (int) $r->param('so')));
        return Response::fileRange($file, 'audio/mpeg', $r->header('range'), ['Cache-Control' => 'private, max-age=2592000']);
    }

    /** @return list<array<string,mixed>> các quyển người này được đọc, Hán ngữ trước rồi HSK, mỗi bộ theo cấp. */
    private function sachCho(Request $r): array
    {
        $bo = $r->isStaff() ? null : (Curricula::levelLabel(Curricula::ofUser($r->userId())) === 'Quyển' ? 'hanngu' : 'hsk');
        $ra = [];
        foreach (glob(Config::uploadsDir('giao-trinh') . '/*/sach.json') ?: [] as $f) {
            $s = json_decode((string) file_get_contents($f), true);
            if (is_array($s) && isset(self::BO[$s['bo'] ?? '']) && ($bo === null || $s['bo'] === $bo)) {
                $ra[] = $s;
            }
        }
        $thuTu = array_flip(array_keys(self::BO));
        usort($ra, static fn ($a, $b) => [$thuTu[$a['bo']], $a['cap']] <=> [$thuTu[$b['bo']], $b['cap']]);
        return $ra;
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
            // private: proxy/CDN không được giữ bản sao, chỉ trình duyệt của chính người xem.
            'Cache-Control'  => 'private, max-age=2592000',
        ], $file);
    }
}
