-- Equips (dins d'una categoria), jugadors, horaris, calendari i control d'assistència.
-- Els exercicis, entrenaments i entrenadors passen de "categories" a "equips".
-- Per a cada categoria existent es crea un equip amb el mateix identificador i nom, de manera que les assignacions actuals es conserven.

CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_name (name),
    KEY idx_teams_category (category_id),
    CONSTRAINT fk_teams_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO teams (id, category_id, name, is_active, created_at, updated_at)
SELECT id, id, name, is_active, NOW(), NOW() FROM categories;

-- Entrenadors ↔ equips
ALTER TABLE user_categories DROP FOREIGN KEY fk_uc_category;
RENAME TABLE user_categories TO user_teams;
ALTER TABLE user_teams CHANGE category_id team_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_ut_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT;

-- Exercicis ↔ equips
ALTER TABLE exercise_categories DROP FOREIGN KEY fk_ec_category;
RENAME TABLE exercise_categories TO exercise_teams;
ALTER TABLE exercise_teams CHANGE category_id team_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_et_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT;

ALTER TABLE exercises DROP FOREIGN KEY fk_exercises_category;
ALTER TABLE exercises CHANGE category_id team_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_exercises_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT;

-- Entrenaments ↔ equips
ALTER TABLE training_categories DROP FOREIGN KEY fk_tc_category;
RENAME TABLE training_categories TO training_teams;
ALTER TABLE training_teams CHANGE category_id team_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_tt_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT;

-- Horaris d'entrenament: per categoria (per defecte) i per equip (si en té, sobreescriu el de la categoria). weekday: 1 = dilluns ... 7 = diumenge
CREATE TABLE IF NOT EXISTS category_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    PRIMARY KEY (id),
    KEY idx_cs_category (category_id),
    CONSTRAINT fk_cs_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    PRIMARY KEY (id),
    KEY idx_ts_team (team_id),
    CONSTRAINT fk_ts_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dies festius (no hi ha entrenament cap equip)
CREATE TABLE IF NOT EXISTS holidays (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_holidays_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(190) NOT NULL,
    birth_date DATE NULL,
    team_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_players_team (team_id, is_active),
    KEY idx_players_name (full_name),
    CONSTRAINT fk_players_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entrenaments del calendari (fitxa d'assistència). Es creen automàticament a partir dels horaris, excloent els festius.
CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
    source ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_att_session (team_id, session_date, start_time),
    KEY idx_att_date (session_date),
    CONSTRAINT fk_att_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
    session_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    status ENUM('present','late','absent') NOT NULL,
    marked_by INT UNSIGNED NULL,
    marked_at DATETIME NOT NULL,
    PRIMARY KEY (session_id, player_id),
    KEY idx_ar_player (player_id, status),
    CONSTRAINT fk_ar_session FOREIGN KEY (session_id) REFERENCES attendance_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_player FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_user FOREIGN KEY (marked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
