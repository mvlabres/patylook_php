ALTER TABLE `payment_type`
  ADD COLUMN `category` VARCHAR(20) NOT NULL DEFAULT 'cash',
  ADD CONSTRAINT `chk_payment_category` CHECK (`category` IN ('cash', 'credit', 'debit'));