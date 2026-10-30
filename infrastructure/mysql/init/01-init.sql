-- Database Initialization for Clothing Commerce Platform
CREATE DATABASE IF NOT EXISTS `clothing_commerce` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `clothing_commerce_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ensure user permissions
GRANT ALL PRIVILEGES ON `clothing_commerce`.* TO 'sail'@'%';
GRANT ALL PRIVILEGES ON `clothing_commerce_testing`.* TO 'sail'@'%';
FLUSH PRIVILEGES;
