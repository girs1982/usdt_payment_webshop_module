-- 1) Create pool table
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL UNIQUE,
  `privkey` VARCHAR(64) NOT NULL,
  `assigned` TINYINT(1) NOT NULL DEFAULT 0,
  `swept` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Store mnemonic + new master address
UPDATE `settings`
SET `mnemonic` = 'tissue suggest badge roast vintage tomato emerge prefer orbit night front divorce',
    `usdt_address` = 'TC2apTWVEZ3HMRbcgEDCr9vXaUtmKhCPWo'
WHERE id = 1;
