# Certicode Labs

A Laravel web application project for Certicode Labs.

## Getting Started

### Prerequisites
- PHP 8.1 or higher
- Composer
- Node.js and npm (for frontend dependencies)
- MySQL/PostgreSQL or SQLite

### Installation

1. Install PHP dependencies:
```bash
composer install
```

2. Configure environment:
```bash
cp .env.example .env
```

3. Generate application key:
```bash
php artisan key:generate
```

4. Configure database in `.env` file

5. Run migrations:
```bash
php artisan migrate
```

6. Start development server:
```bash
php artisan serve
```

The application will be available at `http://127.0.0.1:8000`

## Project Structure

- `app/` - Application code (models, controllers, middleware, etc.)
- `config/` - Configuration files
- `database/` - Migrations and seeders
- `public/` - Public assets and entry point
- `resources/` - Views and raw assets
- `routes/` - Application routes (web.php and api.php)
- `storage/` - Application storage
- `tests/` - Test files
- `.env` - Environment configuration

## Common Commands

```bash
# Start development server
php artisan serve

# Run database migrations
php artisan migrate

# Create a new model with controller
php artisan make:model ModelName -c

# Create a new migration
php artisan make:migration create_table_name

# Run tests
php artisan test

# Launch interactive shell
php artisan tinker
```

## Development Guidelines

- Place models in `app/Models/`
- Place controllers in `app/Http/Controllers/`
- Place web routes in `routes/web.php`
- Place API routes in `routes/api.php`
- Place views in `resources/views/`
- Write unit tests in `tests/Unit/`
- Write feature tests in `tests/Feature/`

## License

This project is licensed under the MIT License.
