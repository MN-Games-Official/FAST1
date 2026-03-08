# AI Education App

A school-focused AI writing and learning platform that helps students understand assignments, organize thoughts, improve writing, and develop reasoning skills — without doing the work for them.

## Core Concept

This is an **AI-supported educational writing workspace** (not a generic chatbot). It acts as a hybrid of Google Docs, Grammarly, and an academic coach, designed to reduce performance gaps between students while preserving learning integrity.

## Tech Stack

- **Frontend:** HTML, vanilla JavaScript, TailwindCSS (CDN)
- **Backend:** PHP 8.x
- **Database:** MySQL / MariaDB
- **AI Integration:** Abacus.AI (with provider abstraction for future providers)
- **Deployment:** VPS with Nginx or Apache, app under `public_html/`

## Project Structure

```
public_html/
  index.php              # Landing page
  login.php              # Authentication
  register.php           # User registration
  dashboard.php          # Student/teacher dashboard
  editor.php             # Document editor with AI sidebar
  teacher/
    index.php            # Teacher dashboard
    class.php            # Class management
    assignment.php       # Assignment management
    flags.php            # Integrity flag review
  admin/
    index.php            # Admin dashboard
    policies.php         # Policy rule management
    analytics.php        # Usage analytics
  api/
    auth.php             # Auth API (login/register/logout)
    documents.php        # Document CRUD & autosave
    ai.php               # AI request routing with policy checks
    assignments.php      # Assignment CRUD
    classes.php          # Class & enrollment management
    teacher.php          # Teacher-specific endpoints
    flags.php            # Policy violation management
    exports.php          # Document export
  includes/
    config.php           # Application configuration
    config.sample.php    # Configuration template
    db.php               # PDO database connection
    auth.php             # Authentication helpers
    csrf.php             # CSRF protection
    helpers.php          # Utility functions
    ai_client.php        # AI provider abstraction layer
    policies.php         # Policy engine (anti-cheating, rules)
    schema.sql           # Database schema
  assets/
    css/app.css          # Global styles
    css/editor.css       # Editor-specific styles
    js/editor.js         # Editor logic (formatting, autosave)
    js/ai-sidebar.js     # AI sidebar (chat, mode selection)
  storage/
    uploads/             # User file uploads
    exports/             # Generated exports
    temp/                # Temporary files
```

## Setup

### 1. Database

Create a MySQL database and run the schema:

```bash
mysql -u root -p -e "CREATE DATABASE ai_education CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p ai_education < public_html/includes/schema.sql
```

### 2. Configuration

Copy and edit the config file:

```bash
cp public_html/includes/config.sample.php public_html/includes/config.php
```

Update `config.php` with your database credentials and AI API key.

### 3. Web Server

Point your web server document root to `public_html/`. Example Nginx config:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/public_html;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Key Features

### Document Editor
- Rich text formatting (bold, italic, underline, headings, lists)
- Autosave every 5 seconds
- Revision history
- PDF export
- Word count tracking

### AI Assistant Modes
1. **Assignment Interpreter** — Simplifies instructions, identifies task type
2. **Planner** — Breaks assignments into steps, creates work plans
3. **Brainstorm Coach** — Asks guided questions, encourages original thinking
4. **Outline Builder** — Helps structure intro/body/conclusion
5. **Draft Coach** — Highlights weak areas, suggests improvements
6. **Reasoning Checker** — Identifies unsupported claims, flags weak logic
7. **Reflection Mode** — Asks why the student made certain choices

### Anti-Cheating System
- Pattern-based detection of direct-answer requests
- Configurable enforcement levels (strict / balanced / supportive)
- Educational redirect on refusal (never just blocks — offers alternatives)
- Integrity logging with teacher notifications
- Policy violations tracked with full audit trail

### Teacher Dashboard
- Class creation and student management
- Assignment creation with custom AI rules
- Student progress monitoring
- AI assistance history viewer
- Integrity flag review and resolution

### Admin Panel
- Platform-wide statistics
- Policy rule management (school/class/assignment scopes)
- Usage analytics (AI events, mode distribution, top users)
- User management

### Security
- Bcrypt password hashing (cost 12)
- CSRF token protection on all forms and API calls
- Prepared SQL statements (PDO)
- Secure session handling (httponly, samesite cookies)
- Role-based authorization (student/teacher/admin)
- Rate limiting on AI endpoints
- Server-side policy enforcement

## User Roles

| Role | Capabilities |
|------|-------------|
| **Student** | Create/edit documents, use AI assistant, view assignments |
| **Teacher** | All student capabilities + create classes/assignments, set AI policies, review flags, monitor progress |
| **Admin** | All teacher capabilities + manage schools, global policies, view analytics |

## AI Provider Abstraction

The `AIClient` class supports multiple providers:
- **Abacus.AI** — Primary provider
- **OpenAI** — Compatible API
- **Mock** — Local development without API keys

Set the provider in `config.php`:
```php
define('AI_PROVIDER', 'abacus'); // 'abacus' | 'openai' | 'mock'
define('AI_API_KEY', 'your-api-key');
```

## License

All rights reserved.