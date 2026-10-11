<?php
declare(strict_types=1);

use Zika\Controllers\LearnController as Learn;
use Zika\Controllers\ProgressController as Prog;

// Nội dung học (04 §4) và tiến độ (04 §5).
return [
    ['GET',  '/learn/levels', [Learn::class, 'levels'], 'L'],
    ['GET',  '/learn/levels/{id}/lessons', [Learn::class, 'levelLessons'], 'L'],
    ['GET',  '/learn/lessons/{id}/{part:flashcards|exercises|test|writing|dialogue|grammar}', [Learn::class, 'lesson'], 'L'],
    ['GET',  '/learn/levels/{id}/review/{part:lt|bt|kt|lv|ht|np}', [Learn::class, 'review'], 'L'],

    ['GET',  '/progress/home', [Prog::class, 'home'], 'L'],
    ['GET',  '/progress/levels/{id}', [Prog::class, 'level'], 'L'],
    ['PUT',  '/progress/lessons/{id}/{part:[a-z]{2}}', [Prog::class, 'save'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'complete'], 'L'],
    ['POST', '/progress/lessons/{id}/{part:[a-z]{2}}/restart', [Prog::class, 'restart'], 'L'],
    ['PUT',  '/progress/review/{id}/{part:[a-z]{2}}', [Prog::class, 'saveReview'], 'L'],
    ['POST', '/progress/review/{id}/{part:[a-z]{2}}/complete', [Prog::class, 'completeReview'], 'L'],
    ['POST', '/progress/beacon', [Prog::class, 'beacon'], 'L'],
];
