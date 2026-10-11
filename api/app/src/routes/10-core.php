<?php
declare(strict_types=1);

use Zika\Controllers\AuthController as AuthC;
use Zika\Controllers\InstallController as Inst;
use Zika\Controllers\MeController as Me;
use Zika\Controllers\PublicController as Pub;

// Công khai, xác thực, tài khoản của tôi, phiên truy cập (04 §2–3).
return [
    ['GET',    '/public/config', [Pub::class, 'config'], 'P'],
    ['GET',    '/public/pages/{slug:privacy|terms}', [Pub::class, 'page'], 'P'],

    ['POST',   '/auth/register', [AuthC::class, 'register'], 'P'],
    ['POST',   '/auth/login', [AuthC::class, 'login'], 'P'],
    ['POST',   '/auth/logout', [AuthC::class, 'logout'], 'L'],
    ['POST',   '/auth/forgot', [AuthC::class, 'forgot'], 'P'],
    ['POST',   '/auth/reset', [AuthC::class, 'reset'], 'P'],
    ['POST',   '/auth/verify-email', [AuthC::class, 'verifyEmail'], 'P'],
    ['POST',   '/auth/resend-verification', [AuthC::class, 'resendVerification'], 'P'],
    ['GET',    '/auth/google/start', [AuthC::class, 'googleStart'], 'P'],
    ['GET',    '/auth/google/callback', [AuthC::class, 'googleCallback'], 'P'],
    ['GET',    '/auth/invite/{token:[A-Za-z0-9_-]{20,100}}', [AuthC::class, 'inviteInfo'], 'P'],
    ['POST',   '/auth/invite/accept', [AuthC::class, 'inviteAccept'], 'L'],

    ['GET',    '/install', [Inst::class, 'form'], 'P'],
    ['POST',   '/install', [Inst::class, 'run'], 'P', ['csrf' => false]],

    ['GET',    '/me', [Me::class, 'show'], 'L'],
    ['PATCH',  '/me', [Me::class, 'update'], 'L'],
    ['DELETE', '/me', [Me::class, 'destroy'], 'L'],
    ['POST',   '/me/avatar', [Me::class, 'avatar'], 'L'],
    ['PUT',    '/me/password', [Me::class, 'password'], 'L'],
    ['GET',    '/me/sessions', [Me::class, 'sessions'], 'L'],
    ['DELETE', '/me/sessions/{id}', [Me::class, 'revokeSession'], 'L'],
    ['GET',    '/me/settings', [Me::class, 'settings'], 'L'],
    ['PUT',    '/me/settings', [Me::class, 'saveSettings'], 'L'],

    ['POST',   '/visit/ping', [Me::class, 'ping'], 'L'],
    ['POST',   '/visit/leave', [Me::class, 'leave'], 'L'],
    ['POST',   '/announcements/{id}/{action:seen|dismiss|click}', [Me::class, 'announcement'], 'L'],
];
