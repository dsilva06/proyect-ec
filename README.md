# proyect-ec

Plataforma de gestion de torneos de padel (frontend React/Vite + backend Laravel).

## Estructura
- `frontend/`: SPA React + Vite
- `backend/`: API Laravel + Sanctum (Bearer tokens)
- `docs/deploy/nginx.conf.example`: ejemplo de rewrite para SPA en produccion

## Variables criticas
Backend (`backend/.env`):
- `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, `FRONTEND_URL`
- `CORS_ALLOWED_ORIGINS` (origenes explicitos, separados por coma)
- `DB_*`
- `QUEUE_CONNECTION`
- `MAIL_*`
- `LEADS_INBOX_EMAIL` (correo receptor de contactos web)
- `SANCTUM_TOKEN_EXPIRATION` is minutes; tokens expire; logout revokes token.

Frontend (`frontend/.env`):
- `VITE_API_URL` (ej. `http://localhost:8001`)

## Ejecucion local
Backend:
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8001
```

Frontend:
```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

## Scheduler + Queue (produccion)
Registrar cron:
```bash
* * * * * cd /var/www/proyect-ec/backend && php artisan schedule:run >> /dev/null 2>&1
```

Worker de colas:
```bash
php artisan queue:work --tries=3 --timeout=120
```

## Validaciones de release
Backend:
```bash
cd backend
php artisan test
```

Frontend:
```bash
cd frontend
npm run lint
npm run build
```

## Healthcheck
- `GET /up`

## Checklist de produccion
1. `APP_DEBUG=false` y `APP_ENV=production`.
2. `APP_KEY` configurada.
3. `CORS_ALLOWED_ORIGINS` con dominios reales.
4. Cron del scheduler activo.
5. Worker de queue activo.
6. SPA deploy con fallback a `index.html` sin reescribir `/api/*`.

## SPA refresh / deep links
- El frontend usa `BrowserRouter`, asi que refrescar una ruta como `/admin/players` o `/player/profile` requiere fallback del host a `index.html`.
- Netlify: `frontend/public/_redirects` ya incluye `/* /index.html 200`.
- Vercel: `frontend/vercel.json` ya incluye rewrite SPA a `index.html`.
- Nginx: usar el ejemplo en `docs/deploy/nginx.conf.example` y mantener `/api/*` y `/sanctum/*` fuera del fallback.
