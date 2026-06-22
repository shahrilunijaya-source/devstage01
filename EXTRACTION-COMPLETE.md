# Track PMS Module Extraction — COMPLETE

**Extraction Date:** 2026-06-22  
**Source:** ProjectManagement (Track PMS)  
**Target:** DevStage01  
**Modules Extracted:** 9 (AI/RAG, Permissions, Feedback, AI Insights, Notifications, Monthly Reports, Workload, Changelog, Calendar)

---

## ✅ Completed Steps

### 1. Laravel 13 Initialized
- Fresh Laravel 13.8.0 installed
- Database: `devstage01` (MySQL)
- App name: DevStage01

### 2. Dependencies Installed
```bash
composer require anthropic-ai/sdk google/apiclient barryvdh/laravel-dompdf
```

### 3. Files Extracted
- **21 services** (AI/RAG, Notifications, Reports, Workload, Insights)
- **20 models** (User, ChatSession, FeedbackItem, Release, Project, etc.)
- **12 policies** (permission system)
- **Views** (feedback, workload, notifications, releases, calendar)
- **4 artisan commands** (GenerateMonthlyReports, ReleasesGenerate, ReleasesSync, SentimentBackfill)

### 4. Routes Configured
Routes registered in `routes/web.php`:
- `/chat` — AI chat interface
- `/portfolio/chat` — portfolio-wide chat
- `/feedback` — bug/feature reports
- `/notifications` — notification center
- `/workload` — PM/PE load heatmap
- `/releases` — changelog (What's New)
- `/calendar` — events
- `/projects/{project}/insights` — AI insights per project
- `/projects/{project}/reports/monthly` — monthly PDF reports
- `/admin/feedback` — feedback triage (admin only)

### 5. Services Registered
`app/Providers/AppServiceProvider.php`:
- `RagService` (AI/RAG)
- `AnthropicClient` (Claude API)
- `VoyageClient` (embeddings)
- `NotificationService`
- `WorkloadService`
- `MonthlyReportService`
- `ProjectInsightService`

### 6. Migrations Ran
30 migrations executed:
- users (with role, department_id, position_id)
- projects (stub for FKs)
- project_assignments
- departments, positions, position_levels, salary_bands
- calendar_events
- monthly_reports, report_deliveries
- notifications
- audit_logs
- project_insights
- rag_chunks, rag_documents
- chat_sessions, chat_messages
- sentiment_scores
- feedback_items, feedback_attachments
- releases

### 7. Seeded Data
- Admin user: `admin@devstage01.local` / `password`
- `system_settings` table created with `anthropic_api_key` and `voyage_api_key` placeholders

---

## 🔧 Next Steps (Customization)

### 1. Configure API Keys
Edit `.env`:
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

### 2. Register Commands in Scheduler
Edit `routes/console.php` or `app/Console/Kernel.php` (Laravel 13):
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:generate-monthly')->monthlyOn(1, '00:00');
Schedule::command('releases:sync')->daily();
```

### 3. Customize for Your Domain

#### Module 1 (AI/RAG): Replace Tools
Edit `app/Services/Rag/Tools/ToolRegistry.php`:
```php
public static function all(): array
{
    return [
        new ChatTool(),
        // Remove Track PMS tools:
        // PlanVsActualTool, QueryClaimsTool, etc.
        // Add YOUR domain tools:
        new YourDomainTool1(),
        new YourDomainTool2(),
    ];
}
```

#### Module 5 (Permissions): Adjust Roles
Current roles: `admin`, `director`, `regular`, `client`

Change in `database/migrations/0001_01_01_000000_create_users_table.php` if needed.

Update policies in `app/Policies/*` to match new role names.

#### Module 7 (AI Insights): Custom Prompts
Edit `app/Services/ProjectInsightService.php`:
```php
protected function buildPrompt($project): string
{
    return "Analyze this [YOUR DOMAIN] project and provide insights on [YOUR METRICS]...";
}
```

#### Module 9 (Monthly Reports): Custom PDF Template
Edit `resources/views/reports/monthly/template.blade.php` — redesign for your business.

#### Module 10 (Workload): Adjust Scoring
Edit `app/Services/WorkloadService.php`:
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

### 4. Test Modules

Start dev server:
```bash
php artisan serve
```

Visit:
- `http://localhost:8000/chat` — AI chat
- `http://localhost:8000/feedback` — feedback form
- `http://localhost:8000/notifications` — notifications
- `http://localhost:8000/workload` — workload heatmap
- `http://localhost:8000/releases` — changelog
- `http://localhost:8000/admin/feedback` — feedback triage (admin only)

Login: `admin@devstage01.local` / `password`

Run tests (if exist):
```bash
php artisan test --filter=Chat
php artisan test --filter=Feedback
php artisan test --filter=Notification
```

---

## 📁 What Was NOT Extracted

These Track PMS modules were excluded as requested:

- **Module 2:** Budget/Ledger (financial dual-tracking)
- **Module 3:** WBS tree + Plan vs Actual
- **Module 4:** S-curve + Gantt charts
- All related:
  - Baseline import
  - Claim milestones
  - Weekly cadence (coupled to WBS)
  - Change requests (coupled to baseline)

---

## 📚 Reference Documents

- **Source manifest:** `../ProjectManagement/EXTRACTION-MANIFEST.md`
- **Extraction script:** `../ProjectManagement/scripts/extract-to-devstage01.sh`
- **Setup guide:** `SETUP.md` (this directory)

---

## 🚀 Ready to Go

DevStage01 now has:
- ✅ AI/RAG chat with tool-use framework
- ✅ Multi-role permission system (Admin/Director/Regular/Client)
- ✅ Feedback + activity logging
- ✅ AI insights + sentiment analysis
- ✅ Notifications
- ✅ Monthly PDF reports
- ✅ Workload heatmap
- ✅ Changelog (What's New)
- ✅ Calendar events

All **domain-agnostic foundations** — ready to adapt to your business logic.

Start building your domain-specific features on top! 🎉
