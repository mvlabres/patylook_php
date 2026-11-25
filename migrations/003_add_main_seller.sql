-- VL Store: additive change to the EXISTING database.
--
-- Creates `main_seller`, the contact shown in the public showcase (WhatsApp link on each product).
-- `whatsapp` holds digits only, with country and area code (e.g. 5511999999999, no "+", spaces or
-- dashes). Nothing existing is changed or removed.
--
-- Run it once against the existing database (select the database first; do NOT run schema.sql,
-- it contains DROP TABLE statements). Then register the seller, for example:
--
--     INSERT INTO `main_seller` (`name`, `whatsapp`) VALUES ('Valentina Labres', '5511999999999');

CREATE TABLE IF NOT EXISTS `main_seller` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `whatsapp` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;
