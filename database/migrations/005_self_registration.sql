-- Auto-registre d'entrenadors: els comptes nous queden pendents fins que un administrador els aprova.
ALTER TABLE users ADD COLUMN pending_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
