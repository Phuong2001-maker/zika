<?php
declare(strict_types=1);

use Zika\Controllers\BookController as Book;
use Zika\Controllers\LearnController as Learn;
use Zika\Controllers\ProgressController as Prog;

// Nội dung học (04 §4) và tiến độ (04 §5).
return [
    ['GET',  '/learn/levels', [Learn::class, 'levels'], 'L'],
    ['GET',  '/learn/levels/{id}/lessons', [Learn::class, 'levelLessons'], 'L'],
    ['GET',  '/learn/lessons/{id}/{part:flashcards|exercises|test|writing|dialogue|grammar}', [Learn::class, 'lesson'], 'L'],
    ['GET',  '/learn/levels/{id}/review/{part:lt|bt|kt|lv|ht|np}', [Learn::class, 'review'], 'L'],
    // Ảnh trang giáo trình cho mọi tài khoản đã đăng nhập (người dùng mở Giáo trình cho học viên 08/11/2026).
    // Thư mục ảnh vẫn bị .htaccess chặn nên khách chưa đăng nhập không lấy được. Không tính giới hạn request:
    // cuộn một lượt là hàng trăm ảnh.
    ['GET',  '/learn/books', [Book::class, 'index'], 'L'],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}', [Book::class, 'show'], 'L'],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/{trang:bia|[0-9]{1,3}}', [Book::class, 'page'], 'L', ['rate' => false]],
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/dap-an/{trang:[0-9]{1,3}}', [Book::class, 'answer'], 'L', ['rate' => false]],
    // Trình duyệt hỏi file nghe thành nhiều đoạn (Range) khi tua, nên cũng không tính giới hạn request.
    ['GET',  '/learn/books/{ma:(?:hsk|hn)[1-6]}/nghe/{so:[0-9]{1,3}}', [Book::class, 'audio'], 'L', ['rate' => false]],

    ['GET',  '/progress/home', [Prog::class, 'home'], 'L'],
    ['GET',  '/progress/levels/{id}', [Prog::class, 'level'], 'L'],
    ['PUT',  '/progress/lessons/{id}/{part:[a-z]{2}}', [Prog::class, 'save'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'complete'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/restart', [Prog::class, 'restart'], 'L'],
    ['PUT',  '/progress/review/{id}/{part:[a-z]{2}}', [Prog::class, 'saveReview'], 'L'],
    ['POST', '/progress/review/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'completeReview'], 'L'],
    ['POST', '/progress/beacon', [Prog::class, 'beacon'], 'L'],
];
