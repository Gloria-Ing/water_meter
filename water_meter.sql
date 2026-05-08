-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 09:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `water_meter`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_all_monthly_summaries` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id_val INT;
    DECLARE cur CURSOR FOR SELECT id FROM users WHERE role = 'client';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO user_id_val;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        CALL update_monthly_summary(user_id_val);
    END LOOP;
    
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_user_statistics` (IN `p_user_id` INT)   BEGIN
    SELECT 
        (SELECT SUM(liters) FROM usage_data WHERE user_id = p_user_id) as total_usage,
        (SELECT SUM(bill) FROM usage_data WHERE user_id = p_user_id) as total_bill,
        (SELECT SUM(amount) FROM payments WHERE user_id = p_user_id AND status = 'completed') as total_paid,
        (SELECT balance FROM users WHERE id = p_user_id) as current_balance,
        (SELECT COUNT(*) FROM usage_data WHERE user_id = p_user_id AND DATE(date) = CURDATE()) as today_readings,
        (SELECT SUM(liters) FROM usage_data WHERE user_id = p_user_id AND DATE(date) = CURDATE()) as today_usage;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `send_balance_alerts` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id_val INT;
    DECLARE user_name_val VARCHAR(100);
    DECLARE user_phone_val VARCHAR(20);
    DECLARE balance_val DECIMAL(10,2);
    DECLARE cur CURSOR FOR SELECT id, name, phone, balance FROM users WHERE role = 'client' AND balance > 10000;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO user_id_val, user_name_val, user_phone_val, balance_val;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO alerts (user_id, alert_type, message) 
        VALUES (user_id_val, 'high_balance', CONCAT('Your balance is ', balance_val, ' RWF. Please make payment.'));
        
        INSERT INTO sms_queue (user_id, phone, message, status) 
        VALUES (user_id_val, user_phone_val, CONCAT('WATER BILL ALERT\nDear ', user_name_val, ',\nYour balance is ', balance_val, ' RWF.\nPlease make payment.'), 'pending');
    END LOOP;
    
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_daily_summary` (IN `p_user_id` INT)   BEGIN
    INSERT INTO daily_summary (user_id, date, liters, bill)
    SELECT 
        p_user_id,
        CURDATE(),
        COALESCE(SUM(liters), 0),
        COALESCE(SUM(bill), 0)
    FROM usage_data
    WHERE user_id = p_user_id AND DATE(date) = CURDATE()
    ON DUPLICATE KEY UPDATE
        liters = VALUES(liters),
        bill = VALUES(bill);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_monthly_summary` (IN `p_user_id` INT)   BEGIN
    INSERT INTO monthly_summary (user_id, month_year, total_liters, total_bill)
    SELECT 
        p_user_id,
        DATE_FORMAT(CURDATE(), '%Y-%m'),
        COALESCE(SUM(liters), 0),
        COALESCE(SUM(bill), 0)
    FROM usage_data
    WHERE user_id = p_user_id 
      AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ON DUPLICATE KEY UPDATE
        total_liters = VALUES(total_liters),
        total_bill = VALUES(total_bill),
        updated_at = NOW();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `alert_type` enum('high_flow','leak','high_balance','payment_due','device_offline') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `current_month_usage`
-- (See below for the actual view)
--
CREATE TABLE `current_month_usage` (
`user_id` int(11)
,`name` varchar(100)
,`phone` varchar(20)
,`email` varchar(100)
,`total_liters` decimal(32,2)
,`total_bill` decimal(32,2)
,`current_balance` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `daily_summary`
--

CREATE TABLE `daily_summary` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `liters` decimal(10,2) DEFAULT 0.00,
  `bill` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `firmware_version` varchar(20) DEFAULT '3.0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `user_id`, `device_id`, `device_name`, `last_seen`, `status`, `firmware_version`, `created_at`) VALUES
(8, 6, 'WATER_METER_006', 'Patrick KWIZERA\'s Water Meter', '2026-05-04 17:16:43', 'active', '3.0', '2026-05-04 16:21:18');

-- --------------------------------------------------------

--
-- Table structure for table `device_heartbeats`
--

CREATE TABLE `device_heartbeats` (
  `id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_liters` decimal(10,2) DEFAULT 0.00,
  `daily_liters` decimal(10,2) DEFAULT 0.00,
  `flow_rate` decimal(10,2) DEFAULT 0.00,
  `rssi` int(11) DEFAULT 0,
  `free_heap` int(11) DEFAULT 0,
  `uptime` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `device_status_summary`
-- (See below for the actual view)
--
CREATE TABLE `device_status_summary` (
`device_id` varchar(50)
,`device_name` varchar(100)
,`user_name` varchar(100)
,`status` enum('active','inactive')
,`last_seen` timestamp
,`connection_status` varchar(15)
,`minutes_offline` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_summary`
--

CREATE TABLE `monthly_summary` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `total_liters` decimal(10,2) DEFAULT 0.00,
  `total_bill` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `amount`, `reference`, `status`, `payment_method`, `transaction_id`, `notes`, `date`) VALUES
(5, 6, 377.00, 'PAY_20260504_046A74', 'completed', '', NULL, '', '2026-05-04 17:11:28');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `log_payment` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    INSERT INTO system_logs (log_type, message, user_id) 
    VALUES ('payment', CONCAT('Payment created: ', NEW.reference, ' - Amount: ', NEW.amount), NEW.user_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_balance_on_payment` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE users SET balance = balance - NEW.amount WHERE id = NEW.user_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `payment_summary`
-- (See below for the actual view)
--
CREATE TABLE `payment_summary` (
`user_id` int(11)
,`name` varchar(100)
,`total_paid` decimal(32,2)
,`payment_count` bigint(21)
,`last_payment_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_queue`
--

INSERT INTO `sms_queue` (`id`, `user_id`, `phone`, `message`, `status`, `sent_at`, `retry_count`, `created_at`) VALUES
(3, 6, '0780002346', 'PAYMENT RECEIVED\nDear Patrick KWIZERA,\nWe have received your payment of 377.00 RWF.\nReference: PAY_20260504_046A74\nThank you for your payment!', 'pending', NULL, 0, '2026-05-04 17:11:28');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `log_type`, `message`, `user_id`, `ip_address`, `created_at`) VALUES
(1, 'login', 'User logged in successfully', 1, '192.168.1.100', '2026-05-04 14:07:43'),
(2, 'data_sync', 'ESP32 data synced successfully', 2, '192.168.1.50', '2026-05-04 14:07:43'),
(3, 'payment', 'Payment processed successfully', 2, '192.168.1.100', '2026-05-03 14:07:43'),
(4, 'user_registration', 'New user registered: john@gmail.com', 4, NULL, '2026-05-04 14:26:09'),
(5, 'user_creation', 'Admin created user:  with role: client', 4, NULL, '2026-05-04 14:26:09'),
(6, 'user_registration', 'New user registered: admin@gmail.com', 5, NULL, '2026-05-04 16:12:44'),
(7, 'user_creation', 'Admin created user:  with role: client and device: WATER_METER_004', 5, NULL, '2026-05-04 16:12:44'),
(8, 'user_registration', 'New user registered: amotechelectronics1@gmail.com', 6, NULL, '2026-05-04 16:21:18'),
(9, 'user_creation', 'Admin created user:  with role: client and device: WATER_METER_006', 6, NULL, '2026-05-04 16:21:18'),
(10, 'payment', 'Payment created: PAY_20260504_046A74 - Amount: 377.00', 6, NULL, '2026-05-04 17:11:28');

-- --------------------------------------------------------

--
-- Table structure for table `usage_data`
--

CREATE TABLE `usage_data` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liters` decimal(10,2) DEFAULT 0.00,
  `bill` decimal(10,2) DEFAULT 0.00,
  `date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usage_data`
--

INSERT INTO `usage_data` (`id`, `user_id`, `liters`, `bill`, `date`) VALUES
(9, 6, 100.00, 10.00, '2026-05-04 16:31:43'),
(10, 6, 150.00, 15.00, '2026-05-04 16:31:43'),
(11, 6, 100.00, 10.00, '2026-05-04 16:33:38'),
(12, 6, 150.00, 15.00, '2026-05-04 16:33:38'),
(13, 6, 5.00, 0.50, '2026-05-04 16:55:22'),
(14, 6, 0.13, 0.01, '2026-05-04 16:55:52'),
(15, 6, 410.27, 41.03, '2026-05-04 16:56:22'),
(16, 6, 752.93, 75.29, '2026-05-04 16:56:52'),
(17, 6, 373.20, 37.32, '2026-05-04 16:57:22'),
(18, 6, 188.93, 18.89, '2026-05-04 16:57:52'),
(19, 6, 255.07, 25.51, '2026-05-04 16:58:22'),
(20, 6, 835.20, 83.52, '2026-05-04 16:58:52'),
(21, 6, 446.93, 44.69, '2026-05-04 16:59:22'),
(22, 6, 5.00, 0.50, '2026-05-04 17:14:45'),
(23, 6, 236.13, 23.61, '2026-05-04 17:15:42'),
(24, 6, 1228.80, 122.88, '2026-05-04 17:16:12'),
(25, 6, 223.60, 22.36, '2026-05-04 17:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','client') DEFAULT 'client',
  `tariff` decimal(10,2) DEFAULT 100.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `phone`, `email`, `password`, `role`, `tariff`, `balance`, `reset_token`, `reset_expires`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', '0788000001', 'admin@watermeter.com', '$2y$10$chAsfU0v9uA3MxdmlZyBgO/ak1Kp4n5nvIvzROk6HYUdUxUdRMhOu', 'admin', 100.00, 0.00, NULL, NULL, '2026-05-04 14:07:42', '2026-05-04 14:12:44'),
(6, 'Patrick KWIZERA', '0780002346', 'amotechelectronics1@gmail.com', '$2y$10$ndqijwIWLcbcYe9IpVnt2u01zHxiiDp1P46rMBy4ui/iAfFY8/eYu', 'client', 100.00, 169.11, NULL, NULL, '2026-05-04 16:21:18', '2026-05-04 17:16:43');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `log_new_user` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO system_logs (log_type, message, user_id) 
    VALUES ('user_registration', CONCAT('New user registered: ', NEW.email), NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `current_month_usage`
--
DROP TABLE IF EXISTS `current_month_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `current_month_usage`  AS SELECT `u`.`id` AS `user_id`, `u`.`name` AS `name`, `u`.`phone` AS `phone`, `u`.`email` AS `email`, coalesce(sum(`ud`.`liters`),0) AS `total_liters`, coalesce(sum(`ud`.`bill`),0) AS `total_bill`, `u`.`balance` AS `current_balance` FROM (`users` `u` left join `usage_data` `ud` on(`u`.`id` = `ud`.`user_id` and date_format(`ud`.`date`,'%Y-%m') = date_format(curdate(),'%Y-%m'))) WHERE `u`.`role` = 'client' GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `device_status_summary`
--
DROP TABLE IF EXISTS `device_status_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `device_status_summary`  AS SELECT `d`.`device_id` AS `device_id`, `d`.`device_name` AS `device_name`, `u`.`name` AS `user_name`, `d`.`status` AS `status`, `d`.`last_seen` AS `last_seen`, CASE WHEN `d`.`last_seen` is null THEN 'Never Connected' WHEN timestampdiff(MINUTE,`d`.`last_seen`,current_timestamp()) > 5 THEN 'Offline' ELSE 'Online' END AS `connection_status`, timestampdiff(MINUTE,`d`.`last_seen`,current_timestamp()) AS `minutes_offline` FROM (`devices` `d` join `users` `u` on(`d`.`user_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `payment_summary`
--
DROP TABLE IF EXISTS `payment_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `payment_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`name` AS `name`, coalesce(sum(`p`.`amount`),0) AS `total_paid`, count(`p`.`id`) AS `payment_count`, max(`p`.`date`) AS `last_payment_date` FROM (`users` `u` left join `payments` `p` on(`u`.`id` = `p`.`user_id` and `p`.`status` = 'completed')) WHERE `u`.`role` = 'client' GROUP BY `u`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_type` (`alert_type`);

--
-- Indexes for table `daily_summary`
--
ALTER TABLE `daily_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_device_id` (`device_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `device_heartbeats`
--
ALTER TABLE `device_heartbeats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device` (`device_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month` (`user_id`,`month_year`),
  ADD KEY `idx_month_year` (`month_year`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_reference` (`reference`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`log_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `usage_data`
--
ALTER TABLE `usage_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `daily_summary`
--
ALTER TABLE `daily_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `device_heartbeats`
--
ALTER TABLE `device_heartbeats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `usage_data`
--
ALTER TABLE `usage_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_summary`
--
ALTER TABLE `daily_summary`
  ADD CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `device_heartbeats`
--
ALTER TABLE `device_heartbeats`
  ADD CONSTRAINT `device_heartbeats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  ADD CONSTRAINT `monthly_summary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD CONSTRAINT `sms_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usage_data`
--
ALTER TABLE `usage_data`
  ADD CONSTRAINT `usage_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `auto_update_monthly_summary` ON SCHEDULE EVERY 1 MONTH STARTS '2026-06-01 01:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    CALL generate_all_monthly_summaries();
END$$

CREATE DEFINER=`root`@`localhost` EVENT `daily_balance_check` ON SCHEDULE EVERY 1 DAY STARTS '2026-05-04 08:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    CALL send_balance_alerts();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
