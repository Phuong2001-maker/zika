<?php
declare(strict_types=1);

use Zika\Controllers\BookController as Book;
use Zika\Controllers\BookPointsController as Points;
use Zika\Controllers\ChatController as Chat;
use Zika\Controllers\FeedbackController as Feedback;
use Zika\Controllers\Hsk30Controller as Hsk30;
use Zika\Controllers\LearnController as Learn;
use Zika\Controllers\ProgressController as Prog;

// Nội dung học (04 §4) và tiến độ (04 §5).
return [
    ['GET',  '/learn/levels', [Learn::class, 'levels'], 'P'],
    // Thi thử HSK 3.0: kho đề chung, không theo giáo trình (migration 027).
    ['GET',  '/learn/hsk30', [Hsk30::class, 'index'], 'P'],
    ['GET',  '/learn/levels/{id}/lessons', [Learn::class, 'levelLessons'], 'P'],
    ['GET',  '/learn/lessons/{id}/{part:flashcards|exercises|test|writing|dialogue|grammar}', [Learn::class, 'lesson'], 'P'],
    ['GET',  '/learn/levels/{id}/review/{part:lt|bt|kt|lv|ht|np}', [Learn::class, 'review'], 'L'],
    // Ảnh trang giáo trình: kèm mã ký ?t= và bài đó đã mở (BookAccess). Khách chưa đăng nhập chỉ đọc quyển đầu của bộ
    // sách thuộc giáo trình mặc định (trangThaiKhach); mở bài bằng điểm vẫn phải đăng nhập.
    // Thư mục ảnh bị .htaccess chặn nên không có lối nào khác. Không tính giới hạn request: cuộn một lượt là hàng trăm ảnh.
    ['GET',  '/learn/books', [Book::class, 'index'], 'P'],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}', [Book::class, 'show'], 'P'],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/quyen', [Book::class, 'access'], 'P'],
    ['POST', '/learn/books/{ma:(?:hsk|hn)[1-6]}/mo/{bai:[0-9]{1,3}}', [Book::class, 'unlock'], 'L'],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/{trang:bia|[0-9]{1,3}}', [Book::class, 'page'], 'P', ['rate' => false]],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/dap-an/{trang:[0-9]{1,3}}', [Book::class, 'answer'], 'P', ['rate' => false]],
    // Trình duyệt hỏi file nghe thành nhiều đoạn (Range) khi tua, nên cũng không tính giới hạn request.
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/nghe/{so:[0-9]{1,3}}', [Book::class, 'audio'], 'P', ['rate' => false]],

    // Điểm mở bài (migration 019).
    ['GET',  '/learn/points', [Points::class, 'points'], 'L'],
    ['POST', '/learn/points/daily', [Points::class, 'daily'], 'L'],

    // Góp ý · báo lỗi từ bảng Chuyển nhanh (07 §1.3).
    ['POST', '/feedback', [Feedback::class, 'create'], 'L'],
    ['GET',  '/feedback/mine', [Feedback::class, 'mine'], 'L'],

    // Quán trà — phòng trò chuyện chia bàn theo chủ đề (migration 020, 021). Web hỏi tin mới vài giây một lần khi tab mở.
    ['GET',    '/chat', [Chat::class, 'rooms'], 'L'],
    ['GET',    '/chat/ban/{ban:[a-z0-9-]{1,40}}', [Chat::class, 'index'], 'L'],
    ['POST',   '/chat/ban/{ban:[a-z0-9-]{1,40}}', [Chat::class, 'create'], 'L'],
    ['DELETE', '/chat/tin/{id}', [Chat::class, 'delete'], 'L'],
    ['POST',   '/chat/tin/{id}/report', [Chat::class, 'report'], 'L'],

    ['GET',  '/progress/home', [Prog::class, 'home'], 'L'],
    ['GET',  '/progress/levels/{id}', [Prog::class, 'level'], 'L'],
    ['PUT',  '/progress/lessons/{id}/{part:[a-z]{2}}', [Prog::class, 'save'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'complete'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/restart', [Prog::class, 'restart'], 'L'],
    ['PUT',  '/progress/review/{id}/{part:[a-z]{2}}', [Prog::class, 'saveReview'], 'L'],
    ['POST', '/progress/review/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'completeReview'], 'L'],
    ['POST', '/progress/beacon', [Prog::class, 'beacon'], 'L'],
];
