# DevStage01 Setup Guide

**Target:** Fresh Laravel 13 project + Track PMS modules 1,5,6,7,8,9,10,11,12

---

## Step 1: Initialize Laravel Project

```bash
cd /c/Users/User/Desktop/Claude/ClaudeCode/Aril/ProjectAI/SpecialProject/DevStage01

# Option A: Fresh Laravel 13
composer create-project laravel/laravel .

# Option B: Use specific Laravel version if 13 not released
composer create-project laravel/laravel . "11.*"
```

**Verify:**
```bash
php artisan --version
# Should show: Laravel Framework 13.x.x (or 11.x.x)
```

---

## Step 2: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```ini
APP_NAME="DevStage01"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=devstage01
DB_USERNAME=root
DB_PASSWORD=

# AI/RAG keys (get after extraction)
ANTHROPIC_API_KEY=
VOYAGE_API_KEY=
```

**Create database:**
```bash
mysql -u root -p
CREATE DATABASE devstage01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

---

## Step 3: Run Track PMS Extraction

```bash
cd ../ProjectManagement
bash scripts/extract-to-devstage01.sh
```

**What it extracts:**
- 22 services (AI/RAG, Notifications, Reports, Workload)
- 18 models (ChatSession, User, FeedbackItem, Release, etc.)
- 12 policies (permission system)
- ~15 controllers
- 4 artisan commands
- ~25 migrations
- ~30 views
- Shared traits

**Excludes:**
- WBS/Budget/Ledger (financial tracking)
- S-curve/Gantt (charts)
- Claim milestones
- Baseline import

---

## Step 4: Install Dependencies

```bash
cd ../DevStage01

composer require anthropic-ai/sdk
composer require google/apiclient
composer require barryvdh/laravel-dompdf
```

---

## Step 5: Merge Routes

Extraction creates `routes/web.extracted-reference.php` — merge relevant routes into `routes/web.php`.

**Key route groups to add:**
```php
// Chat/RAG
Route::middleware('auth')->prefix('chat')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/message', [ChatController::class, 'send'])->name('chat.send');
});

// Feedback
Route::middleware('auth')->prefix('feedback')->group(function () {
    Route::get('/', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/', [FeedbackController::class, 'store'])->name('feedback.store');
});

// Admin feedback triage
Route::middleware(['auth', 'can:admin'])->prefix('admin/feedback')->group(function () {
    Route::get('/', [Admin\FeedbackController::class, 'index'])->name('admin.feedback.index');
    Route::patch('/{feedback}/triage', [Admin\FeedbackController::class, 'triage'])->name('admin.feedback.triage');
});

// Notifications
Route::middleware('auth')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
```

Delete `routes/web.extracted-reference.php` after merging.

---

## Step 6: Register Services

Edit `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // RAG System
    $this->app->singleton(\App\Services\Rag\RagService::class);
    $this->app->singleton(\App\Services\Rag\AnthropicClient::class);
    $this->app->singleton(\App\Services\Rag\VoyageClient::class);
    
    // Other services
    $this->app->singleton(\App\Services\NotificationService::class);
    $this->app->singleton(\App\Services\WorkloadService::class);
    $this->app->singleton(\App\Services\MonthlyReportService::class);
    $this->app->singleton(\App\Services\ProjectInsightService::class);
}
```

---

## Step 7: Register Policies

Edit `app/Providers/AuthServiceProvider.php`:

```php
protected $policies = [
    \App\Models\FeedbackItem::class => \App\Policies\FeedbackItemPolicy::class,
    \App\Models\Project::class => \App\Policies\ProjectPolicy::class,
    \App\Models\User::class => \App\Policies\UserManagementPolicy::class,
    // Add others as needed
];
```

---

## Step 8: Run Migrations

```bash
php artisan migrate
```

**Tables created (~25):**
- `users`, `project_assignments`, `departments`, `positions`
- `chat_sessions`, `chat_messages`, `rag_documents`, `rag_chunks`
- `feedback_items`, `feedback_attachments`, `audit_logs`
- `notifications`
- `monthly_reports`, `report_deliveries`
- `releases`
- `calendar_events`
- `project_insights`, `sentiment_scores`

---

## Step 9: Seed Initial Data

Create `database/seeders/InitialDataSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@devstage01.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        
        // Create system settings table if not exists
        \DB::table('system_settings')->insert([
            ['key' => 'anthropic_api_key', 'value' => env('ANTHROPIC_API_KEY', ''), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'voyage_api_key', 'value' => env('VOYAGE_API_KEY', ''), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
```

Run:
```bash
php artisan db:seed --class=InitialDataSeeder
```

---

## Step 10: Schedule Commands

Edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Monthly reports (1st of month at midnight)
    $schedule->command('reports:generate-monthly')->monthlyOn(1, '00:00');
    
    // Sync changelog daily
    $schedule->command('releases:sync')->daily();
}
```

---

## Step 11: Configure AI Keys

Get API keys:
- **Anthropic:** https://console.anthropic.com/
- **Voyage:** https://www.voyageai.com/

Add to `.env`:
```ini
ANTHROPIC_API_KEY=sk-ant-...
VOYAGE_API_KEY=pa-...
```

Update database:
```bash
php artisan tinker
>>> \DB::table('system_settings')->where('key', 'anthropic_api_key')->update(['value' => env('ANTHROPIC_API_KEY')]);
>>> \DB::table('system_settings')->where('key', 'voyage_api_key')->update(['value' => env('VOYAGE_API_KEY')]);
```

---

## Step 12: Test Modules

```bash
# Start dev server
php artisan serve

# In another terminal, test
php artisan test --filter=Chat
php artisan test --filter=Feedback
php artisan test --filter=Notification
```

**Manual tests:**
1. Visit http://localhost:8000/chat — AI chat interface
2. Visit http://localhost:8000/feedback — feedback form
3. Visit http://localhost:8000/notifications — notification center
4. Login as admin → http://localhost:8000/admin/feedback — triage interface

---

## Step 13: Customize for Your Domain

### Module 1 (AI/RAG): Custom Tools

Edit `app/Services/Rag/Tools/ToolRegistry.php` — replace Track PMS tools with your domain tools:

```php
public static function all(): array
{
    return [
        new ChatTool(),
        // Remove: PlanVsActualTool, QueryClaimsTool, etc.
        // Add YOUR tools:
        new YourDomainTool1(),
        new YourDomainTool2(),
    ];
}
```

### Module 5 (Permissions): Adjust Roles

Edit `app/Models/User.php` — change role enum:

```php
// Track PMS had: admin, director, regular
// Change to YOUR roles:
protected $casts = [
    'role' => 'string', // Options: admin, manager, user, client
];
```

Update policies in `app/Policies/*` to match new role names.

### Module 7 (AI Insights): Custom Prompts

Edit `app/Services/ProjectInsightService.php` — rewrite prompts for your domain:

```php
protected function buildPrompt($project): string
{
    return "Analyze this [YOUR DOMAIN] project and provide insights on [YOUR METRICS]...";
}
```

### Module 9 (Monthly Reports): Custom PDF Template

Edit `resources/views/reports/monthly/template.blade.php` — redesign for your business.

### Module 10 (Workload): Adjust Scoring

Edit `config/workload.php` (if exists) or `app/Services/WorkloadService.php`:

```php
// Track PMS used: behind_schedule, deadline_pressure, contract_value, issues, scope
// Change weights to YOUR factors:
protected const WEIGHTS = [
    'your_factor_1' => 0.30,
    'your_factor_2' => 0.25,
    'your_factor_3' => 0.20,
    // ...
];
```

---

## Module-by-Module Test Plan

| Module | Test URL | Expected Behavior |
|--------|----------|-------------------|
| 1 (RAG) | /chat | Chat interface loads, sends message to Claude |
| 5 (Permissions) | /admin (admin only) | Non-admins get 403 |
| 6 (Feedback) | /feedback | Form submits, shows in admin triage |
| 7 (AI Insights) | Run `InsightService::generate($project)` | Returns JSON with summary/risks |
| 8 (Notifications) | /notifications | Shows bell icon, marks read |
| 9 (Reports) | Run `php artisan reports:generate-monthly` | Creates PDF in storage |
| 10 (Workload) | /workload (if route exists) | Shows heatmap |
| 11 (Changelog) | Run `php artisan releases:sync` | Populates releases table |
| 12 (Calendar) | /calendar (if route exists) | Shows events |

---

## Troubleshooting

### "Class RagService not found"
→ Run `composer dump-autoload`

### "Table system_settings doesn't exist"
→ Check migrations ran: `php artisan migrate:status`

### "Anthropic API error"
→ Verify key in `.env` and `system_settings` table

### "Policies not working"
→ Register in `AuthServiceProvider::$policies`

### "Views not found"
→ Check extraction copied views to `resources/views/`

---

## What's NOT Extracted

Reminder — these Track PMS modules were excluded as requested:

- **Module 2:** Budget/Ledger financial dual-tracking
- **Module 3:** WBS tree + Plan vs Actual
- **Module 4:** S-curve + Gantt charts

If you need these later, run a targeted extraction from Track PMS source.

---

## Next: Build Your Domain Features

Now that foundation modules are installed:

1. **Define your domain models** (Product, Order, Task, etc.)
2. **Create migrations** for domain tables
3. **Build domain-specific RAG tools** (replace Track PMS tools)
4. **Customize permissions** per your org structure
5. **Design reports** for your metrics

All extracted modules are **domain-agnostic foundations** — adapt them to your business logic.

---

**Questions? Check EXTRACTION-MANIFEST.md in ProjectManagement root.**
