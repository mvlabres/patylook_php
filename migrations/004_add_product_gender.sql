-- VL Store: additive change to the EXISTING database.
--
-- Adds the gender classification of each product: 'male' (Masculino), 'female' (Feminino) or
-- 'unisex' (Unissex). Products that already exist are created as 'unisex' so the column is never
-- empty; adjust them afterwards (examples below). No data is removed.
--
-- Run it once against the existing database (select the database first; do NOT run schema.sql,
-- it contains DROP TABLE statements).

ALTER TABLE `product`
  ADD COLUMN `gender` VARCHAR(20) NOT NULL DEFAULT 'unisex' AFTER `brand_id`,
  ADD CONSTRAINT `chk_product_gender` CHECK (`gender` IN ('male', 'female', 'unisex'));

-- Then classify the existing products, for example:
--
--     UPDATE `product` SET `gender` = 'male'   WHERE `id` IN (1, 2);
--     UPDATE `product` SET `gender` = 'female' WHERE `id` = 3;
--
-- (MySQL older than 8.0.16 accepts but does not enforce the CHECK; the application validates it.)
