# Adsbooster.net — Next-Gen AI Ad Optimization, UGC Image Studio & Verified Growth Directory

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/MySQL-8.0%2B%20%7C%20MariaDB-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![AI Engines](https://img.shields.io/badge/AI%20Engines-Groq%20LPU%20%7C%20Flux%20%7C%20Gemini-f55036?style=flat-square)](https://groq.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue?style=flat-square)](#)

**Adsbooster.net** is an enterprise-grade digital advertising optimization platform, AI UGC image creative studio, real-time multi-network social publisher, verified business marketplace, and thought leadership platform built with vanilla PHP 8.x, dark luxury aesthetics, and multi-model AI intelligence.

---

## 🌟 Key Platform Capabilities

### 1. 🎨 AI UGC Image Generator & Creative Studio (`ugc-generator.php`)
- **Photorealistic AI UGC Generation**: Instant creative generation leveraging Flux.1-Schnell, Google Gemini Vision, and Groq LPU ad copy synthesis.
- **Creator Personas & Shoot Styles**:
  - *Personas*: Gen Z Creator, SaaS Founder, Fitness Athlete, Everyday Homemaker, Tech Reviewer, Luxury Fashion Model.
  - *Styles*: Handheld Selfie Product Hold, Unboxing Moment, Lifestyle Kitchen Setting, Influencer Desk Setup, Dramatic Before & After, Minimalist Aesthetic Flatlay.
  - *Aspect Ratios*: 1:1 Square (Feed), 9:16 Vertical (Stories/Reels/TikTok), 16:9 Landscape (YouTube/Banner), 4:5 Portrait (Instagram Grid).
- **Interactive HTML5 Canvas Studio**:
  - Real-time photo filters: Brightness, Contrast, Saturation, Sepia Warmth, and High-Contrast Grayscale.
  - Badge stamps: `VERIFIED`, `50% OFF SALE`, `⭐ TOP RATED`, `BEST SELLER`, `NEW ARRIVAL`, `LIMITED STOCK`.
  - Headline typography and subtext overlays with real-time text styling and font size sliders.
- **1-Click Variation Regeneration**: Synthesizes alternative creative variations using randomized seed generation while preserving the core product concept.
- **Direct Deployment**: 1-click download in high resolution or instant push into live multi-network social post creator.

### 2. 🚀 Real-Time Multi-Platform Social Media & Ads Network Connect (`social-connect.php`, `post-creator.php`, `modules/social_post_engine.php`)
- **Real-Time OAuth 2.0 Integration & Official Callback URLs**:
  All OAuth authorizations redirect to the universal callback handler at `/oauth-callback.php`. Configure the following Authorized Redirect URIs in your developer consoles:

  | Platform | Authorized Redirect URI (Callback URL) | Developer Portal Settings Path | Required Scopes |
  | :--- | :--- | :--- | :--- |
  | **Meta (Facebook)** | `https://yourdomain.com/oauth-callback.php?platform=meta`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [Meta for Developers](https://developers.facebook.com/apps) &rarr; Products &rarr; Facebook Login &rarr; Settings &rarr; **Valid OAuth Redirect URIs** | `public_profile`, `email`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `ads_management` |
  | **Instagram Professional** | `https://yourdomain.com/oauth-callback.php?platform=instagram` | [Meta for Developers](https://developers.facebook.com/apps) &rarr; Facebook Login &rarr; Settings &rarr; **Valid OAuth Redirect URIs** | `instagram_basic`, `instagram_content_publish`, `pages_show_list` |
  | **TikTok for Business** | `https://yourdomain.com/oauth-callback.php?platform=tiktok`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [TikTok Business Developers](https://business-api.tiktok.com/portal/) &rarr; My Apps &rarr; App Details &rarr; **Redirect URI** | `user.info.basic`, `video.upload`, `video.publish` |
  | **Twitter / X** | `https://yourdomain.com/oauth-callback.php?platform=twitter`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [X Developer Portal](https://developer.x.com/en/portal/dashboard) &rarr; User authentication settings &rarr; **Callback URI / Redirect URL** | `tweet.read`, `tweet.write`, `users.read`, `offline.access` |
  | **LinkedIn Marketing** | `https://yourdomain.com/oauth-callback.php?platform=linkedin`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [LinkedIn Developer Portal](https://www.linkedin.com/developers/apps) &rarr; Auth &rarr; **Authorized redirect URLs for your app** | `openid`, `profile`, `email`, `w_member_social` |
  | **Google Ads** | `https://yourdomain.com/oauth-callback.php?platform=google_ads`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) &rarr; Credentials &rarr; OAuth 2.0 Client IDs &rarr; **Authorized redirect URIs** | `openid`, `email`, `profile`, `https://www.googleapis.com/auth/adwords` |
  | **Google Calendar** | `https://yourdomain.com/oauth-callback.php?platform=google_calendar` | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) &rarr; OAuth 2.0 Client IDs &rarr; **Authorized redirect URIs** | `https://www.googleapis.com/auth/calendar` |
  | **Google Business** | `https://yourdomain.com/oauth-callback.php?platform=google_business` | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) &rarr; OAuth 2.0 Client IDs &rarr; **Authorized redirect URIs** | `https://www.googleapis.com/auth/business.manage` |
  | **Snapchat Ads** | `https://yourdomain.com/oauth-callback.php?platform=snapchat`<br>*(Clean fallback: `https://yourdomain.com/oauth-callback.php`)* | [Snap Business Developers](https://businessapi.snapchat.com/portal/) &rarr; App Details &rarr; **Redirect URI** | `snapchat-marketing-api` |

- **Real-Time Live Publishing**:
  - Select any combination of connected channels with interactive multi-checkbox toggles.
  - Choose between immediate live dispatch (`🚀 Publish Live Now`) executing live cURL requests against each platform or scheduled queue delivery.
  - Live latency tracking, response logging, and per-platform dispatch status saved directly to `social_posts`.

### 3. 🌐 Developer Platform, REST API v1 & Webhooks (`api/v1/*`, `developer-docs.php`, `api-settings.php`)

- **One Unified Master API Key**: Single bearer token granting comprehensive access across all platform domains (`read`, `write`, `ads`, `ai`, `social`, `directory`, `wallet`, `ugc`, `reviews`).
- **Sliding-Window Rate Limiting**: Enforces a strict quota of 60 requests per minute tracked in the `api_rate_limits` table. Exceeded quotas return HTTP 429 Too Many Requests with standard `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers.
- **Real-Time HMAC-SHA256 Webhooks**: Outbound event notifications (`campaign.status_changed`, `post.published`, `payment.completed`, `webhook.test_ping`) signed with cryptographic `X-Adsbooster-Signature` headers.
- **Interactive Webhook Tester**: Integrated "Ping Test" in the Developer Console (`api-settings.php`) verifying target endpoints in real time with millisecond latency telemetry.
- **Comprehensive A–Z Documentation**: Full reference manual (`developer-docs.php`) complete with interactive copyable code snippets in cURL, JavaScript (Fetch), Python, and PHP.
- **Live System Status Page**: Real-time service uptime, latency tracking, and operational health dashboard (`status.php`).

### 4. ⭐ Community Reviews & Rating System (`reviews.php`, `index.php`, `dashboard.php`, `admin/reviews.php`)
- **Tiered Eligibility Enforcement**:
  - **Pro Plan Subscribers**: May submit and update verified platform reviews at any time.
  - **Free Plan Users**: Unlock review submission after 5 days of registered platform usage. Free members receive an unobtrusive dashboard banner with "Leave Review" or "Skip for now" (dismisses for 7 days via cookie and session).
- **Dynamic Homepage Showcase**: Top-rated verified reviews dynamically featured on the homepage (`index.php`) displaying user avatars, ratings, review comments, and membership plan badges.
- **Administrative Review Moderation**: Dedicated admin suite (`admin/reviews.php`) to approve, feature, edit, or reject submitted community feedback.

### 5. 💼 Dedicated Public Portfolios, Dual vCards & AI Resume Analyser (`portfolio.php`, `vcard.php`, `vcard-download.php`, `portfolio-builder.php`, `resume-analyser.php`)
- **Dedicated Public Showcase**: Every registered member receives an SEO-indexed, shareable public portfolio link (`/portfolio.php?u=username`) accessible without login.
- **Dual vCard System (Both vCards)**:
  - *vCard 1: Downloadable RFC 6350 (.vcf)*: Standards-compliant vCard 3.0 / RFC 6350 contact card saving directly into Apple iOS Contacts, Google Contacts, and Windows address books (`/vcard-download.php?u=...`). Includes photo URI/base64, structured name, job title, company, phone, WhatsApp, email, URL, address, and social profiles (LinkedIn, Twitter/X, Instagram, GitHub).
  - *vCard 2: Interactive Digital NFC Business Card (`/vcard.php?u=...`)*: Standalone mobile-first virtual smart card featuring realistic 3D perspective tilt on hover, tap-to-flip 180° animation, EMV metallic chip illustration, contactless NFC wave badge, and live scannable QR Code that prompts phone cameras to save contacts instantly. Includes one-click contact action buttons (Save Contact, WhatsApp Chat, Call, Email, Share via Web Share API). Also embedded directly as an interactive widget on `portfolio.php`.
- **4 Luxury Design Themes**:
  - *Dark Luxury Noir* (Obsidian `#080c14` with lime `#c8ff00` accents)
  - *Creative Cyber Neon* (Vibrant purple & cyan gradient glow)
  - *Executive Titanium* (Corporate slate and titanium elegance)
  - *Minimalist Aurora Glass* (Emerald `#10b981` with frosted blur)
- **Portfolio Studio (`portfolio-builder.php`)**: Full visual editor with dynamic repeaters for Case Studies & Projects (title, highlight metric, description, link), Service Packages (title, price, scope), Core Skills tag manager, social networks, and live card preview.
- **AI Resume Analyser & ATS Optimizer (`resume-analyser.php`)**:
  - Drag-and-drop file uploader supporting `.pdf`, `.docx` (via native `ZipArchive`), and `.txt` files, alongside raw text pasting.
  - Deep ATS scoring (0-100) with animated circular SVG gauge, grade verdict, and 4 sub-scores (Readability, Keyword Optimization, Impact & Metrics, Layout & Structure).
  - Automated extraction of executive bio, competencies, and quantified results.
  - Actionable ATS audit highlighting strengths, missing high-value recruiter keywords, and optimization advice.
  - **1-Click Sync to Portfolio & vCard**: Automatically syncs ATS-optimized profile, bio, skills, services, and projects into `user_portfolios`, `user_vcards`, and `users` tables.

### 6. 💼 Advertising & Creative Talent Marketplace (`jobs.php`, `job-details.php`, `create-job.php`)
- **Opportunities & Bounties**: Browse and post contracts, retainers, and bounties for media buyers, UGC creators, copywriters, and performance marketers.
- **Multi-Filter Discovery**: Search by keyword, contract type (Contract, Freelance, Bounty, Full-Time), workplace mode (100% Remote, Hybrid), and budget range.
- **Direct Employer Connect**: Inquire and apply instantly through P2P Chat (`p2p-chat.php?user=...`) or WhatsApp.

### 7. 🤝 Enhanced P2P Chat with Contract & Deal Proposals (`p2p-chat.php`)
- **Real-Time Collaboration**: Connect with clients, freelancers, sellers, and platform administrators.
- **Deal & Contract Proposal Tool**: Propose structured project contracts with agreed amounts ($ USD), milestones, and deliverables stored in `p2p_deals`.
- **Public Profile & vCard Links**: View any chat partner's public portfolio and save their contact details directly from the conversation header.
- **Safety Tools**: Integrated user reporting, block controls, and 24/7 AI Growth Consultant assistant.

### 8. 🏢 Verified Business & Product Directory (`marketplace.php`)
- **Dynamic Homepage Showcase**: Embedded directly onto `index.php` with live seller badges and contact channels.
- **Instant Buyer-Seller Direct Connect**: One-click WhatsApp direct chat (`wa.me`) and instant click-to-call (`tel:`).
- **Tiered Seller Verification**: Verified merchant badges, category filtering (SaaS, Agency, Ecommerce, Real Estate), and geolocation.

### 9. 🔐 Robust Two-Factor Authentication (2FA) & Admin Gate (`security-2fa.php`, `admin/totp-verify.php`, `admin-login.php`)
- **RFC 6238 TOTP Standard**: Compatible with Google Authenticator, Authy, Microsoft Authenticator, and 1Password.
- **Admin Verification Gate**: All administrative sessions require 2FA TOTP verification challenge (`admin/totp-verify.php`) before dashboard access is granted.
- **Email OTP Verification**: Fallback verification codes dispatched to verified account emails.
- **Emergency Recovery Codes**: 8 single-use cryptographically hashed backup codes for account retrieval.

### 10. 🛡️ Administrative Control Suite (`/admin/`)
- All 20 administrative modules are operational:
  - `index.php` — Platform overview, telemetry, financial summary, and audit logs.
  - `pricing.php` — Dynamic subscription tiers, credit quotas, and feature flags.
  - `listings.php` — Marketplace directory moderation and badge assignment.
  - `jobs.php` — Talent opportunities and bounty moderation.
  - `reviews.php` — Community review moderation and homepage featuring.
  - `campaigns.php` — Ad campaign inspection, manual budget adjustment, and status overrides.
  - `users.php` — User role assignments, ban controls, and wallet balance adjustments.
  - `payouts.php` & `transactions.php` — Financial ledger inspection and withdrawal approvals.
  - `cms.php` & `blogs.php` — Article review and static content manager.
  - `analytics.php` — Platform-wide conversion, latency, and revenue metrics.
  - `tickets.php` — Customer support ticket desk and resolution pipeline.
  - `p2p-chat.php` — Escalated dispute review and chat safety monitoring.
  - `seo.php` — Meta titles, descriptions, OpenGraph tags, and sitemap settings.
  - `policy-rules.php` — Platform terms, safety guidelines, and advertising policies.
  - `referrals.php` — Affiliate tracking, referral tiers, and payout thresholds.
  - `fraud-watch.php` — Click-fraud detection and IP anomaly telemetry.
  - `translations.php` — Multilingual dictionary and localization strings.

---

## 🛠️ Technology Stack

- **Backend**: Pure PHP 8.1 - 8.4 (Zero framework overhead, extreme performance)
- **Database**: MySQL 8.0+ / MariaDB 10.3+ (InnoDB, UTF8mb4, indexed relations)
- **Frontend**: Vanilla CSS Design System (Dark Luxury Palette `#080c14`, `#0d1524`, `#c8ff00`), Phosphor Icons
- **Rich Text Editor**: CKEditor 5 Classic via official CDN
- **Mail Engine**: PHPMailer 6.x with custom dark luxury HTML templates
- **AI Providers**: Groq LPUs (`openai/gpt-oss-120b`, `qwen/qwen3.8-27b`), Flux.1-Schnell, Google Gemini 2.5 Flash
- **Social & Ad APIs**: Meta Graph API, TikTok API, Twitter / X API v2, LinkedIn API v2, Google Ads API, Snapchat API
- **Billing Gateways**: Paystack & Flutterwave

---

## 🚀 Quickstart Guide

### 1. Local Development (Laragon / XAMPP)
1. Clone the project into your web directory:
   ```bash
   git clone https://github.com/your-org/adsbooster.git C:\laragon\www\seopp
   ```
2. Start Apache and MySQL from Laragon.
3. Create database `adsbooster` and import schema:
   ```bash
   mysql -u root adsbooster < database/adsbooster_complete.sql
   ```
4. Access the application in your browser:
   ```
   http://localhost/seopp
   ```

### 2. Live Linux Server Deployment
Refer to the comprehensive, step-by-step [DEPLOYMENT.md](DEPLOYMENT.md) for Apache VirtualHost, Nginx reverse proxy, SSL configuration, and cron automation.

---

## 📂 Project Directory Structure

```
seopp/
├── admin/                     # Administrative management dashboard & moderation
│   ├── index.php              # Admin overview & system metrics
│   ├── totp-verify.php        # Admin 2FA TOTP verification challenge
│   ├── pricing.php            # Subscription & pricing plan manager
│   ├── listings.php           # Marketplace directory moderation
│   ├── jobs.php               # Jobs & bounties moderation
│   ├── reviews.php            # Community reviews moderation
│   ├── blogs.php              # Blog post moderation
│   └── users.php              # User role & permission controls
├── api/v1/                    # Public REST API v1
│   ├── bootstrap.php          # Auth, Master API Key verification, Rate limiter
│   ├── campaigns.php          # Ad campaign endpoints
│   ├── posts.php              # Social post creation & live publishing
│   ├── ugc.php                # AI UGC image generation endpoints
│   ├── directory.php          # Business directory endpoints
│   ├── reviews.php            # Community review submission & querying
│   ├── wallet.php             # Balance check & credit transaction endpoints
│   └── webhooks.php           # Webhook sender & test ping helper
├── assets/                    # Static assets (CSS, JS, Fonts, Images)
├── config/                    # System configurations
│   ├── db.php                 # Database PDO singleton connection
│   ├── site_setting_and_config.php # Constants & session handlers
│   └── apikeys.php            # Groq, Gemini, SMTP, & Payment API keys
├── cron/                      # Automated scheduled background scripts
├── database/                  # Master migrations and seeders
│   ├── adsbooster_complete.sql# Complete master database dump
│   └── export_master_sql.php  # Automated database snapshot generator
├── includes/                  # Reusable layouts, headers, and navigation
├── modules/                   # Core business logic classes
│   ├── ai_engine.php          # Groq, Gemini & Flux AI UGC image generator + resume parser
│   ├── social_post_engine.php # Real-time multi-network live publishing engine
│   ├── email_service.php      # PHPMailer & branded HTML email system
│   └── two_factor.php         # RFC 6238 TOTP authentication library
├── api-settings.php           # Developer console, Master API key & webhook tester
├── developer-docs.php         # Complete A-Z developer documentation manual
├── status.php                 # Real-time service uptime & health telemetry
├── ugc-generator.php          # AI UGC Image Studio & Canvas Editor
├── post-creator.php           # Real-time multi-channel post composer & publisher
├── connect-accounts.php       # OAuth connection manager for 6 social/ad networks
├── portfolio.php              # Dedicated public showcase with embedded NFC card
├── portfolio-builder.php      # Showcase Studio, dynamic repeaters & theme designer
├── vcard.php                  # Interactive 3D Digital Virtual NFC Business Card
├── vcard-download.php         # RFC 6350 standards-compliant contact card downloader
├── resume-analyser.php        # AI ATS Resume Analyser, scoring audit & 1-click sync
├── reviews.php                # Community review submission portal
├── marketplace.php            # Verified business & product directory
├── jobs.php / job-details.php # Advertising talent marketplace
└── security-2fa.php           # User Two-Factor Authentication manager
```

---

*© 2026 Adsbooster.net Engineering Team. All rights reserved.*
