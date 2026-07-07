<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

# Product Sync Engine

This repository contains a Laravel application implementing a product sync engine for ERP-to-website/mobile synchronization with MySQL.

## Quick Start

```bash
git clone https://github.com/Rohit-pawar52/VisualEz.git
cd VisualEz
composer install
cp .env.example .env
php artisan key:generate

# Configure MySQL in .env (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
# Create database: mysql -u root -p -e "CREATE DATABASE visualez CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --force
php artisan serve
```

Visit `http://127.0.0.1:8000/api/sync-dashboard` to verify the setup.

## Setup

1. Copy `.env.example` to `.env`.
2. Configure MySQL database:
   ```bash
   # Create database (adjust user/password as needed)
   mysql -u root -p -e "CREATE DATABASE visualez CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
3. Update `.env` with your MySQL credentials:
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=visualez`
   - `DB_USERNAME=root`
   - `DB_PASSWORD=your_password` (if set)
4. Install dependencies:
   - `composer install`
5. Generate app key:
   - `php artisan key:generate`
6. Run migrations:
   - `php artisan migrate --force`

## Authentication with Sanctum

This project now supports API authentication using Laravel Sanctum.

### Register a new user

`POST /api/register`

Request body:

```json
{
  "name": "HR Manager",
  "email": "hr.manager@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Response:

```json
{
  "message": "Registration successful",
  "token": "<sanctum-token>",
  "user": {
    "id": 1,
    "name": "HR Manager",
    "email": "hr.manager@example.com"
  }
}
```

### Login

`POST /api/login`

Request body:

```json
{
  "email": "hr.manager@example.com",
  "password": "password123"
}
```

Response:

```json
{
  "message": "Login successful",
  "token": "<sanctum-token>",
  "user": {
    "id": 1,
    "name": "HR Manager",
    "email": "hr.manager@example.com"
  }
}
```

Use the returned token in the `Authorization: Bearer <token>` header for protected endpoints.

This means HR and managers can create their own accounts without needing a developer to create users manually in the database.

## APIs

### 1. Sync Products

`POST /api/sync-products`

Request body:

```json
[
  {
    "product_code": "P1001",
    "price": 100,
    "stock": 50,
    "updated_at": "2026-06-22 10:00:00",
    "product_name": "Product Name",
    "category": "Category Name",
    "status": "Active"
  }
]
```

Behavior:

- Creates a new product if `product_code` does not exist.
- Updates only when incoming `updated_at` is newer.
- Rejects negative stock.
- Uses transaction and `lockForUpdate()` to avoid race conditions.
- Records sync activity in `product_sync_logs`.

### 2. Product Search

`GET /api/products`

Query parameters:

- `product_code` - partial or full product code match
- `product_name` - partial or full product name match
- `category` - category filter
- `per_page` - pagination size

Returns paginated product data.

### 3. Sync Dashboard

`GET /api/sync-dashboard`

Returns:

- `total_products`
- `created_today`
- `updated_today`
- `skipped_records`
- `failed_records`

## Database design

- `products`
  - `product_code`
  - `product_name`
  - `category`
  - `price`
  - `stock`
  - `status`
  - `last_updated_at`
  - timestamps

- `product_sync_logs`
  - `product_code`
  - `action`
  - `reason`
  - `created_at`

## Assumptions

- ERP may omit optional fields; defaults will be applied.
- Negative stock values are invalid.
- Status values are normalized to `Active` or `Inactive`.
- Simultaneous updates are handled using row locks and transaction retries.

## Verification

- `php artisan route:list`
- `php artisan migrate --force`

## Notes

The project uses MySQL for data persistence. Make sure `DB_USERNAME` and `DB_PASSWORD` in `.env` match your MySQL credentials. If migrations fail, check database connectivity and credentials. Use `php artisan serve` to run the application locally.

## Testing with Postman

A Postman collection is included in `postman_collection.json`. Import it into Postman to test:
- Create products
- Test duplicate/stale updates (skipping)
- Test negative stock (rejection)
- Test concurrent updates
- Search products
- View dashboard

## Project Structure

- `app/Http/Controllers/ProductSyncController.php` - Core sync logic
- `app/Models/Product.php` - Product model
- `app/Models/ProductSyncLog.php` - Sync log model
- `database/migrations/` - Database schema
- `routes/api.php` - API endpoint definitions
- `postman_collection.json` - Postman test collection
