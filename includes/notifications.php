<?php
/**
 * includes/notifications.php
 * ---------------------------------------------------------------------------
 * Renders flash messages stored in the session.
 *
 * Flash shape:
 *   $_SESSION['flash'][] = ['type' => 'success'|'error'|'warning'|'info',
 *                           'message' => '...'];
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

require_once __DIR__ . '/functions.php';

$flashes = get_flashes();
if (empty($flashes)) {
    return;
}

$allowed = ['success', 'error', 'warning', 'info'];
?>
<div class="flash-container" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <?php $type = in_array($flash['type'] ?? '', $allowed, true) ? $flash['type'] : 'info'; ?>
        <div class="flash flash-<?= e($type) ?>" data-flash>
            <span class="flash__message"><?= e($flash['message'] ?? '') ?></span>
            <button type="button" class="flash__close" aria-label="Dismiss message">×</button>
        </div>
    <?php endforeach; ?>
</div>