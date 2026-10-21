<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0750, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        caption TEXT NOT NULL DEFAULT "",
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT "",
        description TEXT NOT NULL DEFAULT "",
        price_paise INTEGER NOT NULL DEFAULT 0,
        mrp_paise INTEGER,
        image TEXT NOT NULL,
        in_stock INTEGER NOT NULL DEFAULT 1,
        visible INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS workshops (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        tag TEXT NOT NULL DEFAULT "",
        duration TEXT NOT NULL DEFAULT "",
        mode TEXT NOT NULL DEFAULT "",
        levels TEXT NOT NULL DEFAULT "",
        age TEXT NOT NULL DEFAULT "",
        items TEXT NOT NULL DEFAULT "",
        note TEXT NOT NULL DEFAULT "",
        image TEXT NOT NULL DEFAULT "",
        sort_order INTEGER NOT NULL DEFAULT 0,
        visible INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        body TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        ip_hash TEXT,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT NOT NULL,
        at INTEGER NOT NULL
    )');

    seed_defaults($pdo);

    return $pdo;
}

/** Fills the editable content with the original copy once. Deleting it later does not bring it back. */
function seed_defaults(PDO $pdo): void
{
    $has = $pdo->prepare('SELECT 1 FROM settings WHERE key = ?');

    $has->execute(['seeded_workshops']);
    if (!$has->fetchColumn()) {
        $rows = [
            ['Reiki Healing', 'Level 1', '2 day workshop', 'Offline', '3 learning levels', 'Age 7 yrs & above', [
                "Reiki Healing's deeper meaning and facts", 'About 7 Chakras and their healing techniques',
                'Learn about the Mudras and their connectivity', 'Attunement process by the teacher',
                'Hands-on practice on self and others', 'Wish fulfilling meditation', 'Focused breathing',
                'Guided grounding meditation technique', 'Guidelines on intention creation',
            ], 'Course content and certificate provided.'],
            ['Merlin Trinity Healing System', 'Level 1', '2 day workshop', 'Online', '5 learning levels', '', [
                'What is Merlin Healing?', 'About 7 chakras and their healing techniques',
                "3 Sacred Master Merlin's symbol attunement process by the teacher",
                'Hands-on practice of the symbol, full body', 'Guided Higher Self meditation',
                'Deep healing and guidance', 'Breathing technique',
            ], 'Course content and certificate provided.'],
            ['Merlin Cleanser', 'Level 1', '1 day workshop', 'Online', '1 learning level', '', [
                "Master Merlin's sacred symbol", 'Attunement process by the teacher', 'Healing guidance',
                'Uses of the symbol in the real world', 'Guided deep healing meditation',
            ], 'Course content provided.'],
            ['Angel Healing', 'Level 1', '1 day workshop', 'Online / Offline', '', '', [
                'Meaning of Angels', 'Angel realms', 'Guided Angel meditation', 'Cord cutting technique',
                'How to connect with the angels', 'Angel signs and messages', 'Power of prayer and surrender',
            ], ''],
            ['Angel Healing & Card Reading', 'Level 2', '2 day workshop', 'Offline', '', '', [
                'Angel card reading. You receive a card deck in this workshop', 'Hands-on practice',
                'Sacred cleansing techniques', 'Guided Mediumship meditation', 'Angel Assisted Vacuuming technique',
                'Cord cutting, and taking back your power', 'Guided meditation for intuition and spiritual growth',
            ], 'Course content and certificate provided.'],
        ];
        $insert = $pdo->prepare('INSERT INTO workshops (name, tag, duration, mode, levels, age, items, note, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $i => $r) {
            $insert->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], implode("\n", $r[6]), $r[7], $i + 1, time()]);
        }
        $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute(['seeded_workshops', '1']);
    }

    $has->execute(['healing_services']);
    if (!$has->fetchColumn()) {
        $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute([
            'healing_services',
            implode("\n", ['House / Office cleansing', 'Inner child healing', 'Past life healing', 'Vacuuming technique', 'Akashic Record reading', 'Angel card reading']),
        ]);
    }
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
}
