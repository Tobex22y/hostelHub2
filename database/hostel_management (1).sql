-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2026 at 05:05 PM
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
-- Database: `hostel_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `email`, `password`, `created_at`) VALUES
(2, 'System Admin', 'admin@hostel.com', '$2y$10$hAYUOFNs3fA2tsiLkXCDreg9wq6Oo/brv5rfv49fB4/M7.KfOQoAC', '2026-06-19 21:21:06');

-- --------------------------------------------------------

--
-- Table structure for table `allocations`
--

CREATE TABLE `allocations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','paid','active','rejected') DEFAULT 'pending',
  `reserved_until` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `allocations`
--

INSERT INTO `allocations` (`id`, `student_id`, `room_id`, `bed_id`, `status`, `reserved_until`, `payment_reference`, `created_at`) VALUES
(48, 23, 9, 9, 'paid', '2026-08-13 18:06:14', 'HH-48-1786640655663', '2026-08-13 17:04:14'),
(60, 22, 10, 14, 'paid', '2026-08-19 10:54:52', 'HH-60-1787133176223', '2026-08-19 09:52:52'),
(64, 21, 9, 8, 'paid', '2026-08-19 15:52:44', 'HH-64-1787151045928', '2026-08-19 14:50:44');

-- --------------------------------------------------------

--
-- Table structure for table `bedspaces`
--

CREATE TABLE `bedspaces` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_number` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `is_occupied` tinyint(1) DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `reserved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bedspaces`
--

INSERT INTO `bedspaces` (`id`, `room_id`, `bed_number`, `student_id`, `is_occupied`, `status`, `reserved_at`) VALUES
(1, 1, 1, NULL, 0, 'available', NULL),
(2, 1, 2, NULL, 0, 'available', NULL),
(3, 1, 3, NULL, 0, 'available', NULL),
(4, 1, 4, NULL, 0, 'available', NULL),
(5, 1, 5, NULL, 0, 'available', NULL),
(6, 1, 6, NULL, 0, 'available', NULL),
(7, 9, 1, NULL, 0, 'available', NULL),
(8, 9, 2, NULL, 1, 'occupied', NULL),
(9, 9, 3, NULL, 1, 'occupied', NULL),
(10, 9, 4, NULL, 0, 'available', NULL),
(11, 9, 5, NULL, 0, 'available', NULL),
(12, 9, 6, NULL, 0, 'available', NULL),
(13, 10, 1, NULL, 0, 'available', NULL),
(14, 10, 2, NULL, 1, 'occupied', NULL),
(15, 10, 3, NULL, 0, 'available', NULL),
(16, 10, 4, NULL, 0, 'available', NULL),
(17, 10, 5, NULL, 0, 'available', NULL),
(18, 10, 6, NULL, 0, 'available', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tickets`
--

CREATE TABLE `maintenance_tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `category` enum('electrical','plumbing','furniture','cleaning','internet','security','other') NOT NULL DEFAULT 'other',
  `title` varchar(120) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(120) NOT NULL COMMENT 'e.g. Hall C2 Room 4 Bed 2',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `admin_note` text DEFAULT NULL COMMENT 'Response/update from admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_tickets`
--

INSERT INTO `maintenance_tickets` (`id`, `student_id`, `category`, `title`, `description`, `location`, `priority`, `status`, `admin_note`, `created_at`, `updated_at`) VALUES
(1, 21, 'furniture', 'faulty door', 'the door is broken and can not close wel', 'Hall C2, Bed Bed 3', 'urgent', 'resolved', 'fixed', '2026-07-17 13:48:59', '2026-08-09 11:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `allocation_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `allocation_id`, `amount`, `status`, `reference`, `created_at`) VALUES
(1, NULL, 1, 50000.00, 'success', 'HOSTEL_1782119398136_615484', '2026-06-22 09:10:10'),
(2, NULL, 9, 50000.00, 'success', 'HH-9-1782133249736', '2026-06-22 13:00:56'),
(3, NULL, 10, 50000.00, 'success', 'HH-10-1782135743123', '2026-06-22 13:42:30'),
(4, NULL, 11, 50000.00, 'success', 'HH-11-1782137373199', '2026-06-22 14:09:43'),
(5, NULL, 12, 50000.00, 'success', 'HH-12-1782141178885', '2026-06-22 15:13:08'),
(6, NULL, 13, 50000.00, 'success', 'HH-13-1782141385521', '2026-06-22 15:16:35'),
(7, NULL, 14, 50000.00, 'success', 'HH-14-1782142578530', '2026-06-22 15:36:34'),
(8, NULL, 16, 50000.00, 'success', 'HH-16-1782201360534', '2026-06-23 07:56:09'),
(9, NULL, 18, 50000.00, 'success', 'HH-18-1782207799136', '2026-06-23 09:43:28'),
(10, NULL, 20, 50000.00, 'success', 'HH-20-1782228237588', '2026-06-23 15:24:06'),
(11, NULL, 23, 50000.00, 'success', 'HH-23-1782304423338', '2026-06-24 12:33:53'),
(12, NULL, 24, 50000.00, 'success', 'HH-24-1782313069954', '2026-06-24 14:58:04'),
(13, NULL, 29, 65000.00, 'success', 'HH-29-1782316617200', '2026-06-24 15:57:54'),
(14, NULL, 30, 65000.00, 'success', 'HH-30-1782318378264', '2026-06-24 16:26:27'),
(15, NULL, 31, 65000.00, 'success', 'HH-31-1782319688359', '2026-06-24 16:48:17'),
(16, NULL, 32, 65000.00, 'success', 'HH-32-1782320911966', '2026-06-24 17:08:39'),
(17, NULL, 33, 50000.00, 'success', 'HH-33-1782323418952', '2026-06-24 17:50:28'),
(18, NULL, 35, 65000.00, 'success', 'HH-35-1782328088080', '2026-06-24 19:08:47'),
(19, NULL, 36, 50000.00, 'success', 'HH-36-1782328397076', '2026-06-24 19:13:25'),
(20, NULL, 38, 65000.00, 'success', 'HH-38-1782331269375', '2026-06-24 20:01:19'),
(21, NULL, 39, 65000.00, 'success', 'HH-39-1782335035150', '2026-06-24 21:04:06'),
(22, NULL, 41, 65000.00, 'success', 'HH-41-1782489695186', '2026-06-26 16:01:45'),
(23, NULL, 42, 50000.00, 'success', 'HH-42-1782687431578', '2026-06-28 22:57:20'),
(24, NULL, 43, 65000.00, 'success', 'HH-43-1783291583009', '2026-07-05 22:46:35'),
(25, NULL, 44, 50000.00, 'success', 'HH-44-1786056583586', '2026-08-06 22:50:28'),
(26, NULL, 45, 65000.00, 'success', 'HH-45-1786057084661', '2026-08-06 22:58:13'),
(27, NULL, 48, 65000.00, 'success', 'HH-48-1786640655663', '2026-08-13 17:04:27'),
(28, NULL, 55, 50000.00, 'success', 'HH-55-1786985359809', '2026-08-17 16:49:28'),
(29, NULL, 57, 65000.00, 'success', 'HH-57-1787012567849', '2026-08-18 00:23:04'),
(30, NULL, 58, 65000.00, 'success', 'HH-58-1787054766082', '2026-08-18 12:06:17'),
(31, NULL, 59, 65000.00, 'success', 'HH-59-1787085932039', '2026-08-18 20:45:43'),
(32, NULL, 60, 50000.00, 'success', 'HH-60-1787133176223', '2026-08-19 09:53:16'),
(33, NULL, 61, 65000.00, 'success', 'HH-61-1787135593279', '2026-08-19 10:35:30'),
(34, NULL, 62, 65000.00, 'success', 'HH-62-1787136315759', '2026-08-19 10:45:28'),
(35, NULL, 64, 65000.00, 'success', 'HH-64-1787151045928', '2026-08-19 14:50:55');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `occupied` int(11) NOT NULL DEFAULT 0,
  `status` enum('available','full','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) DEFAULT 0.00,
  `room_type` varchar(50) DEFAULT 'standard',
  `hall` varchar(10) DEFAULT NULL,
  `gender` enum('male','female','unisex') NOT NULL DEFAULT 'unisex'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `capacity`, `occupied`, `status`, `created_at`, `price`, `room_type`, `hall`, `gender`) VALUES
(1, '1', 6, 0, 'available', '2026-06-22 06:24:18', 50000.00, '6duplex', 'C1', 'male'),
(9, '1', 6, 0, 'available', '2026-06-23 09:19:40', 65000.00, 'duplex', 'C2', 'male'),
(10, 'A- 1', 6, 0, 'available', '2026-06-28 22:56:39', 50000.00, 'Standard', 'A', 'female');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `matric_number` varchar(50) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `confirm_password` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `fullname`, `email`, `phone`, `gender`, `matric_number`, `password`, `confirm_password`, `profile_image`, `created_at`) VALUES
(20, 'Praise Ayomide Omoniyi', 'praiseomoniyi65@gmail.com', '08052671012', 'male', '21513', '$2y$10$ZwaCQFzObVcrwp1dtseUE.ILZxz/4sAAJACvvHzz.JQ43/cIQ.4/2', NULL, '1781898131_HLS%20-%20Photoset-3_edited.jpeg', '2026-06-19 19:42:12'),
(21, 'Osuntoki Tobiloba', 'osuntokisamuel176@gmail.com', '07063452662', 'male', 'CSC/2022/5151', '$2y$10$APbYWS98HggXFV6/Q4n/g.4DYNG92H3tXRVIpvFIc3sOIcOHYJA/2', NULL, '1782062289_img.jpg', '2026-06-21 17:18:09'),
(22, 'osuntoki toluwani', 'osuntokitobiloa@gmail.com', '09126400249', 'female', 'SLT/21/4184', '$2y$10$ZIaCnj7QO29xpEC5AzYuC.2L7V2y0TcKLL25xC6V1ktvSNkKWicZG', NULL, '1782569471_DSC_1110_050521.JPG', '2026-06-27 14:11:11'),
(23, 'Balogun Ayobami ', 'adebobolaademola549@gmail.com', '07063452662', 'male', '5008', '$2y$10$DX2T7QwjVeKHQPBvbtqrY.lCAeAGgIYY2xSRVVSUXaaOSD.rpEoOu', NULL, '1786640536_IMG-20251127-WA0025.jpg', '2026-08-13 17:02:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `allocations`
--
ALTER TABLE `allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student` (`student_id`);

--
-- Indexes for table `bedspaces`
--
ALTER TABLE `bedspaces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_per_hall` (`hall`,`room_number`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_matric_number` (`matric_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `allocations`
--
ALTER TABLE `allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `bedspaces`
--
ALTER TABLE `bedspaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bedspaces`
--
ALTER TABLE `bedspaces`
  ADD CONSTRAINT `bedspaces_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD CONSTRAINT `maintenance_tickets_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
