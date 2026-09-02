# Business Starter

A lightweight Laravel 12 modular monolith using Vue 3, TypeScript, Inertia, Tailwind CSS, Vite, MySQL, and Laravel session authentication.

## Modules

- `app/Modules/Users` owns the user model, CRUD controller, requests, policy, and `app:create-admin` command.
- `app/Modules/Settings` owns profile and password settings.
- `resources/js/modules/users` is the reference frontend module pattern; `resources/js/modules/settings` follows the same approach.

The application uses a simple `users.is_admin` boundary. There are no roles, permissions, JWTs, Pinia stores, or page REST APIs.

## Setup

1. Create a MySQL database named `business_starter` or change the `DB_*` values in `.env`.
2. Install dependencies:

   ```powershell
   composer install
   npm.cmd install
   ```

3. Generate an application key if `.env` was newly created, then migrate and create the first administrator:

   ```powershell
   php artisan key:generate
   php artisan migrate
   php artisan app:create-admin
   ```

4. Run the application and Vite:

   ```powershell
   php artisan serve
   npm.cmd run dev
   ```

## Verification

```powershell
php artisan test
npm.cmd run typecheck
npm.cmd run build
```
