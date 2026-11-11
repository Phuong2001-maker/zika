<?php
declare(strict_types=1);

use Zika\Controllers\ExerciseController as Ex;
use Zika\Controllers\TestController as Test;
use Zika\Controllers\WritingController as Writing;

// Làm bài tập (04 §6), kiểm tra & thi thử (04 §7), luyện viết (04 §8).
return [
    ['POST',  '/exercises/attempts', [Ex::class, 'start'], 'L'],
    ['GET',   '/exercises/attempts/{id}', [Ex::class, 'show'], 'L'],
    ['PATCH', '/exercises/attempts/{id}', [Ex::class, 'answer'], 'L'],
    ['POST',  '/exercises/attempts/{id}/finish', [Ex::class, 'finish'], 'L'],

    ['GET',   '/tests/lessons/{id}/summary', [Test::class, 'lessonSummary'], 'L'],
    ['GET',   '/tests/mock/{id}/summary', [Test::class, 'mockSummary'], 'L'],
    // Khách học thử làm được Kiểm tra / Thi thử HSK 3.0 cấp 1: lượt gắn cookie zk_khach (TestService::startKhach).
    ['POST',  '/tests/start', [Test::class, 'start'], 'P'],
    ['GET',   '/tests/attempts/{id}', [Test::class, 'show'], 'P'],
    ['PATCH', '/tests/attempts/{id}', [Test::class, 'save'], 'P'],
    ['POST',  '/tests/attempts/{id}/submit', [Test::class, 'submit'], 'P'],
    ['POST',  '/tests/attempts/{id}/check', [Test::class, 'check'], 'P'],

    ['POST',  '/writing/result', [Writing::class, 'result'], 'L'],
    ['GET',   '/writing/weak', [Writing::class, 'weak'], 'L'],
];
