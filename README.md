# Reside Kenya — residekenya.com

The Reside Ke property website (moved from cPanel / resideke.co.ke), running on Vercel.

## How it's put together

| Folder | What's in it |
|---|---|
| `app/` | The original PHP site — pages, admin panel, form handlers (`app/api/`), shared code (`app/includes/`), templates |
| `public/` | Static files served straight from Vercel's CDN: CSS, JS, logo/images and all existing property photos (`public/uploads/properties/`) |
| `api/index.php` | Single entry point: maps every URL to the matching page in `app/` (same URLs as before, plus `/buy`, `/rent`, `/property/<slug>`, `/sitemap.xml`) |
| `sql/schema.pgsql` | Database structure (Postgres) |
| `vercel.json` | Uses the `vercel-php` runtime (PHP 8.5) |

## Services

- **Database:** Neon Postgres, connected in Vercel (`DATABASE_URL`).
- **New uploads** (photos added in the admin, brochures, client submissions): Vercel Blob (`BLOB_READ_WRITE_TOKEN`). Photos are shrunk in the browser before upload because Vercel accepts at most ~4.5 MB per request.
- **Logins** are stored in the database (`sessions` table), so they work across Vercel's servers.
- **Email:** outgoing email is off. Inquiries, viewing requests, bookings, property submissions and subscribers all land in the admin panel.

## Contact details

Shown on the site from **Admin → Settings**: `residekenya@gmail.com`, `+254 117 959 105`, WhatsApp `254117959105`.

## Local development

```bash
createdb reside && psql reside -f sql/schema.pgsql
cd public && DATABASE_URL=postgres://localhost/reside APP_ENV=dev php -S localhost:8080 ../api/index.php
```
