# Tarmac Configuration Summary

This document summarizes the configurations added from the Transipal project to make Tarmac production-ready for Heroku deployment.

## ✅ Configurations Added

### 1. Root Domain to WWW Subdomain Redirection

**File:** `src/Controller/Public/RedirectController.php`

**Purpose:** Automatically redirects users from the bare domain to the www subdomain for consistency and SEO.

**Features:**
- Redirects `tarmac.app` → `www.tarmac.app` (permanent redirect, 301)
- Redirects Heroku URLs (`app-name.herokuapp.com`) to custom domain (temporary redirect, 302)
- High priority routing to catch all paths

**Examples:**
```
https://tarmac.app/login → https://www.tarmac.app/login
https://tarmac-prod.herokuapp.com → https://www.tarmac.app
```

---

### 2. Trusted Proxies Configuration

**File:** `config/packages/framework.yaml`

**Purpose:** Configures Symfony to trust Heroku's proxy infrastructure for proper IP detection and HTTPS handling.

**Configuration:**
```yaml
trusted_proxies: "%env(TRUSTED_PROXIES)%"
trusted_headers:
    - "x-forwarded-for"
    - "x-forwarded-proto"
    - "x-forwarded-port"
```

**Why it matters:**
- Correct user IP addresses in logs
- Proper HTTPS detection behind proxies
- Required for Heroku deployment

**Environment Variable:**
```bash
TRUSTED_PROXIES="10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
```

---

### 3. Monolog Email Alert Strategy

**Files:**
- `config/packages/monolog.yaml` (updated for production)
- `src/Service/Logging/TwigEmailHandler.php`
- `src/Service/Logging/UserLogProcessor.php`
- `src/Service/Logging/ErrorEmailFormatter.php`
- `templates/email/error_alert.html.twig`
- `config/services.yaml` (service definitions)

**Purpose:** Sends email alerts to administrators when critical errors occur in production.

**Features:**
- **Automatic email alerts** for critical errors (level 500+)
- **Rich error context** including:
  - User information (authenticated user, roles, email)
  - Request information (URL, HTTP method, IP address, referrer)
  - Error details (message, time, level, channel)
  - Exception trace with stack trace
- **Mobile-optimized email template** with professional styling
- **Distinguishes between**:
  - Web request errors (with full HTTP context)
  - Background job/CLI errors (with job context)

**Configuration in production:**
```yaml
when@prod:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: error
                handler: grouped
            grouped:
                type: group
                members: [nested, twig_email]  # Both log AND email
            twig_email:
                type: service
                id: App\Service\Logging\TwigEmailHandler
```

**Service Configuration:**
```yaml
App\Service\Logging\TwigEmailHandler:
    arguments:
        $fromEmail: "%env(MAILER_FROM_EMAIL)%"
        $toEmail: "%env(MAILER_ADMIN_EMAIL)%"
        $subject: '[Tarmac] Critical Error Alert'
        $level: 500 # Critical level
```

**Environment Variables:**
```bash
MAILER_FROM_EMAIL="noreply@tarmac.app"
MAILER_ADMIN_EMAIL="admin@tarmac.app"
MAILER_DSN="smtp://user:pass@smtp.example.com:587"
```

**How it works:**
1. Critical error occurs (500+ level)
2. `TwigEmailHandler` captures the error
3. `UserLogProcessor` adds user information
4. `WebProcessor` (Monolog built-in) adds HTTP request information
5. Email is sent using the `error_alert.html.twig` template
6. Error is also logged to stderr (for Heroku logs)

---

### 4. AWS S3 Configuration (Optional)

**Files:** 
- `app.json` (environment variables)
- `config/services.yaml` (ready for AWS client configuration)

**Purpose:** Placeholder configuration for future AWS S3 file storage integration.

**Environment Variables Added:**
```bash
AWS_S3_URL="https://your-bucket.s3.amazonaws.com"
AWS_S3_REGION="eu-west-3"
AWS_S3_BUCKET="your-bucket-name"
AWS_S3_ACCESS_ID="your-access-key"
AWS_S3_ACCESS_SECRET="your-secret-key"
```

**Note:** The AWS S3 integration is not actively used yet but is configured for future use. To implement:

1. Install required packages:
   ```bash
   composer require aws/aws-sdk-php league/flysystem-aws-s3-v3 oneup/flysystem-bundle
   ```

2. Add S3 client to `config/services.yaml`:
   ```yaml
   s3_client:
       class: Aws\S3\S3Client
       arguments:
           - version: 'latest'
             region: '%env(AWS_S3_REGION)%'
             credentials:
                 key: '%env(AWS_S3_ACCESS_ID)%'
                 secret: '%env(AWS_S3_ACCESS_SECRET)%'
   ```

3. Configure Flysystem in `config/packages/oneup_flysystem.yaml`

---

## 📋 Updated Files Summary

### New Files Created:
1. `src/Controller/Public/RedirectController.php` - Domain redirection
2. `src/Service/Logging/TwigEmailHandler.php` - Email alert handler
3. `src/Service/Logging/UserLogProcessor.php` - User context processor
4. `src/Service/Logging/ErrorEmailFormatter.php` - Email formatter (backup)
5. `templates/email/error_alert.html.twig` - Error email template
6. `CONFIGURATION_SUMMARY.md` - This file

### Files Modified:
1. `config/packages/framework.yaml` - Added trusted proxies
2. `config/packages/monolog.yaml` - Added email handler for production
3. `config/services.yaml` - Added logging service definitions
4. `app.json` - Added email and AWS environment variables
5. `HEROKU_DEPLOYMENT.md` - Updated documentation

---

## 🔄 How the Configurations Work Together

### Production Error Flow:
```
1. Critical Error Occurs
   ↓
2. Monolog "fingers_crossed" captures it
   ↓
3. Grouped Handler triggers both:
   ├─→ nested: Logs to stderr (Heroku logs)
   └─→ twig_email: Sends email alert
   ↓
4. Email sent to admin with:
   - User context (from UserLogProcessor)
   - HTTP context (from WebProcessor)
   - Error details and stack trace
```

### Proxy Trust Flow:
```
User Request
   ↓
Heroku Proxy (adds X-Forwarded-* headers)
   ↓
Symfony (trusts proxy, reads real IP from headers)
   ↓
Application (sees correct user IP and HTTPS status)
```

### Domain Redirection Flow:
```
User visits: https://tarmac.app/dashboard
   ↓
RedirectController matches (priority 100)
   ↓
301 Redirect to: https://www.tarmac.app/dashboard
   ↓
Normal route handling
```

---

## 🚀 Next Steps

### To Enable Email Alerts in Production:

1. **Configure a mailer in Heroku:**
   ```bash
   # Using SendGrid (recommended for Heroku)
   heroku addons:create sendgrid:starter
   
   # Or configure custom SMTP
   heroku config:set MAILER_DSN="smtp://user:pass@smtp.example.com:587"
   ```

2. **Set email addresses:**
   ```bash
   heroku config:set MAILER_FROM_EMAIL="noreply@tarmac.app"
   heroku config:set MAILER_ADMIN_EMAIL="your-email@example.com"
   ```

3. **Test the configuration:**
   ```bash
   # Trigger a test error in dev/staging first
   heroku run php bin/console app:test:error --env=staging
   ```

### To Enable AWS S3 (Optional):

1. **Install dependencies:**
   ```bash
   composer require aws/aws-sdk-php league/flysystem-aws-s3-v3 oneup/flysystem-bundle
   ```

2. **Configure environment variables:**
   ```bash
   heroku config:set AWS_S3_URL="https://your-bucket.s3.amazonaws.com"
   heroku config:set AWS_S3_REGION="eu-west-3"
   heroku config:set AWS_S3_BUCKET="your-bucket"
   heroku config:set AWS_S3_ACCESS_ID="your-key"
   heroku config:set AWS_S3_ACCESS_SECRET="your-secret"
   ```

3. **Configure Flysystem** (see AWS S3 section above)

---

## 📱 Mobile-First Considerations

All email templates follow mobile-first principles:
- **Responsive design** - Works on all screen sizes
- **Touch-friendly** - Large buttons and tap targets
- **Optimized content** - Readable on small screens
- **Inline styles** - Compatible with all email clients

---

## 🔒 Security Considerations

1. **Never commit secrets** - All sensitive data is in environment variables
2. **Trusted proxies** - Only trust Heroku's internal network ranges
3. **Email rate limiting** - Errors are buffered to prevent email floods
4. **Production-only** - Email alerts only trigger in production environment

---

## 📚 Additional Resources

- [Symfony Trusted Proxies](https://symfony.com/doc/current/deployment/proxies.html)
- [Monolog Handlers](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md)
- [Heroku PHP Support](https://devcenter.heroku.com/categories/php-support)
- [AWS S3 with Symfony](https://symfony.com/doc/current/components/filesystem.html)

---

*Last updated: October 23, 2025*

