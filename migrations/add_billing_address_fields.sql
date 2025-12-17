-- Add billing address fields to payment_methods table
ALTER TABLE `payment_methods` 
ADD COLUMN `billing_address_line1` varchar(255) DEFAULT NULL AFTER `token`,
ADD COLUMN `billing_address_line2` varchar(255) DEFAULT NULL AFTER `billing_address_line1`,
ADD COLUMN `billing_city` varchar(100) DEFAULT NULL AFTER `billing_address_line2`,
ADD COLUMN `billing_state` varchar(100) DEFAULT NULL AFTER `billing_city`,
ADD COLUMN `billing_postal_code` varchar(20) DEFAULT NULL AFTER `billing_state`,
ADD COLUMN `billing_country` varchar(2) DEFAULT NULL AFTER `billing_postal_code`; 