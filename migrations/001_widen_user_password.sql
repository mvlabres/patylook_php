-- Patylook: optional, additive change to the EXISTING database.
--
-- The application stores passwords as bcrypt hashes, which are 60 characters long, but
-- `user`.`password` was declared as VARCHAR(32). The application refuses to start until the
-- column is wide enough. This statement only widens the column; no data is changed or removed.
--
-- Run it once against the existing database (select the database first; do NOT run schema.sql,
-- it contains DROP TABLE statements).
--
-- Existing users whose password was stored in the old (<= 32 characters) format will not be able
-- to sign in until a new hash is stored for them. Generate one with:
--     patylook hash-password
-- and apply it with:
--     UPDATE `user` SET `password` = '<hash printed above>' WHERE `username` = '<login>';

ALTER TABLE `user` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;
