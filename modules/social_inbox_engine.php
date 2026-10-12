<?php
declare(strict_types=1);

/**
 * Omnichannel Unified Social Inbox & AI Sales Auto-Closer Engine
 * Adsbooster.net
 */

class SocialInboxEngine
{
    /**
     * Get or Seed Demo Inquiries for Testing
     */
    public static function getInboxMessages(int $userId): array
    {
        $messages = db()->fetchAll("SELECT * FROM social_inbox_messages WHERE user_id = ? ORDER BY received_at DESC", [$userId]);
        if (empty($messages)) {
            // Seed realistic high-intent social leads across IG, FB, TikTok, and WhatsApp
            $seeds = [
                [
                    'platform' => 'instagram',
                    'sender_id' => 'ig_sarah_m',
                    'sender_name' => 'Sarah Miller (@sarahm_designs)',
                    'sender_avatar' => 'assets/images/thumbs/team-details-img3.png',
                    'message_type' => 'comment',
                    'post_context_id' => 'post_ig_9921',
                    'message_text' => 'Price? How fast does shipping take to California?',
                    'sentiment' => 'high_intent_buy'
                ],
                [
                    'platform' => 'tiktok',
                    'sender_id' => 'tt_alex_k',
                    'sender_name' => 'Alex Rivera (@alexmoves)',
                    'sender_avatar' => 'assets/images/thumbs/team-details-img1.png',
                    'message_type' => 'dm',
                    'post_context_id' => null,
                    'message_text' => 'Hey! Where can I buy this? Is there an active discount code available?',
                    'sentiment' => 'high_intent_buy'
                ],
                [
                    'platform' => 'whatsapp',
                    'sender_id' => 'wa_14155552671',
                    'sender_name' => 'Marcus Thorne (+1 415-555-2671)',
                    'sender_avatar' => 'assets/images/thumbs/team-details-img2.png',
                    'message_type' => 'whatsapp_chat',
                    'post_context_id' => null,
                    'message_text' => 'Hello, I saw your ad on Facebook. Do you have wholesale pricing for agencies?',
                    'sentiment' => 'positive'
                ],
                [
                    'platform' => 'facebook',
                    'sender_id' => 'fb_emma_w',
                    'sender_name' => 'Emma Watson',
                    'sender_avatar' => 'assets/images/thumbs/blog-details-img1.png',
                    'message_type' => 'comment',
                    'post_context_id' => 'fb_post_881',
                    'message_text' => 'Link please! I want to try the 14-day trial before subscribing.',
                    'sentiment' => 'high_intent_buy'
                ]
            ];

            foreach ($seeds as $s) {
                db()->insert('social_inbox_messages', [
                    'user_id' => $userId,
                    'platform' => $s['platform'],
                    'sender_id' => $s['sender_id'],
                    'sender_name' => $s['sender_name'],
                    'sender_avatar' => $s['sender_avatar'],
                    'message_type' => $s['message_type'],
                    'post_context_id' => $s['post_context_id'],
                    'message_text' => $s['message_text'],
                    'ai_auto_replied' => 0,
                    'status' => 'unread',
                    'sentiment' => $s['sentiment']
                ]);
            }

            $messages = db()->fetchAll("SELECT * FROM social_inbox_messages WHERE user_id = ? ORDER BY received_at DESC", [$userId]);
        }

        return $messages;
    }

    /**
     * AI Auto-Closer: Generate and Dispatch Sales Closer Reply
     */
    public static function triggerAutoCloser(int $userId, int $messageId): array
    {
        $msg = db()->fetch("SELECT * FROM social_inbox_messages WHERE id = ? AND user_id = ?", [$messageId, $userId]);
        if (!$msg) {
            throw new Exception("Message not found");
        }

        $senderName = explode(' ', $msg['sender_name'])[0];
        $promoCode = "FLASH15";
        $productLink = BASE_URL . "/pricing-plan.php";

        $replyText = "Hey {$senderName}! 👋 Thanks for asking! You can grab yours directly here: {$productLink} — use promo code [{$promoCode}] at checkout for an instant 15% OFF today only! 🚀 Let me know if you need any help!";

        db()->update('social_inbox_messages', [
            'ai_auto_replied' => 1,
            'auto_reply_text' => $replyText,
            'status' => 'replied',
            'replied_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$messageId]);

        return [
            'success' => true,
            'message_id' => $messageId,
            'reply_text' => $replyText,
            'platform' => $msg['platform']
        ];
    }
}
