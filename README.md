# semanticseo-pro-analyzer-20250630_071932

**SemanticSEO Pro Analyzer** is a modular PHP web application and WordPress plugin enabling SEO professionals to perform bulk semantic analysis via the TextRazor API, visualize and export results, schedule recurring analyses, manage subscriptions, and embed directly in WordPress?all within a scalable LAMP- or Docker-based architecture.

Project Plan / Detailed spec:  
https://docs.google.com/document/d/1dpcFCcPllOCYq2dcMEGPrHjoa5hPY3yeXVByVmKHPuo/

---

## Table of Contents

- [Overview](#overview)  
- [Features](#features)  
- [Architecture](#architecture)  
- [Installation](#installation)  
- [Usage](#usage)  
- [Components](#components)  
- [Dependencies](#dependencies)  
- [Environment Variables](#environment-variables)  
- [Testing](#testing)  
- [Contributing](#contributing)  
- [License](#license)  
- [Acknowledgements](#acknowledgements)  

---

## Overview

SemanticSEO Pro Analyzer lets users:

- Register/login with role-based access (Guest, Free, Paid, Admin)  
- Securely store/encrypt TextRazor API keys  
- Perform bulk semantic analysis (immediate & queued) of URL lists or CSV uploads  
- View results in an interactive dashboard (filter, sort, paginate, drill-down)  
- Export results to CSV or schedule recurring analyses with email notifications  
- Manage freemium subscriptions and team seats via Stripe (with webhooks)  
- Embed the analyzer in WordPress via a shortcode  
- Scale with Redis caching, Redis/Laravel-style queue, Monolog logging  
- Comply with WCAG 2.1 AA and support full internationalization (i18n)  
- Run on a traditional LAMP stack or in Docker (PHP-FPM, Nginx, MySQL, Redis)  

---

## Features

- Role-based authentication & authorization with CSRF protection  
- Secure, encrypted TextRazor API key storage  
- Bulk semantic analysis with real-time and background processing  
- Interactive DataTables dashboard with drill-down modals  
- CSV import/export  
- Recurring analysis scheduler (cron or queue-based)  
- Stripe integration (subscriptions, team seats, webhooks)  
- WordPress plugin with settings page and `[semanticseo_analyzer]` shortcode  
- Caching (Redis), queueing (Redis/Laravel Queue or WP-Cron), logging (Monolog)  
- WCAG 2.1 AA compliance and i18n support  
- Dockerized for development & production, with CI/CD pipeline ready  

---

## Architecture

- PSR-4 autoloaded PHP MVC core  
- Folder structure:  
  - **Controllers** (AuthController, AnalysisController, SubscriptionController, etc.)  
  - **Models** & **Repositories** (User, APIKey, Subscription, AnalysisResult, Job)  
  - **Services** (TextRazorService, StripeService, QueueService, CacheService, LoggerService)  
  - **Jobs** (AnalyzeJob, ScheduledJob) & **CLI Worker** (QueueWorker)  
  - **Middleware** (AuthMiddleware, ApiMiddleware, CsrfMiddleware)  
  - **WordPress plugin layer** (semanticseo-pro.php, shortcode.php, settings.php, plugincontroller.php)  
  - **Views/Layouts** (masterlayout.php, partials, page templates)  
  - **Front-end assets** (DataTables, Bootstrap/SCSS, AJAX, embed.js, embed.css)  
- Environment via `.env` (vlucas/phpdotenv)  
- Dependencies: Composer & npm  
- Optional Docker Compose: PHP-FPM, Nginx, MySQL, Redis  

---

## Installation

### Prerequisites

- PHP 7.4+ with PDO, cURL, JSON extensions  
- Composer  
- Node.js & npm  
- MySQL 5.7+ (or MariaDB)  
- Redis  
- Apache/Nginx (or Docker & Docker Compose)  
- WordPress 5.0+ (if using plugin)  

### Clone & Setup

```bash
git clone https://github.com/your-org/semanticseo-pro-analyzer-20250630_071932.git
cd semanticseo-pro-analyzer-20250630_071932
```

1. Copy and configure your environment file:  
   ```bash
   cp .env.example .env
   # Edit .env: DB_*, REDIS_*, TEXT_RAZOR_API_KEY, STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, APP_URL, etc.
   ```
2. Install PHP dependencies:  
   ```bash
   composer install
   ```
3. Install JS/CSS dependencies and build assets:  
   ```bash
   npm install
   npm run build        # for production
   npm run dev          # for development
   ```
4. Run database migrations / seeds:  
   ```bash
   php database.php migrate
   ```
5. Serve the application:  
   - **LAMP**: point your vhost to the project root (or `public/`)  
   - **Built-in PHP server**:  
     ```bash
     php -S localhost:8000
     ```
   - **Docker Compose (optional)**:  
     ```bash
     docker-compose up -d
     ```

### WordPress Plugin Installation

1. Copy `semanticseo-pro.php`, `shortcode.php`, `settings.php`, `plugincontroller.php`, `embed.js`, `embed.css` into `wp-content/plugins/semanticseo-pro/`.  
2. Activate the plugin in WP Admin.  
3. Go to **Settings ? SemanticSEO** to enter your TextRazor API key.  
4. Use the shortcode in any page/post:  
   ```html
   [semanticseo_analyzer]
   ```

---

## Usage

### Web Dashboard

1. Register or log in.  
2. Add or validate your TextRazor API key (encrypted).  
3. Enter URLs (newline-separated) or upload a CSV file.  
4. Click **Analyze** (immediate small batches) or **Queue** (large batches).  
5. View and interact with results (filter, sort, drill-down).  
6. Export to CSV or schedule recurring analyses.  

### CLI Worker

Process background jobs (e.g., in a separate terminal or container):

```bash
php queue_worker.php
# Or inside Docker container:
docker exec -it <php_container> php queue_worker.php
```

### Scheduling

Automate scheduled analyses with cron or scheduler script:

```cron
* * * * * cd /path/to/project && php schedule_runner.php
```

---

## Components

- **index.php** (.php)  
  Entry point and router for the core PHP application; loads dependencies, environment, and dispatches HTTP requests.  
- **app.php** (.php)  
  Application configuration loader; parses `.env`, sets error handling, and provides global settings.  
- **database.php** (.php)  
  Database connection manager; returns a PDO/MySQLi instance, handles migrations/seeding.  
- **authcontroller.php** (.php)  
  Handles user registration, login, logout, and permission checks.  
- **analysiscontroller.php** (.php)  
  Processes URL/CSV input, validates, triggers semantic analysis (immediate or queued), and retrieves results.  
- **textrazorservice.php** (.php)  
  Fetches page HTML and calls TextRazor API endpoints; handles rate limits and response parsing.  
- **queueservice.php** (.php)  
  Manages background job queueing for batch analyses, retries, and worker processing.  
- **csvexporter.php** (.php)  
  Generates CSV files from analysis result sets and streams them to the client.  
- **subscriptioncontroller.php** (.php)  
  Manages user subscription plans, upgrade/downgrade flows, and team seat assignments.  
- **schedulejobcontroller.php** (.php)  
  Handles scheduling, listing, and canceling of recurring analysis jobs.  
- **stripewebhookcontroller.php** (.php)  
  Processes incoming Stripe webhooks for subscription events; verifies signatures.  
- **cacheservice.php** (.php)  
  Provides abstraction over Redis for caching API responses and rate-limit counters.  
- **authmiddleware.php** (.php)  
  Ensures routes are accessed by authenticated users with proper roles; redirects otherwise.  
- **apimiddleware.php** (.php)  
  Validates API requests, enforces API key usage and rate limits.  
- **semanticseo-pro.php** (.php)  
  Main WordPress plugin bootstrap: registers activation/deactivation hooks, enqueues assets, and initializes the plugin.  
- **shortcode.php** (.php)  
  Defines the `[semanticseo_analyzer]` shortcode and renders the embedded analyzer interface.  
- **settings.php** (.php)  
  Implements the WP Settings API to store and retrieve the TextRazor API key in the admin.  
- **plugincontroller.php** (.php)  
  Shared plugin logic for session checks, capability enforcement, and data bridging between WP and the core app.  
- **embed.js** (.js)  
  Front-end JavaScript for the embedded analyzer: handles form submission, AJAX calls, and table rendering.  
- **app.js** (.js)  
  Core front-end JS: initializes UI, binds events, and fetches data from endpoints.  
- **embed.css** (.css)  
  Styles for the embedded WordPress analyzer interface.  
- **masterlayout.php**, **headerpartial.php**, **sidebarmenu.php**, **footerpartial.php** (.php)  
  Base layout and partials that render header, sidebar navigation, and footer.  
- **login.php**, **register.php**, **analyze.php**, **results.php**, **schedule.php** (.php)  
  Page templates for authentication, analysis input, results display, and scheduling.  
- **composer.json** (.json)  
  PHP dependency definitions, autoload settings (PSR-4).  
- **package.json** (.json)  
  JS/CSS dependencies and build scripts.  
- **phpunit.xml** (.xml)  
  PHPUnit configuration for running test suites.  
- **semanticseopro.pot** (.pot)  
  Translation template for WordPress plugin internationalization.  
- **sitemap.xml** (.xml)  
  Static sitemap for SEO and crawler indexing.  

---

## Dependencies

### PHP (composer.json)

- vlucas/phpdotenv  
- predis/predis  
- monolog/monolog  
- stripe/stripe-php  
- phpunit/phpunit (dev)  
- (plus any PSR-compliant routing, queue or helper packages)

### JavaScript (package.json)

- bootstrap  
- jquery  
- datatables.net & datatables.net-bs4  
- webpack (or gulp) & related loaders  
- etc.

### Services

- TextRazor API (semantic analysis)  
- Stripe API (payments, subscriptions)  
- MySQL / MariaDB  
- Redis  

---

## Environment Variables

Define in `.env`:

```
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=semanticseo
DB_USERNAME=root
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_DRIVER=redis

TEXT_RAZOR_API_KEY=your_textrazor_api_key

STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Optional: MAIL_*, PUSH_*, etc. for notifications
```

---

## Testing

Run the PHPUnit test suite:

```bash
vendor/bin/phpunit
```

---

## Contributing

1. Fork the repository  
2. Create a feature branch: `git checkout -b feature/YourFeature`  
3. Commit your changes: `git commit -am "Add YourFeature"`  
4. Push to branch: `git push origin feature/YourFeature`  
5. Open a pull request  

Please follow PSR-12 coding standards and update tests accordingly.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Acknowledgements

- [TextRazor](https://textrazor.com/)  
- [Stripe](https://stripe.com/)  
- [DataTables](https://datatables.net/)  
- [Bootstrap](https://getbootstrap.com/)  
- Inspired by best practices in scalable PHP application design.