<?php declare(strict_types=1);

/**
 * REST API v1: Campaigns Endpoint
 * Adsbooster.net
 *
 * GET    /api/v1/campaigns.php           - List user's campaigns
 * GET    /api/v1/campaigns.php?id={id}   - Retrieve single campaign
 * POST   /api/v1/campaigns.php           - Create new ad campaign
 * PUT    /api/v1/campaigns.php?id={id}   - Update / Pause / Resume campaign
 */

require_once __DIR__ . '/bootstrap.php';

$userId = (int)$auth['user']['id'];
$campaignId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($segments[1]) ? (int)$segments[1] : null);

switch ($method) {
    case 'GET':
        handleListCampaigns($userId, $campaignId);
        break;
    case 'POST':
        handleCreateCampaign($userId);
        break;
    case 'PUT':
    case 'PATCH':
        if ($campaignId === null) {
            jsonResponse(400, ['success' => false, 'error' => 'Campaign ID is required (e.g. ?id=123).']);
        }
        handleUpdateCampaign($userId, $campaignId);
        break;
    default:
        jsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
}

function handleListCampaigns(int $userId, ?int $campaignId): void
{
    if ($campaignId !== null) {
        $campaign = db()->fetchOne(
            "SELECT id, campaign_name, campaign_type, objective, budget_type,
                    budget_amount, status, autopilot_enabled, created_at, updated_at
             FROM ad_campaigns
             WHERE id = ? AND user_id = ?",
            [$campaignId, $userId]
        );

        if ($campaign === null) {
            jsonResponse(404, ['success' => false, 'error' => 'Campaign not found.']);
        }

        jsonResponse(200, ['success' => true, 'campaign' => $campaign]);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? null;

    $sql = "SELECT id, campaign_name, campaign_type, objective, budget_type,
                   budget_amount, status, autopilot_enabled, created_at, updated_at
            FROM ad_campaigns
            WHERE user_id = ?";
    $params = [$userId];

    if ($status !== null && in_array($status, ['pending', 'active', 'paused', 'completed', 'rejected'], true)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $total = db()->count('ad_campaigns', 'user_id = ?' . ($status !== null ? " AND status = '" . $status . "'" : ''), [$userId]);

    $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    $campaigns = db()->fetchAll($sql, $params);

    jsonResponse(200, [
        'success'    => true,
        'campaigns'  => $campaigns,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

function handleCreateCampaign(int $userId): void
{
    $body = getRequestBody();

    $required = ['campaign_name', 'campaign_type'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonResponse(400, ['success' => false, 'error' => "Missing required field: {$field}"]);
        }
    }

    $validTypes = ['meta', 'instagram', 'tiktok', 'google_ads', 'snapchat', 'combo_pack'];
    if (!in_array($body['campaign_type'], $validTypes, true)) {
        jsonResponse(400, ['success' => false, 'error' => 'Invalid campaign type. Valid: ' . implode(', ', $validTypes)]);
    }

    $budgetType = $body['budget_type'] ?? 'daily';
    $budgetAmount = (float)($body['budget_amount'] ?? $body['budget'] ?? 10.00);
    if ($budgetAmount <= 0) {
        jsonResponse(400, ['success' => false, 'error' => 'Budget amount must be greater than zero.']);
    }

    $campaignId = db()->insert('ad_campaigns', [
        'user_id'            => $userId,
        'campaign_name'      => trim($body['campaign_name']),
        'campaign_type'      => $body['campaign_type'],
        'objective'          => trim($body['objective'] ?? 'traffic'),
        'budget_type'        => $budgetType,
        'budget_amount'      => $budgetAmount,
        'targeting_spec'     => !empty($body['targeting_spec']) ? json_encode($body['targeting_spec']) : null,
        'headline'           => trim($body['headline'] ?? ''),
        'primary_text'       => trim($body['primary_text'] ?? ''),
        'destination_url'    => trim($body['destination_url'] ?? ''),
        'creative_media_url' => trim($body['creative_media_url'] ?? '') ?: null,
        'status'             => 'active',
        'autopilot_enabled'  => (int)($body['autopilot_enabled'] ?? 1),
        'created_at'         => date('Y-m-d H:i:s'),
        'updated_at'         => date('Y-m-d H:i:s'),
    ]);

    jsonResponse(201, [
        'success'     => true,
        'message'     => 'Campaign created and launched successfully.',
        'campaign_id' => $campaignId,
        'campaign'    => [
            'id'            => $campaignId,
            'name'          => trim($body['campaign_name']),
            'type'          => $body['campaign_type'],
            'budget_amount' => $budgetAmount,
            'status'        => 'active'
        ]
    ]);
}

function handleUpdateCampaign(int $userId, int $campaignId): void
{
    $campaign = db()->fetchOne(
        "SELECT id, status FROM ad_campaigns WHERE id = ? AND user_id = ?",
        [$campaignId, $userId]
    );

    if ($campaign === null) {
        jsonResponse(404, ['success' => false, 'error' => 'Campaign not found.']);
    }

    $body = getRequestBody();

    $allowedFields = [
        'campaign_name', 'objective', 'budget_type', 'budget_amount',
        'headline', 'primary_text', 'destination_url', 'creative_media_url',
        'autopilot_enabled',
    ];

    $updates = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $body)) {
            $updates[$field] = $body[$field];
        }
    }

    if (isset($body['status'])) {
        $newStatus = $body['status'];
        if (!in_array($newStatus, ['active', 'paused'], true)) {
            jsonResponse(400, ['success' => false, 'error' => 'Can only pause or resume a campaign (status: active/paused).']);
        }
        $updates['status'] = $newStatus;
    }

    if (empty($updates)) {
        jsonResponse(400, ['success' => false, 'error' => 'No valid fields provided to update.']);
    }

    $updates['updated_at'] = date('Y-m-d H:i:s');
    db()->update('ad_campaigns', $updates, 'id = ?', [$campaignId]);

    jsonResponse(200, ['success' => true, 'message' => 'Campaign updated successfully.']);
}
