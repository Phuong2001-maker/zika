<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;

/**
 * Ảnh trang giáo trình HSK 1–3. Sách có bản quyền nên ảnh không để công khai ở /uploads:
 * .htaccess chặn thẳng thư mục giao-trinh, chỉ còn lối này, và route đã đòi quyền quản trị.
 */
final class BookController
{
    /** GET /admin/books/{ma}/{trang} — trang là số (1, 2…) hoặc "bia". */
    public function page(Request $r): Response
    {
        $ma = $r->param('ma');
        $trang = $r->param('trang');
        $ten = $trang === 'bia' ? 'bia.webp' : sprintf('%03d.webp', (int) $trang);

        $root = realpath(Config::uploadsDir('giao-trinh'));
        $file = realpath(Config::uploadsDir("giao-trinh/$ma/$ten"));
        if ($root === false || $file === false || !str_starts_with($file, $root) || !is_file($file)) {
            throw HttpError::notFound();
        }

        return new Response(200, '', [
            'Content-Type'   => 'image/webp',
            'Content-Length' => (string) filesize($file),
            // private: proxy/CDN không được giữ bản sao, chỉ trình duyệt của chính người xem.
            'Cache-Control'  => 'private, max-age=2592000',
        ], $file);
    }
}
