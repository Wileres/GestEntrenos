-- Migració per a instal·lacions existents: s'eliminen els equips (les categories passen a dir-se "Equips" a la interfície).
-- Les categories principals dels exercicis es conserven com a categories associades.
INSERT IGNORE INTO exercise_categories (exercise_id, category_id) SELECT id, category_id FROM exercises;
DROP TABLE IF EXISTS training_teams;
DROP TABLE IF EXISTS user_teams;
DROP TABLE IF EXISTS teams;
