CREATE DATABASE IF NOT EXISTS `grafik_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `grafik_db`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NULL,
    `role` ENUM('admin', 'worker') NOT NULL DEFAULT 'worker',
    `full_name` VARCHAR(255) NOT NULL,
    `is_underage` TINYINT(1) NOT NULL DEFAULT 0,
    `auth_code` VARCHAR(6) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `color_hex` VARCHAR(7) DEFAULT '#4f46e5',
    `is_training` TINYINT(1) NOT NULL DEFAULT 0
);

-- Locations Table
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `is_24_7` TINYINT(1) NOT NULL DEFAULT 0
);

-- User-Locations Pivot Table
CREATE TABLE IF NOT EXISTS `user_locations` (
    `user_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `location_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE CASCADE
);

-- Schedules Table
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `location_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `shift_type` ENUM('day', 'night', 'off', 'training') NOT NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_date` (`user_id`, `date`)
);

-- Absences Table
CREATE TABLE IF NOT EXISTS `absences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_absence` (`user_id`, `date`)
);

-- Demo Data
-- 1. Insert Admin (password: admin)
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `role`, `full_name`, `is_active`) VALUES 
(1, 'admin@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'admin', 'Администратор', 1);

-- 2. Insert Location
INSERT IGNORE INTO `locations` (`id`, `name`) VALUES 
(1, 'Обект Център');

-- 3. Insert Workers
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `role`, `full_name`, `is_underage`, `auth_code`, `is_active`) VALUES 
(2, 'worker1@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Иван Иванов', 0, NULL, 1),
(3, 'worker2@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Георги Георгиев', 0, NULL, 1),
(4, 'worker3@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Мария Петрова', 0, NULL, 1),
(5, 'worker4@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Елена Стоянова', 0, NULL, 1),
(6, 'underage@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Петър Непълнолетен', 1, NULL, 1);

-- 4. Assign Workers to Location
INSERT IGNORE INTO `user_locations` (`user_id`, `location_id`) VALUES 
(2, 1), (3, 1), (4, 1), (5, 1), (6, 1);

-- 5. Add Demo Absence for Worker 1
INSERT IGNORE INTO `absences` (`user_id`, `date`, `reason`) VALUES 
(2, CURDATE() + INTERVAL 2 DAY, 'Болничен');

-- 6. Insert Additional Locations
INSERT IGNORE INTO `locations` (`id`, `name`, `is_24_7`) VALUES 
(2, 'Обект Младост', 1),
(3, 'Обект Люлин', 0),
(4, 'Обект Пловдив Център', 1),
(5, 'Обект Варна Морска', 0);

-- 7. Insert Additional Workers
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `role`, `full_name`, `is_underage`, `auth_code`, `is_active`, `color_hex`) VALUES 
(7,  'worker7@grafik.local',  '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Димитър Колев',        0, NULL, 1, '#f43f5e'),
(8,  'worker8@grafik.local',  '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Николай Тодоров',      0, NULL, 1, '#0ea5e9'),
(9,  'worker9@grafik.local',  '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Стефан Димитров',      1, NULL, 1, '#10b981'),
(10, 'worker10@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Красимира Василева',   0, NULL, 1, '#f59e0b'),
(11, 'worker11@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Десислава Йорданова',  0, NULL, 1, '#8b5cf6'),
(12, 'worker12@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Александър Христов',   0, NULL, 1, '#ec4899'),
(13, 'worker13@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Виктория Ангелова',    0, NULL, 1, '#06b6d4'),
(14, 'worker14@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Борис Найденов',       0, NULL, 1, '#84cc16'),
(15, 'worker15@grafik.local', '$2y$10$27FmU0nv6vnBA95BHF6xC.l5PN6Mo7oOZOA9CywKmJ8M13JzepwEy', 'worker', 'Анна Маринова',        0, NULL, 1, '#d946ef');

-- 8. Assign New Workers to New Locations
INSERT IGNORE INTO `user_locations` (`user_id`, `location_id`) VALUES 
(7, 2), (8, 2), (9, 2),
(10, 3), (11, 3),
(12, 4), (13, 4),
(14, 5), (15, 5);

-- 9. Add Demo Absences for New Workers
INSERT IGNORE INTO `absences` (`user_id`, `date`, `reason`) VALUES 
(10, CURDATE() + INTERVAL 3 DAY, 'Отпуск'),
(14, CURDATE() + INTERVAL 1 DAY, 'Болничен');
