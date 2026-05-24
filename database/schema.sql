PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT UNIQUE,
    password_hash TEXT,
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    avatar_color TEXT,
    created_at TEXT NOT NULL,
    last_login_at TEXT,
    is_blocked INTEGER NOT NULL DEFAULT 0 CHECK (is_blocked IN (0, 1))
);

CREATE TABLE IF NOT EXISTS packs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT,
    type TEXT NOT NULL CHECK (type IN (
        'daily',
        'weekly',
        'monthly',
        'mood',
        'question',
        'choice',
        'dark',
        'light',
        'action',
        'rare'
    )),
    visual_theme TEXT NOT NULL DEFAULT 'default',
    cards_per_open INTEGER NOT NULL DEFAULT 1 CHECK (cards_per_open > 0),
    daily_limit INTEGER CHECK (daily_limit IS NULL OR daily_limit > 0),
    is_daily_special INTEGER NOT NULL DEFAULT 0 CHECK (is_daily_special IN (0, 1)),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS predictions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pack_id INTEGER NOT NULL,
    title TEXT,
    text TEXT NOT NULL,
    rarity TEXT NOT NULL DEFAULT 'common' CHECK (rarity IN (
        'common',
        'uncommon',
        'rare',
        'epic',
        'legendary',
        'mythic'
    )),
    mood_tag TEXT,
    tone_tag TEXT,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (pack_id) REFERENCES packs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS openings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    guest_id TEXT,
    pack_id INTEGER NOT NULL,
    prediction_id INTEGER NOT NULL,
    rarity TEXT NOT NULL CHECK (rarity IN (
        'common',
        'uncommon',
        'rare',
        'epic',
        'legendary',
        'mythic'
    )),
    user_question TEXT,
    choice_a TEXT,
    choice_b TEXT,
    result_context TEXT,
    opened_at TEXT NOT NULL,
    ip_hash TEXT,
    user_agent_hash TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (pack_id) REFERENCES packs(id) ON DELETE CASCADE,
    FOREIGN KEY (prediction_id) REFERENCES predictions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS saved_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    opening_id INTEGER,
    prediction_id INTEGER NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (opening_id) REFERENCES openings(id) ON DELETE SET NULL,
    FOREIGN KEY (prediction_id) REFERENCES predictions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT,
    icon TEXT,
    condition_type TEXT NOT NULL,
    condition_value INTEGER NOT NULL DEFAULT 1 CHECK (condition_value > 0),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))
);

CREATE TABLE IF NOT EXISTS user_achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    achievement_id INTEGER NOT NULL,
    unlocked_at TEXT NOT NULL,
    UNIQUE (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    payload_json TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS site_settings (
    "key" TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scope TEXT NOT NULL,
    identifier_hash TEXT NOT NULL,
    action TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 1 CHECK (attempts > 0),
    window_started_at TEXT NOT NULL,
    last_attempt_at TEXT NOT NULL,
    UNIQUE (scope, identifier_hash, action)
);

CREATE TABLE IF NOT EXISTS visitors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    visitor_id TEXT NOT NULL UNIQUE,
    user_id INTEGER NULL,
    first_seen_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    ip_hash TEXT NULL,
    user_agent_hash TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    visitor_id TEXT NOT NULL,
    user_id INTEGER NULL,
    guest_id TEXT NULL,
    event_type TEXT NOT NULL,
    page TEXT NULL,
    entity_type TEXT NULL,
    entity_id INTEGER NULL,
    payload_json TEXT NULL,
    created_at TEXT NOT NULL,
    ip_hash TEXT NULL,
    user_agent_hash TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_packs_slug ON packs(slug);
CREATE INDEX IF NOT EXISTS idx_predictions_pack_rarity_active ON predictions(pack_id, rarity, is_active);
CREATE INDEX IF NOT EXISTS idx_openings_user_opened ON openings(user_id, opened_at);
CREATE INDEX IF NOT EXISTS idx_openings_guest_opened ON openings(guest_id, opened_at);
CREATE INDEX IF NOT EXISTS idx_saved_cards_user ON saved_cards(user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created ON admin_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits(scope, identifier_hash, action);
CREATE INDEX IF NOT EXISTS idx_visitors_user_seen ON visitors(user_id, last_seen_at);
CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at);
CREATE INDEX IF NOT EXISTS idx_events_type_created ON events(event_type, created_at);
CREATE INDEX IF NOT EXISTS idx_events_visitor_created ON events(visitor_id, created_at);
CREATE INDEX IF NOT EXISTS idx_events_user_created ON events(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_events_entity ON events(entity_type, entity_id);
