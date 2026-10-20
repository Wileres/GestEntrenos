<?php $pwa = \App\Services\SettingsService::pwa(); ?>
    <meta name="theme-color" content="<?= e($pwa['theme']) ?>">
    <meta name="application-name" content="<?= e($pwa['short']) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e($pwa['short']) ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="app-base" content="<?= e(\App\Helpers\Request::basePath()) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(\App\Services\IconService::url('favicon-32')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(\App\Services\IconService::url('favicon-16')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(\App\Services\IconService::url('apple-touch-icon')) ?>">
