-- Set the freshly derived TRON address as the store's USDT address
UPDATE settings SET usdt_address = 'TC2apTWVEZ3HMRbcgEDCr9vXaUtmKhCPWo' WHERE id = 1;

-- Insert a test transaction for 1 USDT using that address
INSERT INTO transactions (order_id, customer_name, customer_email, real_amount, payment_amount, address, network, status)
VALUES ('test-pool-1001', 'Pool Test', 'test@example.com', 1.0, 1.0, 'TC2apTWVEZ3HMRbcgEDCr9vXaUtmKhCPWo', 'TRON', 'pending');
