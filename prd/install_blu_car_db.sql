-- Baza de date dedicată Blue-Car (separată de agent_db / aibotpiese)
-- Rulează din phpMyAdmin sau: mysql -u root -p < install_blu_car_db.sql

CREATE DATABASE IF NOT EXISTS `blu_car_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'blu_car'@'localhost' IDENTIFIED BY 'BluCar2026!';
CREATE USER IF NOT EXISTS 'blu_car'@'127.0.0.1' IDENTIFIED BY 'BluCar2026!';

GRANT ALL PRIVILEGES ON `blu_car_db`.* TO 'blu_car'@'localhost';
GRANT ALL PRIVILEGES ON `blu_car_db`.* TO 'blu_car'@'127.0.0.1';

FLUSH PRIVILEGES;

USE `blu_car_db`;
