# HR_Final_Perusahaan

Human Resources management system built with Laravel 12, Inertia, and React (Vite).

## Tech Stack
- Backend: Laravel 12, PHP 8.2+
- Frontend: React 19, Inertia.js, Vite
- Styling: Tailwind CSS 4
- Testing: Pest

## Requirements
- PHP 8.2+
- Composer
- Node.js 18+ and npm
- SQLite (default) or a configured database

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

## Run (development)
```bash
# Backend server
php artisan serve

# Frontend (Vite)
npm run dev
```

## Build
```bash
npm run build
```

## Quality
```bash
# PHP lint
composer run lint

# JS/TS lint
npm run lint

# Type check
npm run types
```

## Tests
```bash
composer test
```

## Notes
- Default database is SQLite. Update DB settings in `.env` as needed.
- Queue and session use database drivers by default. Run migrations before starting.
