<?php
declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));
define('DB_PATH', BASE_DIR . '/data/site.db');
define('UPLOAD_DIR', BASE_DIR . '/uploads');

// SEO / identity. Set SITE_URL to your live domain, e.g. 'https://svayamev.com' (no trailing slash).
// While empty, the URL is detected from the request, which is fine for local testing only.
const SITE_URL = '';
const SITE_NAME = 'Svayamev';
// Founder / mentor's name. Fill this in so it appears in the photo caption and search-engine data.
const OWNER_NAME = 'Akshata Yogeshwar Bawiskar';
// Social profile URLs (Instagram, YouTube, ...), used for search-engine "sameAs" data.
const SOCIAL_PROFILES = [];

const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const MAX_IMAGE_EDGE = 1800;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_SECONDS = 900;
const SESSION_IDLE_SECONDS = 1800;
