<?php
declare(strict_types=1);

/**
 * Shopify, WooCommerce & E-Commerce 1-Click Sync Engine
 * Adsbooster.net
 */

class EcommerceSyncEngine
{
    /**
     * Connect or ingest store products
     */
    public static function connectAndSyncStore(int $userId, string $storeType, string $storeName, string $storeUrl): int
    {
        $storeUrl = rtrim($storeUrl, '/');
        $storeId = db()->insert('ecommerce_stores', [
            'user_id' => $userId,
            'store_type' => $storeType,
            'store_name' => $storeName,
            'store_url' => $storeUrl,
            'is_connected' => 1,
            'last_synced_at' => date('Y-m-d H:i:s')
        ]);

        // Auto-populate store with high-converting sample e-commerce catalog items
        $sampleProducts = [
            [
                'title' => 'Ultra-Hydrating Botanical Glow Serum (50ml)',
                'handle' => 'botanical-glow-serum',
                'price' => 38.00,
                'image_url' => 'assets/images/thumbs/about-five-img1.png',
                'description' => 'Formulated with organic hyaluronic acid and niacinamide. Clinically proven to hydrate and brighten skin tone in 7 days.',
                'rating' => 4.9,
                'reviews_count' => 328
            ],
            [
                'title' => 'Ergonomic Wireless Mech Keyboard RGB',
                'handle' => 'wireless-mech-keyboard',
                'price' => 129.00,
                'image_url' => 'assets/images/thumbs/team-details-img2.png',
                'description' => 'Custom tactile switches, 80-hour battery life, aerospace aluminum casing with seamless Bluetooth 5.3 pairing.',
                'rating' => 4.8,
                'reviews_count' => 184
            ],
            [
                'title' => 'High-Performance Compression Athletic Leggings',
                'handle' => 'compression-athletic-leggings',
                'price' => 54.00,
                'image_url' => 'assets/images/thumbs/blog-details-img2.png',
                'description' => 'Squat-proof seamless fabric, moisture-wicking technology with high-waist posture support for gym and daily wear.',
                'rating' => 4.95,
                'reviews_count' => 512
            ]
        ];

        foreach ($sampleProducts as $p) {
            db()->insert('ecommerce_products', [
                'store_id' => $storeId,
                'user_id' => $userId,
                'product_title' => $p['title'],
                'product_handle' => $p['handle'],
                'price' => $p['price'],
                'currency' => 'USD',
                'image_url' => $p['image_url'],
                'description' => $p['description'],
                'rating' => $p['rating'],
                'reviews_count' => $p['reviews_count'],
                'auto_pack_generated' => 0
            ]);
        }

        return $storeId;
    }

    /**
     * Generate 1-Click Campaign Auto-Pack for a Product
     * (3 AI UGC Images + 3 Copy Variations + 14-Day Posting Schedule)
     */
    public static function generateAutoPack(int $userId, int $productId): array
    {
        $product = db()->fetch("SELECT * FROM ecommerce_products WHERE id = ? AND user_id = ?", [$productId, $userId]);
        if (!$product) {
            throw new Exception("Product not found");
        }

        $pName = $product['product_title'];
        $pPrice = '$' . number_format((float)$product['price'], 2);

        // 1. 3 AI UGC Images
        $ugcImages = [
            ['title' => 'Handheld Product Showcase', 'url' => $product['image_url'] ?: 'assets/images/thumbs/about-five-img1.png', 'badge' => 'TOP RATED ⭐'],
            ['title' => 'Lifestyle Daily Use', 'url' => 'assets/images/thumbs/blog-details-img1.png', 'badge' => '50% OFF FLASH SALE'],
            ['title' => 'Creator Unboxing Aesthetic', 'url' => 'assets/images/thumbs/team-details-img3.png', 'badge' => 'VERIFIED BUYER']
        ];

        // 2. 3 Psychological Copy Angles
        $copyVariations = [
            [
                'angle' => 'Pain-Point & Instant Relief',
                'headline' => "Finally, {$pName} that actually delivers real results.",
                'primary_text' => "Stop wasting money on subpar alternatives. Get guaranteed results with {$pName} for just {$pPrice}.",
                'cta' => 'Shop Now with 30-Day Money Back Guarantee'
            ],
            [
                'angle' => 'Benefit-Driven & Transformation',
                'headline' => "Transform your daily routine with {$pName}.",
                'primary_text' => "Over 500+ 5-star reviews can't be wrong. Experience the difference starting today for only {$pPrice}.",
                'cta' => 'Claim Your 20% New Customer Discount'
            ],
            [
                'angle' => 'FOMO & Limited-Time Flash Sale',
                'headline' => "⚡ FLASH SALE: {$pName} is 40% OFF this weekend only!",
                'primary_text' => "High demand alert! Inventory is running low on {$pName}. Secure yours now before we sell out.",
                'cta' => 'Order Now - Free Express Shipping Included'
            ]
        ];

        // 3. 14-Day Multi-Network Posting Calendar
        $calendar = [];
        $platforms = ['instagram', 'tiktok', 'facebook', 'twitter'];
        for ($day = 1; $day <= 14; $day++) {
            $plat = $platforms[($day - 1) % count($platforms)];
            $calDate = date('Y-m-d H:i:s', strtotime("+{$day} days 18:00:00"));
            $calendar[] = [
                'day' => "Day {$day}",
                'date' => $calDate,
                'platform' => $plat,
                'post_type' => $day % 3 === 0 ? 'video_reel' : 'carousel_image',
                'caption' => "Day {$day} with {$pName}! Transform your routine today. Available now for {$pPrice} via link in bio! #{$product['product_handle']} #shopping #bestseller",
                'status' => 'scheduled'
            ];
        }

        $packId = db()->insert('ecommerce_campaign_packs', [
            'product_id' => $productId,
            'user_id' => $userId,
            'ugc_images' => json_encode($ugcImages),
            'copy_variations' => json_encode($copyVariations),
            'posting_calendar_14d' => json_encode($calendar)
        ]);

        db()->update('ecommerce_products', ['auto_pack_generated' => 1], 'id = ?', [$productId]);

        return [
            'id' => $packId,
            'product' => $product,
            'ugc_images' => $ugcImages,
            'copy_variations' => $copyVariations,
            'posting_calendar_14d' => $calendar
        ];
    }
}
