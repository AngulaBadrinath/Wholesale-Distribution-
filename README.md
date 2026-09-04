# Wholesale Distribution Management System

> **Phase 00 — Foundation & Core Application Infrastructure**

A modern, high-performance, modular monolith designed for wholesale distribution operations, built with Laravel 13, Inertia 3, React 19, TypeScript, and Tailwind CSS 4.

---

## 1. Technical Architecture

- **Backend:** Laravel 13 running on PHP 8.5
- **Frontend:** React 19.2, TypeScript, Inertia 3, Vite 8, Tailwind CSS 4, shadcn/ui design tokens
- **Persistence:** PostgreSQL 18 (Alpine)
- **Cache & Queues:** Redis 7 (Alpine) via Predis
- **Local Infrastructure:** Docker Compose
- **Design Direction:** "Premium B2B Commerce × Modern SaaS ERP" (WCAG 2.1 AA compliant)
- **Testing:** PHPUnit / Pest test harness, TypeScript static analysis (`tsc --noEmit`)
- **CI/CD:** GitHub Actions workflow (`.github/workflows/ci.yml`)

---

## 2. Local Development Setup

### Prerequisites
- PHP 8.5+ with extensions: `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `pgsql`, `sodium`, `zip`
- Composer 2.x
- Node.js 22+ & npm 11+
- Docker & Docker Compose

### Step 1: Environment Configuration
Copy the environment template:
```bash
cp .env.example .env
```

### Step 2: Start Infrastructure Services
Start the PostgreSQL 18 and Redis 7 containers:
```bash
docker compose up -d
```
Verify container health:
```bash
docker compose ps
```
- **PostgreSQL 18:** Host port `5433` (mapped to container `5432`)
- **Redis 7:** Host port `6380` (mapped to container `6379`)

### Step 3: Install Dependencies
```bash
# Backend PHP dependencies
composer install

# Frontend Node dependencies
npm install
```

### Step 4: Generate Application Key & Run Migrations
```bash
php artisan key:generate
php artisan migrate
```

### Step 5: Start Development Servers
In separate terminal sessions (or using concurrently):
```bash
# Start Laravel development server
php artisan serve

# Start Vite HMR server
npm run dev
```
Access the application shell at: `http://localhost:8000`  
Access the health check endpoint at: `http://localhost:8000/health`

---

## 3. Verification & Quality Assurance

Run the test suite:
```bash
php artisan test
```

Run TypeScript static type checks:
```bash
npm run type-check
```

Build frontend production assets:
```bash
npm run build
```

---

## 4. Repository Governance & Specifications

This repository is governed by the AI Engineering Constitution and Project Operating System:
- **Constitutional Directives:** [AGENTS.md](AGENTS.md) | [GEMINI.md](GEMINI.md) | [CLAUDE.md](CLAUDE.md)
- **High-Signal Project Snapshot:** [docs/AI_CONTEXT.md](docs/AI_CONTEXT.md)
- **Authoritative Specifications:**
  - 01 — PRD: `docs/Wholesale_Distribution_Management_System_PRD.md`
  - 02 — Technical Architecture: `docs/Wholesale_Distribution_Management_System_Technical_Architecture.md`
  - 03 — Security & Access: `docs/Wholesale_Distribution_Management_System_Security_and_Access.md`
  - 04 — Frontend Specification: `docs/Wholesale_Distribution_Management_System_Frontend_Specification.md`
  - 05 — Feature Ticket List: `docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md`
- **Operating Guidelines:**
  - Invariant Rules: [docs/PROJECT_RULES.md](docs/PROJECT_RULES.md)
  - Execution Flow: [docs/DEVELOPMENT_FLOW.md](docs/DEVELOPMENT_FLOW.md)
  - Phase Roadmap: [docs/BUILD_PHASES.md](docs/BUILD_PHASES.md)
  - Status Tracking: [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md)
  - Checklist: [docs/PROJECT_CHECKLIST.md](docs/PROJECT_CHECKLIST.md)
  - Test Matrix: [docs/TEST_MATRIX.md](docs/TEST_MATRIX.md)
