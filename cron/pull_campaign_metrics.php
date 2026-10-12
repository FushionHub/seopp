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
 * Cron Job: Pull Campaign Metrics
 * Adsbooster.net
 *
 * Writes real per-day performance rows into campaign_metrics_daily.
 *
 * IMPORTANT: this job deliberately writes NOTHING unless a genuine metrics
 * source supplies the numbers. An earlier version fabricated impressions,
 * clicks and spend with rand(), and those invented figures were then shown on
 * the analytics dashboard, used to pick A/B test winners and to trigger
 * achievements. Inventing ad performance data is worse than showing none.
 *
 * A metrics source is any row in connected_accounts that carries a usable
 * access token for a platform we can actually query. When no such connection
 * exists the run is still logged as a success, with an explanatory note, so
 * operators can see the job is healthy and simply has no data to pull.
 *
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/db.php';

$jobName = 'pull_campaign_metrics';
$logId   = null;

try {
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => date('Y-m-d H:i:s'),
        'status'     => 'running',
    ]);

    $campaigns = db()->fetchAll(
        "SELECT id, campaign_name, campaign_type, budget_amount, budget_type, user_id
         FROM ad_campaigns
         WHERE status = 'active'
         ORDER BY id ASC"
    );

    if (!$campaigns) {
        finish($logId, 'success', 'No active campaigns; nothing to pull.');
        echo "pull_campaign_metrics: no active campaigns.\n";
        exit(0);
    }

    // Group active campaigns by owning user so one connection can serve many.
    $byUser = [];
    foreach ($campaigns as $c) {
        $byUser[(int)$c['user_id']][] = $c;
    }

    $connections = db()->fetchAll(
        "SELECT user_id, platform, access_token, token_expiry
         FROM connected_accounts
         WHERE access_token IS NOT NULL AND access_token <> ''
           AND (token_expiry IS NULL OR token_expiry > NOW())"
    );
    $connByUser = [];
    foreach ($connections as $row) {
        $connByUser[(int)$row['user_id']][] = $row;
    }

    $today         = date('Y-m-d');
    $written       = 0;
    $skipped       = 0;
    $failed        = 0;
    $missingSource = [];

    foreach ($byUser as $userId => $userCampaigns) {
        $conns = $connByUser[$userId] ?? [];

        foreach ($userCampaigns as $campaign) {
            $metrics = null;

            foreach ($conns as $conn) {
                $metrics = fetchRealMetrics($conn, $campaign, $today);
                if ($metrics !== null) {
                    break;
                }
            }

            if ($metrics === null) {
                $skipped++;
                $missingSource[] = (int)$campaign['id'];
                continue;
            }

            try {
                $existing = db()->fetchOne(
                    "SELECT id FROM campaign_metrics_daily
                     WHERE campaign_id = ? AND variant_id IS NULL AND date = ?",
                    [$campaign['id'], $today]
                );

                $row = [
                    'campaign_id' => $campaign['id'],
                    'date'        => $today,
                    'impressions' => $metrics['impressions'],
                    'clicks'      => $metrics['clicks'],
                    'spend'       => $metrics['spend'],
                    'conversions' => $metrics['conversions'],
                    'ctr'         => $metrics['ctr'],
                    'cpc'         => $metrics['cpc'],
                ];

                if ($existing) {
                    db()->update('campaign_metrics_daily', $row, 'id = ?', [$existing['id']]);
                } else {
                    db()->insert('campaign_metrics_daily', $row);
                }
                $written++;
            } catch (\Throwable $e) {
                $failed++;
                error_log("pull_campaign_metrics: write failed for campaign {$campaign['id']}: " . $e->getMessage());
            }
        }
    }

    $note = sprintf(
        'Wrote %d real metric row(s); skipped %d campaign(s) with no connected metrics source; %d failed.%s',
        $written,
        $skipped,
        $failed,
        $missingSource
            ? ' Campaign ids without a source: ' . implode(', ', array_slice($missingSource, 0, 20)) . '.'
            : ''
    );
    if ($written === 0) {
        $note .= ' Nothing was invented — connect a platform account to start receiving performance data.';
    }

    finish($logId, $failed > 0 && $written === 0 ? 'failed' : 'success', $note);
    echo "pull_campaign_metrics: {$note}\n";
    exit($failed > 0 && $written === 0 ? 1 : 0);
} catch (\Throwable $e) {
    error_log('pull_campaign_metrics: ' . $e->getMessage());
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

function finish(?int $logId, string $status, string $note): void
{
    if ($logId === null) {
        return;
    }
    db()->update('cron_run_logs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status'      => $status,
        'notes'       => $note,
    ], 'id = ?', [$logId]);
}

/**
 * Ask a connected platform for this campaign's metrics for $date.
 *
 * Returns a normalised metrics array, or null when the connection cannot
 * produce data for this campaign (unsupported platform, missing linkage,
 * API error, ...). Never guesses or synthesises numbers.
 *
 * Supported today: Meta Marketing API insights, keyed by campaign name
 * because ad_campaigns has no native platform campaign id column.
 */
function fetchRealMetrics(array $conn, array $campaign, string $date): ?array
{
    $platform = strtolower((string)$conn['platform']);
    $token    = (string)$conn['access_token'];

    if ($token === '') {
        return null;
    }

    // Only Meta has a metrics integration wired up here.
    if (!in_array($platform, ['meta', 'facebook', 'instagram'], true)) {
        return null;
    }

    $name = trim((string)$campaign['campaign_name']);
    if ($name === '') {
        return null;
    }

    $level = ($campaign['campaign_type'] ?? '') === 'instagram' ? 'adset' : 'campaign';
    $url   = 'https://graph.facebook.com/v19.0/act_' . urlencode($conn['platform_user_id'] ?? '')
        . '/insights?level=' . $level
        . '&fields=campaign_name,impressions,clicks,spend,actions'
        . '&time_range=' . urlencode(json_encode(['since' => $date, 'until' => $date]))
        . '&limit=100&access_token=' . urlencode($token);

    $res = httpGetJson($url);
    if ($res['code'] !== 200) {
        error_log('pull_campaign_metrics: Meta insights HTTP ' . $res['code'] . ' ' . $res['body']);
        return null;
    }

    $data = $res['json']['data'] ?? [];
    if (!is_array($data) || !$data) {
        return null;
    }

    foreach ($data as $entry) {
        if (trim((string)($entry['campaign_name'] ?? '')) !== $name) {
            continue;
        }

        $impressions = (int)($entry['impressions'] ?? 0);
        $clicks      = (int)($entry['clicks'] ?? 0);
        $spend       = round((float)($entry['spend'] ?? 0), 2);

        $conversions = 0;
        foreach (($entry['actions'] ?? []) as $action) {
            if (($action['action_type'] ?? '') === 'offsite_conversion.fb_pixel_purchase') {
                $conversions += (int)($action['value'] ?? 0);
            }
        }

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'spend'       => $spend,
            'conversions' => $conversions,
            'ctr'         => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
            'cpc'         => $clicks > 0 ? round($spend / $clicks, 2) : 0.0,
        ];
    }

    return null;
}

function httpGetJson(string $url): array
{
    $bundle = function_exists('ca_bundle_found') ? ca_bundle_found() : '';
    $verify = $bundle !== '';

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'AdsboosterMetricsCron/2.0',
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ];
    if ($verify) {
        $opts[CURLOPT_CAINFO] = $bundle;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $code,
        'body' => is_string($body) ? $body : '',
        'json' => is_string($body) ? (json_decode($body, true) ?: []) : [],
    ];
}
