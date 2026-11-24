-- Patylook: additive change to the EXISTING database.
--
-- Links each product to its brand (`brand`). The column is nullable because products that were
-- registered before brands existed have none. No data is changed or removed.
--
-- Requires the `brand` table (see the `Table patylook.brand` block of schema.sql).
--
-- Run it once against the existing database (select the database first; do NOT run schema.sql,
-- it contains DROP TABLE statements).

ALTER TABLE `product`
  ADD COLUMN `brand_id` INT NULL AFTER `product_category_id`,
  ADD INDEX `fk_product_brand_idx` (`brand_id` ASC),
  ADD CONSTRAINT `fk_product_brand`
    FOREIGN KEY (`brand_id`)
    REFERENCES `brand` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;
