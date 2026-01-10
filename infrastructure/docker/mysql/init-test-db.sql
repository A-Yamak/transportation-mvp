-- ==============================================================================
-- MySQL Initialization Script
-- ==============================================================================
-- This script runs automatically when the MySQL container is first created.
-- It creates the testing database used by PHPUnit tests.
--
-- Note: The main database (transportationapp) is already created by the
-- MYSQL_DATABASE environment variable in compose.yaml.
-- ==============================================================================

-- Create testing database
CREATE DATABASE IF NOT EXISTS alsabiqoon_testing;

-- Grant privileges to application user
GRANT ALL PRIVILEGES ON alsabiqoon_testing.* TO 'transportationapp'@'%';

-- Flush privileges to ensure they take effect
FLUSH PRIVILEGES;
