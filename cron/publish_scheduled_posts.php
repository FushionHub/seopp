<?php
declare(strict_types=1);

// CLI-only: these scripts run privileged cross-user jobs and must never be
// reachable over HTTP. Scheduled from crontab / Task Scheduler only.
if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); }
    echo 'Forbidden: cron jobs are CLI-only.' . PHP_EOL;
    exit;
}

/**
 * Cron Job: Publish Scheduled Posts
 * Adsbooster.net
 *
 * Finds scheduled_posts where status = 'scheduled' AND scheduled_time <= NOW(),
 * dispatches each one through SocialPostEngine against the user's connected
 * account, and records the provider's real response.
 *
 * This job previously called simulatePublish(), which invented a random
 * "platform_post_id" and marked the row published without contacting any
 * social network at all. Rows now carry the external_id the provider returned
 * and, on failure, the exact error text.
 *
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/apikeys.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../modules/social_post_engine.php';

$jobName = 'publish_scheduled_posts';
$logId   = null;

try {
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => date('Y-m-d H:i:s'),
        'status'     => 'running',
    ]);

    $posts = db()->fetchAll(
        "SELECT id, user_id, platform, caption, media_url, media_type
         FROM scheduled_posts
         WHERE status = 'scheduled' AND scheduled_time <= NOW()
         ORDER BY scheduled_time ASC
         LIMIT 50"
    );

    $published = 0;
    $failed    = 0;
    $errors    = [];

    foreach ($posts as $post) {
        try {
            // Claim the row so a concurrent run cannot double-publish it.
            $stmt = db()->query(
                "UPDATE scheduled_posts SET status = 'publishing', updated_at = ? WHERE id = ? AND status = 'scheduled'",
                [date('Y-m-d H:i:s'), $post['id']]
            );
            if ((int)$stmt->rowCount() === 0) {
                continue;
            }

            $result = SocialPostEngine::publishScheduledRow((int)$post['id']);

            if (!empty($result['success'])) {
                $published++;
            } else {
                $failed++;
                $errors[] = sprintf(
                    '#%d(%s): %s',
                    (int)$post['id'],
                    (string)$post['platform'],
                    mb_substr((string)($result['error'] ?? 'unknown error'), 0, 120)
                );
            }
        } catch (\Throwable $e) {
            $failed++;
            $errors[] = '#' . (int)$post['id'] . ': ' . mb_substr($e->getMessage(), 0, 120);
            error_log("publish_scheduled_posts: Failed post {$post['id']}: " . $e->getMessage());
            try {
                db()->update('scheduled_posts', [
                    'status'        => 'failed',
                    'publish_error' => mb_substr($e->getMessage(), 0, 500),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [$post['id']]);
            } catch (\Throwable $e2) {
                error_log('publish_scheduled_posts: could not mark failed: ' . $e2->getMessage());
            }
        }
    }

    $note = sprintf('Processed %d due post(s). Published: %d, Failed: %d.', count($posts), $published, $failed);
    if ($errors) {
        $note .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 10)) . '.';
    }

    db()->update('cron_run_logs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status'      => $failed > 0 && $published === 0 ? 'failed' : 'success',
        'notes'       => $note,
    ], 'id = ?', [$logId]);

    echo "publish_scheduled_posts: {$note}\n";
    exit($failed > 0 && $published === 0 ? 1 : 0);
} catch (\Throwable $e) {
    error_log('publish_scheduled_posts: ' . $e->getMessage());
    if ($logId !== null) {
        try {
            db()->update('cron_run_logs', [
                'finished_at' => date('Y-m-d H:i:s'),
                'status'      => 'failed',
                'notes'       => $e->getMessage(),
            ], 'id = ?', [$logId]);
        } catch (\Throwable $logError) {
            error_log('Failed to log cron error: ' . $logError->getMessage());
        }
    }
    exit(1);
}
