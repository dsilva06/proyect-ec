# Project Structure

## Frontend (React)
- `frontend/src/pages/{admin,player,public}`: Top-level screens.
- `frontend/src/features/*/api.js`: API modules per domain.
- `frontend/src/components/shared`: Reusable shared components.
- `frontend/src/components/ui`: Small UI building blocks.
- `frontend/src/api/httpClient.js`: Single HTTP client wrapper.
- `frontend/src/auth`: Auth context and role helpers.

## Backend (Laravel)
- `backend/routes/api/*.php`: API routes separated by domain.
  - `auth.php`, `statuses.php`, `public.php`, `admin.php`, `player.php`
- `backend/app/Http/Controllers/{Admin,Player,Public}`: API controllers by role.
- `backend/app/Http/Requests`: FormRequest validation.
- `backend/app/Http/Resources`: API resources.
- `backend/app/Services`: Domain services (e.g. ranking/waitlist).
