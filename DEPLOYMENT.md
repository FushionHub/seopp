# Adsbooster.net — Professional Deployment & Developer Guide

This document provides a comprehensive, production-grade guide for deploying, configuring, and maintaining the **Adsbooster.net** platform on Linux (Ubuntu/Debian, RHEL/Rocky), Windows (Laragon/IIS/XAMPP), or Dockerized cloud environments.

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Prerequisites & Requirements](#2-prerequisites--requirements)
3. [Step-by-Step Installation](#3-step-by-step-installation)
   - [Step 1: Clone Repository & Set Permissions](#step-1-clone-repository--set-permissions)
   - [Step 2: Database Creation & Migration](#step-2-database-creation--migration)
   - [Step 3: Application Configuration](#step-3-application-configuration)
   - [Step 4: Web Server Configuration (Apache & Nginx)](#step-4-web-server-configuration-apache--nginx)
   - [Step 5: PHP-FPM Configuration & Performance Tuning](#step-5-php-fpm-configuration--performance-tuning)
4. [Third-Party Services & API Keys](#4-third-party-services--api-keys)
   - [Groq Cloud AI Engine](#groq-cloud-ai-engine)
   - [Google Gemini AI Studio](#google-gemini-ai-studio)
   - [SMTP Email Delivery (PHPMailer)](#smtp-email-delivery-phpmailer)
   - [Payment Gateways (Stripe & Paystack)](#payment-gateways-stripe--paystack)
5. [Core Subsystems Verification](#5-core-subsystems-verification)
   - [Two-Factor Authentication (2FA)](#two-factor-authentication-2fa)
   - [AI UGC Video Generator](#ai-ugc-video-generator)
   - [Blog & CKEditor 5 CMS](#blog--ckeditor-5-cms)
   - [Custom HTTP Error Pages](#custom-http-error-pages)
6. [Automated Scheduled Tasks (Cron Jobs)](#6-automated-scheduled-tasks-cron-jobs)
7. [Production Security & Hardening Checklist](#7-production-security--hardening-checklist)
8. [Troubleshooting & FAQ](#8-troubleshooting--faq)

---

## 1. System Architecture

Adsbooster.net is engineered as a high-performance, low-latency, modular PHP platform without heavyweight framework bloat:

```
┌─────────────────────────────────────────────────────────────┐
│                      Client Browsers                        │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTPS (TLS 1.3)
┌──────────────────────────────▼──────────────────────────────┐
│            Reverse Proxy / Web Server (Apache / Nginx)      │
│  - URL Rewriting & Clean Routing                            │
│  - Custom Error Routing (400, 401, 403, 404, 500, 503)       │
│  - Gzip / Brotli Compression & Cache Headers                │
└──────────────────────────────┬──────────────────────────────┘
                               │ FastCGI / mod_php
┌──────────────────────────────▼──────────────────────────────┐
│               PHP 8.2 / 8.3 / 8.4 Application Engine        │
│  ├── Modules: AI Engine, Email Service, Two-Factor (RFC6238) │
│  ├── Auth Guard & Role-Based Access Control (Admin/User)    │
│  └── CKEditor 5 Classic + HTML Email Delivery Engine        │
└──────────────┬──────────────────────────────┬───────────────┘
               │                              │
┌──────────────▼─────────────┐ ┌──────────────▼───────────────┐
│     MySQL 8.0+ / MariaDB   │ │  External Cloud Services     │
│   50 Tables (InnoDB, UTF8) │ │  - Groq AI (LLM / UGC)       │
│   Database: `adsbooster`   │ │  - Google Gemini AI Studio   │
│                            │ │  - SMTP Email Gateways       │
└────────────────────────────┘ └──────────────────────────────┘
```

---

## 2. Prerequisites & Requirements

### Server Requirements
| Component            | Minimum Specification                         | Recommended Specification     |
| :------------------- | :-------------------------------------------- | :---------------------------- |
| **Operating System** | Ubuntu 22.04 LTS / Debian 12 / Windows Server | Ubuntu 24.04 LTS              |
| **CPU**              | 2 vCPU Cores                                  | 4+ vCPU Cores                 |
| **RAM**              | 2 GB                                          | 4 GB - 8 GB                   |
| **Storage**          | 20 GB SSD                                     | 50 GB NVMe                    |
| **PHP**              | PHP 8.1.0 or higher                           | PHP 8.3 / 8.4                 |
| **Database**         | MySQL 5.7+ or MariaDB 10.3+                   | MySQL 8.0.36+ or MariaDB 11.x |

### Required PHP Extensions
Ensure the following extensions are compiled and enabled in `php.ini`:
```ini
extension=pdo_mysql
extension=curl
extension=openssl
extension=mbstring
extension=fileinfo
extension=gd
extension=intl
extension=zip
```

---

## 3. Step-by-Step Installation

### Step 1: Clone Repository & Set Permissions

#### On Linux / Ubuntu:
```bash
# Navigate to web root
cd /var/www

# Clone repository
git clone https://github.com/your-org/adsbooster.git seopp
cd seopp

# Set ownership to web user (www-data for Apache/Nginx)
sudo chown -R www-data:www-data /var/www/seopp

# Set secure directory and file permissions
sudo find /var/www/seopp -type d -exec chmod 755 {} \;
sudo find /var/www/seopp -type f -exec chmod 644 {} \;

# Ensure uploads and cache directories are writable
sudo chmod -R 775 /var/www/seopp/assets/uploads
```

#### On Windows (Laragon / XAMPP):
- Place project in `C:\laragon\www\seopp` or `C:\xampp\htdocs\seopp`.
- Ensure directory `assets\uploads` exists and is writable.

---

### Step 2: Database Creation & Migration

1. Connect to MySQL server via CLI or phpMyAdmin:
```bash
mysql -u root -p
```

2. Create the production database with latin1 character set:
```sql
CREATE DATABASE `adsbooster` 
  CHARACTER SET latin1 
  COLLATE latin1_swedish_ci;
```

3. Import the master SQL schema and seed data from `database/adsbooster_complete.sql`:
```bash
mysql -u root -p adsbooster < /var/www/seopp/database/adsbooster_complete.sql
```
*Note: This script establishes all 50 system tables, seed settings, pricing plans, and admin accounts.*

---

### Step 3: Application Configuration

#### 1. Database Connection (`config/db.php`)
Open `config/db.php` and verify your credentials:
```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'adsbooster');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

#### 2. Site URL & Environment Settings (`config/site_setting_and_config.php`)
Ensure `BASE_URL` reflects your live domain:
```php
// Example for local development:
define('BASE_URL', 'http://localhost/seopp');

// Example for production domain:
define('BASE_URL', 'https://adsbooster.net');
```

#### 3. API Keys & External Credentials (`config/apikeys.php`)
Configure your live API keys in `config/apikeys.php`:
```php
return [
    'groq_api_key'      => 'gsk_your_groq_api_key_here',
    'gemini_api_key'    => 'your_google_gemini_api_key_here',
    'smtp_host'         => 'smtp.sendgrid.net',
    'smtp_port'         => 587,
    'smtp_user'         => 'apikey',
    'smtp_pass'         => 'your_smtp_password_here',
    'smtp_from_email'   => 'no-reply@adsbooster.net',
    'smtp_from_name'    => 'Adsbooster.net',
    'stripe_secret_key' => 'sk_live_...',
    'paystack_secret'   => 'sk_live_...',
];
```

---

### Step 4: Web Server Configuration (Apache & Nginx)

#### Option A: Apache 2.4 Configuration
Ensure `mod_rewrite` and `mod_headers` are enabled:
```bash
sudo a2enmod rewrite headers deflate
```

Create a VirtualHost configuration (`/etc/apache2/sites-available/adsbooster.conf`):
```apache
<VirtualHost *:80>
    ServerName adsbooster.net
    ServerAlias www.adsbooster.net
    DocumentRoot /var/www/seopp

    <Directory /var/www/seopp>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/adsbooster_error.log
    CustomLog ${APACHE_LOG_DIR}/adsbooster_access.log combined
</VirtualHost>
```

Enable site and restart Apache:
```bash
sudo a2ensite adsbooster.conf
sudo systemctl reload apache2
```

The repository includes a ready-to-use `.htaccess` file with preconfigured directives:
- RewriteEngine enabled for clean URLs.
- Custom ErrorDocuments:
  - `ErrorDocument 400 /400.php`
  - `ErrorDocument 401 /401.php`
  - `ErrorDocument 403 /403.php`
  - `ErrorDocument 404 /404.php`
  - `ErrorDocument 500 /500.php`
  - `ErrorDocument 503 /503.php`
- Security header enforcement (`X-Frame-Options`, `X-Content-Type-Options`).

---

#### Option B: Nginx + PHP-FPM Configuration
Create `/etc/nginx/sites-available/adsbooster`:
```nginx
server {
    listen 80;
    server_name adsbooster.net www.adsbooster.net;
    root /var/www/seopp;
    index index.php index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Custom Error Pages
    error_page 400 /400.php;
    error_page 401 /401.php;
    error_page 403 /403.php;
    error_page 404 /404.php;
    error_page 500 /500.php;
    error_page 503 /503.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Pass PHP scripts to FastCGI
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(ht|git) {
        deny all;
    }

    location ~ ^/(config|database|cron)/ {
        deny all;
        return 403;
    }
}
```

Enable site and test configuration:
```bash
sudo ln -s /etc/nginx/sites-available/adsbooster /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

### Step 5: PHP-FPM Configuration & Performance Tuning

In `/etc/php/8.3/fpm/php.ini` or `/etc/php/8.3/apache2/php.ini`:
```ini
upload_max_filesize = 32M
post_max_size = 48M
memory_limit = 256M
max_execution_time = 120
date.timezone = UTC
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
expose_php = Off
```

---

## 4. Third-Party Services & API Keys

### Groq Cloud, Gemini (Nano Banana 2) & Veo Video Engines
Adsbooster utilizes Groq's high-speed LPUs for ultra-fast ad copy generation, campaign audits, P2P AI chat, and resume parsing. Google Gemini image models power photorealistic UGC image synthesis and Google Veo powers text-to-video rendering.
1. Obtain a Groq API key from [Groq Console](https://console.groq.com/keys) and a Google AI Studio key from [aistudio.google.com/apikey](https://aistudio.google.com/apikey).
2. The system automatically handles model failovers in priority order:
   - Text (Groq): `openai/gpt-oss-120b` (Default High Performance), `qwen/qwen3.8-27b`, `openai/gpt-oss-20b`, `llama-3.3-70b-versatile`
   - Images (Gemini): `gemini-3-pro-image-preview` (Nano Banana 2) → `gemini-2.5-flash-image` (Nano Banana) → `gemini-2.0-flash-preview-image-generation`
   - Video (Gemini): `veo-3.1-generate-preview` → `veo-3.0-generate-preview` → `veo-3.0-generate-001`, then automatic fallback to the built-in Storyboard Studio renderer
3. Enter keys in `config/apikeys.php` under `'groq_api_key'` and `'gemini_api_key'`, or set the `GEMINI_API_KEY` environment variable on the server.
4. If external AI endpoints suffer transient timeouts, automated fallback heuristics prevent session drops (curated first-party stills for images, Storyboard Studio for video).

### Social Media & Ad Network OAuth Connections
Adsbooster supports 100% real-time OAuth 2.0 connectivity and live multi-network dispatch. In your third-party developer accounts, configure the following Authorized Redirect URIs pointing to your production domain:

#### Universal Master Callback URL:
```text
https://yourdomain.com/oauth-callback.php
```

#### Platform-Specific Callback URLs:
- **Meta (Facebook & Instagram)**:
  - Valid OAuth Redirect URIs:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=meta
    https://yourdomain.com/oauth-callback.php?platform=instagram
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [developers.facebook.com](https://developers.facebook.com/apps) &rarr; Products &rarr; Facebook Login &rarr; Settings &rarr; **Valid OAuth Redirect URIs**
  - Scopes: `public_profile`, `email`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `ads_management`, `instagram_basic`, `instagram_content_publish`
  - Credentials in `config/apikeys.php`: `META_APP_ID`, `META_APP_SECRET`

- **TikTok for Business**:
  - Redirect URI:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=tiktok
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [business-api.tiktok.com](https://business-api.tiktok.com/portal/) &rarr; App Details &rarr; **Redirect URI**
  - Scopes: `user.info.basic`, `video.upload`, `video.publish`
  - Credentials in `config/apikeys.php`: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`

- **Twitter / X (OAuth 2.0 PKCE)**:
  - Callback URI / Redirect URL:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=twitter
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [developer.x.com](https://developer.x.com/en/portal/dashboard) &rarr; Projects & Apps &rarr; User authentication settings &rarr; **Callback URI / Redirect URL**
  - Type: Web App (Confidential client)
  - Scopes: `tweet.read`, `tweet.write`, `users.read`, `offline.access`
  - Credentials in `config/apikeys.php`: `TWITTER_CLIENT_ID`, `TWITTER_CLIENT_SECRET`

- **LinkedIn Marketing Solutions**:
  - Authorized Redirect URLs:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=linkedin
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [linkedin.com/developers](https://www.linkedin.com/developers/apps) &rarr; Auth &rarr; **Authorized redirect URLs for your app**
  - Scopes: `openid`, `profile`, `email`, `w_member_social`
  - Credentials in `config/apikeys.php`: `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`

- **Google Ads & Cloud Platform**:
  - Authorized Redirect URIs:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=google_ads
    https://yourdomain.com/oauth-callback.php?platform=google_calendar
    https://yourdomain.com/oauth-callback.php?platform=google_business
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [console.cloud.google.com](https://console.cloud.google.com/apis/credentials) &rarr; Credentials &rarr; OAuth 2.0 Client IDs &rarr; **Authorized redirect URIs**
  - Scopes: `openid`, `email`, `profile`, `https://www.googleapis.com/auth/adwords`, `https://www.googleapis.com/auth/calendar`, `https://www.googleapis.com/auth/business.manage`
  - Credentials in `config/apikeys.php`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`

- **Snapchat Marketing API**:
  - Redirect URI:
    ```text
    https://yourdomain.com/oauth-callback.php?platform=snapchat
    https://yourdomain.com/oauth-callback.php
    ```
  - Developer Portal: [businessapi.snapchat.com](https://businessapi.snapchat.com/portal/) &rarr; OAuth App &rarr; **Redirect URI**
  - Scopes: `snapchat-marketing-api`
  - Credentials in `config/apikeys.php`: `SNAPCHAT_CLIENT_ID`, `SNAPCHAT_CLIENT_SECRET`

Test and monitor live account connectivity in `/social-connect.php`.


### Public REST API v1, Rate Limiting & Webhooks
1. **Unified Master API Key**: Single key granting access to all resources (`read`, `write`, `ads`, `ai`, `social`, `directory`, `wallet`, `ugc`, `reviews`). Generate keys in `api-settings.php`.
2. **Rate Limiting**: Monitored via the `api_rate_limits` table with a sliding 60-second window (60 requests/minute). Requests exceeding quota receive HTTP 429 with standard headers:
   - `X-RateLimit-Limit: 60`
   - `X-RateLimit-Remaining: 0`
   - `X-RateLimit-Reset: <timestamp>`
3. **Webhooks**: Outbound notifications are signed with `HMAC-SHA256` in the `X-Adsbooster-Signature` header. Developers can test endpoints using the "Ping Test" button in `api-settings.php`.

### SMTP Email Delivery (PHPMailer)
Adsbooster is an **email-first** platform. All major interactions (signups, password resets, 2FA OTP codes, ad status updates, marketplace inquiries) dispatch responsive HTML emails.
- Bundled PHPMailer is loaded from `vendor/autoload.php`.
- Set SMTP credentials in `config/apikeys.php`.
- Test outbound email delivery by navigating to `https://your-domain.com/send-email.php`.

---

## 5. Core Subsystems Verification

After deployment, test the following endpoints to verify all components:

| Endpoint                   | Purpose                                                                  | Expected Status  |
| :------------------------- | :----------------------------------------------------------------------- | :--------------- |
| `/index.php`               | Homepage with dynamic Verified Directory & Reviews showcase              | HTTP 200         |
| `/marketplace.php`         | Business & Product Directory with WhatsApp/Phone filters                 | HTTP 200         |
| `/ugc-generator.php`       | AI UGC Image Creative Studio with Canva-style canvas editor              | HTTP 200         |
| `/ugc-video-studio.php`    | AI Video Studio — Veo/Nano Banana 2, edit, regenerate, download, run ads | HTTP 200 (login) |
| `/post-creator.php`        | AI caption + Nano Banana 2 illustration → live multi-network publish     | HTTP 200 (login) |
| `/developer-docs.php`      | Encyclopedic A–Z Developer Documentation & code generators               | HTTP 200         |
| `/api-settings.php`        | Master API Key console, Rate limit monitor & Webhook Tester              | HTTP 200         |
| `/status.php`              | Live service uptime & network latency telemetry                          | HTTP 200         |
| `/security-2fa.php`        | Google Authenticator RFC 6238 & Email OTP 2FA setup                      | HTTP 200         |
| `/admin-login.php`         | Administrative login with TOTP 2FA verification challenge                | HTTP 200         |
| `/admin/index.php`         | Admin dashboard — 25 modules, user management, maintenance mode          | HTTP 200 (admin) |
| `/portfolio.php?u=demo`    | Dedicated public portfolio view (accessible without login)               | HTTP 200         |
| `/vcard-download.php?id=3` | RFC 6350 compliant digital vCard download (.vcf)                         | HTTP 200 (vcard) |
| `/reviews.php`             | Community review submission portal (Pro vs Free 5-day rule)              | HTTP 200         |
| `/send-email.php`          | Outbound HTML Email Studio with CKEditor 5                               | HTTP 200         |
| `/p2p-chat.php`            | P2P Chat with AI Growth Consultant bot & deal proposals                  | HTTP 200         |
| `/blog-manage.php`         | CKEditor 5 Blog Article Creator & Manager                                | HTTP 200         |
| `/404.php`                 | Custom 404 Error handler with live search                                | HTTP 404         |

### Feature Smoke Test (after first login)

1. **Image generation** — UGC Studio → generate a creative. With `GEMINI_API_KEY` set, the result reports `gemini-3-pro-image-preview` (Nano Banana 2); without a key it uses first-party fallback stills and still works.
2. **Video generation** — Video Studio → *Generate video*. Scenes come back with same-origin stills, the browser renders an MP4/WebM, and the library card offers **Edit / Regenerate / Download / Run ad / Post**.
3. **Edit + Regenerate** — hit *Regenerate* on a library card: same script, fresh stills, same creative id, credits re-charged on save.
4. **AI post → social** — Post Creator → *Generate Post Content* → *Make an illustration* → *🚀 Publish Live Now* to every connected channel.


---

## 6. Automated Scheduled Tasks (Cron Jobs)

Set up automated background maintenance jobs via crontab:
```bash
sudo crontab -u www-data -e
```

Add the following schedule:
```cron
# Reset daily ad campaign budgets at midnight UTC
0 0 * * * php /var/www/seopp/cron/daily_budget_reset.php >> /var/log/adsbooster_cron.log 2>&1

# Run click-fraud and IP anomaly monitor every 15 minutes
*/15 * * * * php /var/www/seopp/cron/fraud_monitor.php >> /var/log/adsbooster_cron.log 2>&1

# Sync social media metrics and latency pings hourly
0 * * * * php /var/www/seopp/cron/social_sync.php >> /var/log/adsbooster_cron.log 2>&1
```

---

## 7. Production Security & Hardening Checklist

- [ ] **HTTPS / SSL Certificate**: Install a free SSL certificate via Certbot:
  ```bash
  sudo certbot --apache -d adsbooster.net -d www.adsbooster.net
  ```
- [ ] **Database Access Restriction**: Ensure MySQL listens on `127.0.0.1` and is not exposed to the public internet (`bind-address = 127.0.0.1` in `my.cnf`).
- [ ] **Error Visibility**: Ensure `display_errors = Off` and `log_errors = On` in production `php.ini`.
- [ ] **Admin Default Password**: Login as `admin@adsbooster.net` and change the default administrative password immediately.
- [ ] **2FA Enforcement**: Enable Two-Factor Authentication on all administrative accounts via `/security-2fa.php`.
- [ ] **Upload File Type Whitelisting**: Handled in PHP backend via `finfo_file()` MIME-type validation. Never trust file extensions alone.

---

## 8. Troubleshooting & FAQ

#### Q: How do I test the custom error pages?
Directly request a non-existent URL (e.g., `https://your-domain.com/non-existent-page`) to test `404.php`, or visit `/500.php` / `/403.php` to verify dark-mode error styling and redirection buttons.

#### Q: What if Groq AI returns a 404 model error?
The `AIEngine` class in `modules/ai_engine.php` includes automated model failovers. If `openai/gpt-oss-120b` is unavailable, it cascades to `qwen/qwen3.8-27b`, `openai/gpt-oss-20b`, and local heuristic synthesis without terminating user sessions.

#### Q: Where are email dispatch audit logs stored?
All outbound emails sent through `EmailService::sendBrandedMail` or `EmailService::sendDirectEmail` are logged in the `email_logs` MySQL table with status, recipient, and timestamps.

---
*Maintained by the Adsbooster Engineering Team. For technical inquiries, contact support@adsbooster.net.*
