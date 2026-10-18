-- Dades inicials. L'usuari administrador el crea l'instal·lador (la contrasenya mai es guarda en text pla).
INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrador'), (2, 'trainer', 'Entrenador')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT IGNORE INTO categories (name, sort_order, is_active, created_at, updated_at) VALUES
('Escoleta', 1, 1, NOW(), NOW()),
('Prebenjamí', 2, 1, NOW(), NOW()),
('Benjamí', 3, 1, NOW(), NOW()),
('Aleví', 4, 1, NOW(), NOW()),
('Infantil', 5, 1, NOW(), NOW()),
('Cadet', 6, 1, NOW(), NOW()),
('Juvenil', 7, 1, NOW(), NOW()),
('Primer equip', 8, 1, NOW(), NOW());

INSERT IGNORE INTO smtp_settings (id, host, port, encryption, username, password_enc, from_email, from_name, reply_to, enabled, updated_at)
VALUES (1, '', 587, 'tls', '', NULL, '', 'Joventut Sant Pere Màrtir', '', 0, NOW());

INSERT IGNORE INTO application_settings (setting_key, setting_value, updated_at) VALUES
('club_name', 'Joventut Sant Pere Màrtir', NOW()),
('logo_file', '', NOW());
