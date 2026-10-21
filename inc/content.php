<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** One entry per non-empty line. */
function lines_to_array(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), fn ($l) => $l !== ''));
}

/** "2 day workshop" -> "P2D" (ISO 8601 duration for search engines), or null. */
function duration_to_iso(string $text): ?string
{
    return preg_match('/(\d{1,2})\s*day/i', $text, $m) ? 'P' . (int) $m[1] . 'D' : null;
}

function mode_to_schema(string $text): ?string
{
    $online = (bool) preg_match('/online/i', $text);
    $offline = (bool) preg_match('/offline|onsite|in[- ]person/i', $text);
    return match (true) {
        $online && $offline => 'blended',
        $online => 'online',
        $offline => 'onsite',
        default => null,
    };
}

/* ---- Review form protection (no cookies needed for visitors) ---- */

function form_secret(): string
{
    $secret = setting('form_secret');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        set_setting('form_secret', $secret);
    }
    return $secret;
}

function make_form_token(): string
{
    $ts = time();
    return $ts . '.' . hash_hmac('sha256', 'review|' . $ts, form_secret());
}

/** Returns an error message, or null when the token is valid. */
function check_form_token(string $token): ?string
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return 'Something went wrong. Please refresh the page and try again.';
    }
    if (!hash_equals(hash_hmac('sha256', 'review|' . $parts[0], form_secret()), $parts[1])) {
        return 'Something went wrong. Please refresh the page and try again.';
    }
    $age = time() - (int) $parts[0];
    if ($age < 4) {
        return 'That was very quick. Please take a moment and try again.';
    }
    if ($age > 21600) {
        return 'This form expired. Please refresh the page and try again.';
    }
    return null;
}

function visitor_hash(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . form_secret());
}

function clean_text(string $text, int $max): string
{
    $text = preg_replace('/[^\P{C}\n]+/u', '', $text) ?? '';
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
    return mb_substr(trim($text), 0, $max);
}
