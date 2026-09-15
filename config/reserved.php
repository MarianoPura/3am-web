<?php

declare(strict_types=1);

/**
 * RESERVED PATHS — legacy application protection
 * ==============================================
 *
 * The single highest-risk constraint in this project: 3ammediatech.com hosts
 * existing applications (/armonyx, /trebl, ...) that must keep working exactly
 * as they do today. The new site sits at the apex and catches everything else.
 *
 * Two independent guards use this list, and they are deliberately redundant:
 *
 *   1. ReservedPath middleware — refuses the request with a hard 404 before a
 *      single database query runs. The new site will never render a page at a
 *      legacy path, even if a matching row somehow exists.
 *
 *   2. Slug validation in the admin — refuses to save any slug that would
 *      collide. A CMS row can never come to shadow a live application.
 *
 * The web server is the real enforcement layer (legacy paths are matched first
 * and never reach PHP). These guards exist because a web-server config can be
 * edited by someone who has not read this file.
 */

return [

    /*
     * Explicitly reserved first path segments.
     *
     * Keep in sync with bin/legacy-paths.txt and with the web-server config.
     * ★ Populate fully from section 4 of the Phase 0 server audit. ★
     */
    'paths' => [
        // ── Existing applications — DO NOT REMOVE ──
        'armonyx',
        'trebl',
        // TODO(phase-0): append every remaining app directory from audit §4.

        // ── This application's own reserved namespaces ──
        'admin',
        'api',
        'assets',
        'build',
        'storage',
        'uploads',
        'vendor',

        // ── Well-known / conventional ──
        'cgi-bin',
        'phpmyadmin',
        'webmail',
        'cpanel',
        '.well-known',
    ],

    /*
     * Belt and braces: also treat any real directory in the legacy document
     * root as reserved, whether or not it is listed above.
     *
     * This is what makes the guard self-maintaining — an app deployed to
     * /var/www/html/newapp is protected the moment it exists, without anyone
     * remembering to edit this file. That is the failure mode most likely to
     * actually happen.
     *
     * Set to null to disable the scan (results are cached; see 'scan_ttl').
     */
    'scan_docroot' => env('LEGACY_DOCROOT', '/var/www/html'),

    /*
     * Seconds to cache the directory scan. The legacy app list changes rarely,
     * and this runs on every request — do not set it low.
     */
    'scan_ttl' => 3600,

    /*
     * Reserved slugs for CMS content, beyond the path list above.
     *
     * These would not break a legacy app, but they would collide with this
     * site's own routes — a project slugged "contact" would be unreachable.
     */
    'slugs' => [
        'about', 'work', 'services', 'ventures', 'clients', 'contact',
        'insights', 'careers', 'privacy', 'terms', 'search',
        'sitemap', 'sitemap.xml', 'robots.txt', 'feed', 'rss',
        'login', 'logout', 'register', 'password', 'account',
        'media', 'technology', 'events', // the three channel routes
        '404', '500', 'error',
    ],
];
