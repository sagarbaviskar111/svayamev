# Svayamev website

Plain PHP + SQLite site. No framework, no database server.

## Run locally
Needs PHP 8.1+ with `pdo_sqlite`, `fileinfo` and `gd` enabled.

    php -S localhost:8000 router.php

- Website: http://localhost:8000
- Admin:   http://localhost:8000/admin/login.php

First visit to the admin login shows a one-time "create admin" form. Once the account exists, that form is gone.

`router.php` is for local use only: `php -S` ignores `.htaccess`, so the router repeats its deny rules for `data/`, `inc/` and `uploads/`. It is not needed on live hosting.

## Go live (shared hosting / Apache)
1. Upload everything to `public_html`.
2. Turn on free SSL, then uncomment the HTTPS redirect in `.htaccess`.
3. Open `/admin/login.php` immediately and create the admin account.
4. In the dashboard set the WhatsApp number (country code, digits only).
5. Make sure `data/` and `uploads/` are writable by PHP.

On Nginx, `.htaccess` is ignored. Deny `/data`, `/inc` and PHP execution inside `/uploads` in the server config.

## Editable content (admin menu)
- **Workshops** (`/admin/workshops.php`): add, edit, delete, reorder, hide. Each has name, level label, duration, mode, learning levels, age criteria, course content (one point per line), a note and an optional image. The website cards and the FAQ answers about age, online/offline and certificates are built from these.
- **Healing list** (`/admin/healing.php`): one service per line. Add, change or delete lines; the order is the order on the website.
- **Reviews** (`/admin/reviews.php`): visitors submit name + review on the website. Nothing shows publicly until you press Approve. You can also add, edit, hide and delete reviews yourself. The menu shows a badge with the number waiting.

Review form protection: signed time-limited token, hidden trap field, no links/emails allowed, max 3 reviews per visitor per hour, all output escaped.

## Crystal shop
Admin > **Crystal shop** (`/admin/products.php`): add, edit and delete products with photo, name, category, description, in-stock and show/hide switches. No prices are stored or shown.

On the website a "Shop" section appears once at least one visible product exists. Each card has a quantity stepper and an **Ask price on WhatsApp** button that opens WhatsApp with a ready message (product and quantity) to the number set in the admin dashboard. Sold-out items show "Ask when available". Products also feed the search-engine data and the sitemap.

## SEO setup (do this when you go live)
Edit `inc/config.php`:
- `SITE_URL` = your domain, e.g. `https://svayamev.com` (used for canonical URL, sitemap, social cards)
- `OWNER_NAME` = founder/mentor name (shown under the photo and in search-engine data)
- `SOCIAL_PROFILES` = Instagram / YouTube links

Then submit `https://your-domain/sitemap.xml` in Google Search Console and Bing Webmaster Tools.

Already included: title/description, canonical, Open Graph + Twitter cards, JSON-LD (business, courses, FAQ), `sitemap.xml`, `robots.txt`, favicons + web manifest, WebP images with width/height, gzip + caching rules, one H1 and a clean heading order.

Images are regenerated from `Public/logo.jpeg` and `Public/teacher.jpeg` into `assets/img/`. `Public/` is blocked from the web.

## Security built in
- Passwords hashed with `password_hash`; login limited to 5 tries per 15 min per IP
- CSRF token on every admin form, including logout
- All SQL via prepared statements; all output escaped
- Uploads: MIME + image check, random filenames, re-encoded with GD, PHP execution disabled in `uploads/`
- Session cookie HttpOnly, SameSite=Strict, Secure on HTTPS, 30 min idle timeout
- CSP, X-Frame-Options, nosniff and other headers on every page
