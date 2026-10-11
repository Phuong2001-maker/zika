<?php
declare(strict_types=1);

use Zika\Controllers\Admin\ContentController as Content;
use Zika\Controllers\Admin\DialogueController as Dialogue;
use Zika\Controllers\Admin\MiscController as Misc;
use Zika\Controllers\Admin\OpsController as Ops;
use Zika\Controllers\Admin\StatsController as Stats;
use Zika\Controllers\Admin\QuestionController as Question;
use Zika\Controllers\Admin\WritingController as Writing;

// Soạn nội dung (04 §10.1, §10.3–§10.6). Quyền E = biên tập + quản trị.
return [
    // Chung
    ['GET',  '/admin/badges', [Misc::class, 'badges'], 'E'],
    ['GET',  '/admin/staff', [Misc::class, 'staff'], 'E'],
    ['GET',  '/admin/dict', [Misc::class, 'dict'], 'E'],
    ['POST', '/admin/media/image', [Misc::class, 'image'], 'E'],
    ['POST', '/admin/media/audio', [Misc::class, 'audio'], 'E'],

    // Cấp
    ['GET',   '/admin/levels', [Content::class, 'levels'], 'E'],
    ['PATCH', '/admin/levels/{id}', [Content::class, 'updateLevel'], 'A'],

    // Bài học
    ['GET',    '/admin/levels/{id}/lessons', [Content::class, 'lessons'], 'E'],
    ['POST',   '/admin/levels/{id}/lessons', [Content::class, 'createLesson'], 'E'],
    ['PUT',    '/admin/levels/{id}/lessons/order', [Content::class, 'reorderLessons'], 'E'],
    ['GET',    '/admin/lessons/{id}', [Content::class, 'lesson'], 'E'],
    ['PATCH',  '/admin/lessons/{id}', [Content::class, 'updateLesson'], 'E'],
    ['DELETE', '/admin/lessons/{id}', [Content::class, 'deleteLesson'], 'E'],

    // Từ vựng
    ['GET',    '/admin/words', [Content::class, 'words'], 'E'],
    ['POST',   '/admin/words', [Content::class, 'createWord'], 'E'],
    ['POST',   '/admin/words/bulk', [Content::class, 'bulkWords'], 'E'],
    ['GET',    '/admin/words/{id}', [Content::class, 'word'], 'E'],
    ['PUT',    '/admin/words/{id}', [Content::class, 'updateWord'], 'E'],
    ['DELETE', '/admin/words/{id}', [Content::class, 'deleteWord'], 'E'],
    ['PUT',    '/admin/lessons/{id}/words/order', [Content::class, 'reorderWords'], 'E'],

    // Bài tập · Kiểm tra
    ['GET',    '/admin/lessons/{id}/questions', [Question::class, 'index'], 'E'],
    ['POST',   '/admin/lessons/{id}/questions', [Question::class, 'create'], 'E'],
    ['PUT',    '/admin/lessons/{id}/questions/order', [Question::class, 'reorder'], 'E'],
    ['POST',   '/admin/lessons/{id}/questions/copy-from-bt', [Question::class, 'copyFromBt'], 'E'],
    ['PUT',    '/admin/questions/{id}', [Question::class, 'update'], 'E'],
    ['DELETE', '/admin/questions/{id}', [Question::class, 'delete'], 'E'],
    ['PUT',    '/admin/lessons/{id}/test-config', [Question::class, 'testConfig'], 'E'],

    // Thi thử
    ['GET',    '/admin/levels/{id}/mock-tests', [Question::class, 'mockTests'], 'E'],
    ['POST',   '/admin/levels/{id}/mock-tests', [Question::class, 'createMock'], 'E'],
    ['PATCH',  '/admin/mock-tests/{id}', [Question::class, 'updateMock'], 'E'],
    ['DELETE', '/admin/mock-tests/{id}', [Question::class, 'deleteMock'], 'E'],
    ['GET',    '/admin/mock-tests/{id}/questions', [Question::class, 'mockQuestions'], 'E'],
    ['POST',   '/admin/mock-tests/{id}/questions', [Question::class, 'createMockQuestion'], 'E'],
    ['PUT',    '/admin/mock-tests/{id}/questions/order', [Question::class, 'reorderMockQuestions'], 'E'],
    ['POST',   '/admin/mock-tests/{id}/copy-from-tests', [Question::class, 'copyIntoMockTest'], 'E'],

    // Luyện viết
    ['GET',    '/admin/lessons/{id}/writing', [Writing::class, 'index'], 'E'],
    ['POST',   '/admin/lessons/{id}/writing/from-words', [Writing::class, 'fromWords'], 'E'],
    ['POST',   '/admin/lessons/{id}/writing/chars', [Writing::class, 'addChar'], 'E'],
    ['PUT',    '/admin/lessons/{id}/writing/order', [Writing::class, 'reorder'], 'E'],
    ['PUT',    '/admin/lessons/{id}/writing/config', [Writing::class, 'config'], 'E'],
    ['PATCH',  '/admin/lesson-chars/{id}', [Writing::class, 'updateLessonChar'], 'E'],
    ['DELETE', '/admin/lesson-chars/{id}', [Writing::class, 'deleteLessonChar'], 'E'],
    ['POST',   '/admin/characters/{id}/stroke-data', [Writing::class, 'uploadStroke'], 'E'],
    ['POST',   '/admin/characters/{id}/stroke-data/fetch', [Writing::class, 'fetchStroke'], 'E'],

    // Hội thoại · Ngữ pháp
    ['GET',    '/admin/lessons/{id}/dialogue', [Dialogue::class, 'get'], 'E'],
    ['PUT',    '/admin/lessons/{id}/dialogue', [Dialogue::class, 'save'], 'E'],
    ['GET',    '/admin/lessons/{id}/grammar', [Dialogue::class, 'grammar'], 'E'],
    ['POST',   '/admin/lessons/{id}/grammar', [Dialogue::class, 'createGrammar'], 'E'],
    ['PUT',    '/admin/lessons/{id}/grammar/order', [Dialogue::class, 'reorderGrammar'], 'E'],
    ['PUT',    '/admin/grammar/{id}', [Dialogue::class, 'updateGrammar'], 'E'],
    ['DELETE', '/admin/grammar/{id}', [Dialogue::class, 'deleteGrammar'], 'E'],

    // Tổng quan · Người dùng · Phiên truy cập (04 §10.2) — chỉ quản trị
    ['GET',    '/admin/overview', [Stats::class, 'overview'], 'A'],
    ['GET',    '/admin/users', [Stats::class, 'users'], 'A'],
    ['POST',   '/admin/users/bulk', [Stats::class, 'bulk'], 'A'],
    ['GET',    '/admin/users/{id}', [Stats::class, 'user'], 'A'],
    ['DELETE', '/admin/users/{id}', [Stats::class, 'delete'], 'A'],
    ['GET',    '/admin/users/{id}/visits', [Stats::class, 'visits'], 'A'],
    ['POST',   '/admin/users/{id}/lock', [Stats::class, 'lock'], 'A'],
    ['POST',   '/admin/users/{id}/unlock', [Stats::class, 'unlock'], 'A'],
    ['POST',   '/admin/users/{id}/notify', [Stats::class, 'notify'], 'A'],
    ['PATCH',  '/admin/users/{id}/role', [Stats::class, 'role'], 'A'],
    ['GET',    '/admin/sessions', [Stats::class, 'sessions'], 'A'],

    // Thông báo (04 §10.8)
    ['GET',    '/admin/announcements', [Ops::class, 'announcements'], 'A'],
    ['POST',   '/admin/announcements', [Ops::class, 'saveAnnouncement'], 'A'],
    ['GET',    '/admin/announcements/targets', [Ops::class, 'targets'], 'A'],
    ['PUT',    '/admin/announcements/{id}', [Ops::class, 'saveAnnouncement'], 'A'],
    ['DELETE', '/admin/announcements/{id}', [Ops::class, 'deleteAnnouncement'], 'A'],

    // Góp ý (04 §10.9) — biên tập chỉ thấy nhóm nội dung
    ['GET',    '/admin/feedback', [Ops::class, 'feedback'], 'E'],
    ['GET',    '/admin/feedback/{id}', [Ops::class, 'feedbackDetail'], 'E'],
    ['PATCH',  '/admin/feedback/{id}', [Ops::class, 'updateFeedback'], 'E'],
    ['POST',   '/admin/feedback/{id}/reply', [Ops::class, 'replyFeedback'], 'E'],

    // Ủng hộ & Shopee (04 §10.10)
    ['GET',    '/admin/support', [Ops::class, 'support'], 'A'],
    ['PATCH',  '/admin/support', [Ops::class, 'toggleSupport'], 'A'],
    ['POST',   '/admin/support/links', [Ops::class, 'saveLink'], 'A'],
    ['PUT',    '/admin/support/links/order', [Ops::class, 'reorderLinks'], 'A'],
    ['PUT',    '/admin/support/links/{id}', [Ops::class, 'saveLink'], 'A'],
    ['PATCH',  '/admin/support/links/{id}', [Ops::class, 'toggleLink'], 'A'],
    ['DELETE', '/admin/support/links/{id}', [Ops::class, 'deleteLink'], 'A'],
    ['PUT',    '/admin/support/donate', [Ops::class, 'donate'], 'A'],

    // Cài đặt (04 §10.11)
    ['GET',    '/admin/settings', [Ops::class, 'settings'], 'A'],
    ['PUT',    '/admin/settings', [Ops::class, 'saveSettings'], 'A'],
    ['DELETE', '/admin/staff/{id}', [Ops::class, 'removeStaff'], 'A'],
];
