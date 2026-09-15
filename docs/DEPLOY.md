# Deployment — drop-in at `/var/www/html/web`

No vhost changes. No edits under `/etc/httpd/conf.d`. Upload the folder and it
serves at `3ammediatech.com/web`, the same way `/armonyx` does.

---

## Step 0 — Confirm `.htaccess` is honoured. Do not skip this.

The application source lives inside the document root, so `app/`, `config/` and
`.env` are reachable by URL. The **only** thing stopping that is `.htaccess`.
If `AllowOverride` is `None` for `/var/www/html`, every rule in this project
does nothing and your database credentials become a public URL.

`/armonyx` almost certainly relies on `.htaccess` too, so this is very likely
already fine — but confirm it rather than assume it:

```bash
# On the server
grep -rn "AllowOverride" /etc/httpd/conf/httpd.conf /etc/httpd/conf.d/ | grep -v '^\s*#'
```

You want `AllowOverride All` (or at least `FileInfo Options Limit AuthConfig`)
for the block covering `/var/www/html`. `AllowOverride None` means stop — the
drop-in approach cannot be made safe, and the app must move outside the docroot.

---

## Step 1 — Upload

Everything in this repository goes to `/var/www/html/web/`, including the
dotfiles. **`.htaccess` files are hidden — most SFTP clients skip them by
default.** If `.htaccess` does not arrive, the site will show a file listing
and serve your source as plain text.

```
/var/www/html/web/
├── .htaccess          ★ the protection — verify it uploaded
├── .env               ★ create in step 2, chmod 600
├── index.php          front controller
├── favicon.svg
├── css/
├── uploads/           (+ .htaccess — no execution)
├── app/               (+ .htaccess — denied)
├── config/            (+ .htaccess — denied)
├── routes/            (+ .htaccess — denied)
├── storage/           (+ .htaccess — denied)
├── bin/               (+ .htaccess — denied)
├── docs/              (+ .htaccess — denied)
└── bootstrap.php      (denied by root .htaccess)
```

Confirm the hidden files landed:

```bash
ls -la /var/www/html/web/ | grep -E '^\.|\.htaccess'
find /var/www/html/web -name '.htaccess'      # expect 8
```

Permissions:

```bash
sudo chown -R apache:apache /var/www/html/web
sudo find /var/www/html/web -type d -exec chmod 755 {} \;
sudo find /var/www/html/web -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/html/web/storage /var/www/html/web/uploads
```

---

## Step 2 — Environment

```bash
cd /var/www/html/web
cp .env.example .env
chmod 600 .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Edit `.env`:

```ini
APP_ENV=local
APP_DEBUG=true                          # errors visible while setting up
APP_URL=https://3ammediatech.com
APP_BASE_PATH=/web                      # auto-detected, but be explicit
APP_KEY=<paste the generated value>
FORCE_HTTPS=true
SESSION_NAME=TAM_SESS                   # must NOT be PHPSESSID — see below
```

Leave `DB_*` empty for now. The shell renders without a database.

### Why `SESSION_NAME` matters here

`/armonyx` and `/trebl` share this domain. If they use the default `PHPSESSID`
at cookie path `/` and this site did too, the cookies would overwrite each
other and users would be logged out of `/armonyx` at random — an intermittent
bug that is almost impossible to trace back to this site. `TAM_SESS` plus the
separate session store set in `index.php` keeps them apart.

---

## Step 3 — Verify

Run all three groups. The middle one is the one that matters.

```bash
# 1. The site loads
curl -sI https://3ammediatech.com/web/ | head -1        # HTTP/… 200
curl -s  https://3ammediatech.com/web/health            # {"status":"ok",…}

# 2. ★ The source is NOT reachable ★
for p in .env config/app.php app/Core/Database.php bootstrap.php \
         composer.json storage/logs/php-error.log routes/web.php; do
  printf '%-34s %s\n' "$p" \
    "$(curl -s -o /dev/null -w '%{http_code}' https://3ammediatech.com/web/$p)"
done
# EVERY line must be 403 (or 404). A 200 anywhere means .htaccess is not being
# read — take the site down and fix AllowOverride before going further.

# 3. Directory listing is off
curl -s https://3ammediatech.com/web/css/ | head -5     # must not list files

# 4. The legacy apps are untouched
curl -sI https://3ammediatech.com/armonyx/ | head -1
curl -sI https://3ammediatech.com/trebl/   | head -1
```

---

## If something breaks

| Symptom | Cause |
|---|---|
| File listing at `/web` | `.htaccess` did not upload, or `AllowOverride None` |
| 500 on every page | Check `storage/logs/php-error.log`. Usually `storage/` not writable, or PHP < 8.2 |
| 404 on every path except `/web/` | Uncomment `RewriteBase /web/` in `.htaccess` |
| `.env` returns 200 | **Stop.** Take the directory offline. `.htaccess` is being ignored |
| Blank white page | Fatal before the error handler — check `php-error.log` and the Apache error log |

```bash
tail -50 /var/www/html/web/storage/logs/php-error.log
sudo tail -50 /var/log/httpd/error_log
```

With `APP_DEBUG=true` exceptions render in the browser (escaped). **Set
`APP_DEBUG=false` and `APP_ENV=production` before this is shown to anyone.**

---

## Later — the apex cutover

`/web` is the development mount. Moving the site to `3ammediatech.com/` is a
separate exercise that does require vhost changes, and it is gated on:

1. The Phase 0 audit (`bin/audit-server.sh`) producing the complete legacy app
   inventory.
2. Every one of those apps listed in `bin/legacy-paths.txt` and in
   `config/reserved.php`.
3. `bin/legacy-smoketest.sh baseline` captured **before** any config change.
4. `bin/legacy-smoketest.sh verify` passing **after** it.

Step 4 is the gate — the difference between believing the legacy apps survived
and knowing it. Nothing about that changes because the site currently lives at
`/web`.
