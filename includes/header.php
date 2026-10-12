<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------------------
 * Shared HTML head and opening body markup.
 *
 * Expected variables (all optional):
 *   $pageTitle  string   Browser tab title.
 *   $pageCss    array    Extra CSS files (relative to assets/css/).
 *   $pageJs     array    Extra JS files (relative to assets/js/, deferred).
 *   $bodyClass  string   Optional class on <body>.
 *
 * Loads:
 *   - global.css
 *   - header.css
 *   - page-specific CSS
 * Then renders navigation and flash messages.
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'TrashWise';
$pageCss   = $pageCss   ?? [];
$pageJs    = $pageJs    ?? [];
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TrashWise — Community Waste Management System for Minglanilla, Cebu">
    <meta name="theme-color" content="#2C6705">

    <title><?= e($pageTitle) ?> — TrashWise</title>

    <link rel="icon" type="image/png" href="<?= e(BASE_URL) ?>/assets/images/trashwiselogo.png">

    <!-- Global styles -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/header.css">

    <!-- Page-specific styles -->
    <?php foreach ($pageCss as $css): ?>
        <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/<?= e($css) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/navigation.php'; ?>

<main class="app-main">
<?php require __DIR__ . '/notifications.php'; ?>