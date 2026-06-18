# Certicode Labs - Laravel Project

## Project Overview
- **Name:** Certicode Labs
- **Framework:** Laravel
- **Type:** Web Application
- **PHP Version:** 8.1+

## Development Guidelines

### Getting Started
1. Run `php artisan serve` to start the development server
2. The application runs on `http://127.0.0.1:8000`
3. Database migrations can be run with `php artisan migrate`

### Code Organization
- Place models in `app/Models/`
- Place controllers in `app/Http/Controllers/`
- Place routes in `routes/web.php` (web routes) or `routes/api.php` (API routes)
- Place views in `resources/views/`

### Database
- Migrations are stored in `database/migrations/`
- Create new migrations with: `php artisan make:migration create_table_name`
- Seeders are in `database/seeders/`

### Testing
- Unit tests go in `tests/Unit/`
- Feature tests go in `tests/Feature/`
- Run tests with: `php artisan test`

### Environment Configuration
- Copy `.env.example` to `.env` for local development
- Generate app key: `php artisan key:generate`
- Configure database connection in `.env`
