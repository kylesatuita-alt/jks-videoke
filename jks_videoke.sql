-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2026 at 12:53 PM
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
-- Database: `jks_videoke`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `videoke_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_areas`
--

CREATE TABLE `delivery_areas` (
  `id` int(11) NOT NULL,
  `province` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `sitio` varchar(100) NOT NULL,
  `delivery_fee` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `delivery_areas`
--

INSERT INTO `delivery_areas` (`id`, `province`, `barangay`, `sitio`, `delivery_fee`) VALUES
(1, 'Carcar', 'Calidngan', 'Proper', 1800.00),
(2, 'Carcar', 'Calidngan', 'Virsaros', 1800.00),
(3, 'Carcar', 'Calidngan', 'Hinagdanan', 1800.00),
(4, 'Carcar', 'Calidngan', 'Lumbang', 1800.00),
(5, 'Carcar', 'Calidngan', 'Pinanuyakan', 1800.00),
(7, 'Carcar', 'Calidngan', 'Baño', 1900.00),
(8, 'Carcar', 'Calidngan', 'Lunas', 1900.00),
(9, 'Carcar', 'Calidngan', 'Latab', 1900.00),
(10, 'Carcar', 'Calidngan', 'Sacsac', 2000.00),
(11, 'Carcar', 'Calidngan', 'Other', 1800.00),
(12, 'Carcar', 'Buenavista', 'Proper', 2000.00),
(13, 'Carcar', 'Buenavista', 'Lupa', 2000.00),
(14, 'Carcar', 'Buenavista', 'Kalubihan', 2000.00),
(15, 'Carcar', 'Buenavista', 'Sambagan', 2000.00),
(16, 'Carcar', 'Buenavista', 'Cabiawon', 2100.00),
(17, 'Carcar', 'Buenavista', 'Cara-atan', 2100.00),
(18, 'Carcar', 'Buenavista', 'Other', 2000.00),
(19, 'Carcar', 'Valencia', 'Proper', 2200.00),
(20, 'Carcar', 'Valencia', 'Abuno', 2200.00),
(21, 'Carcar', 'Valencia', 'Tagaytay', 2200.00),
(22, 'Carcar', 'Valencia', 'Tal-ut', 2200.00),
(23, 'Carcar', 'Valencia', 'Danao 1', 2300.00),
(24, 'Carcar', 'Valencia', 'Danao 2', 2300.00),
(25, 'Carcar', 'Valencia', 'Lower Tal-ut', 2300.00),
(26, 'Carcar', 'Valencia', 'Tina-an', 2400.00),
(27, 'Carcar', 'Valencia', 'Other', 2200.00);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `videoke_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender` enum('user','admin') NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `user_id`, `sender`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'user', 'I want to ask about my reservation status.', 1, '2026-06-27 07:53:54'),
(2, 1, 'admin', 'Hi! Thank you for contacting us. We\'re happy to let you know that your reservation is confirmed. Your videoke unit will be delivered on your scheduled reservation date. We appreciate your trust and look forward to serving you!', 1, '2026-06-27 07:58:06');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `res_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'status_update',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `res_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 3, 'status_update', '???? Your Unit is On the Way!', 'Unit 02 (Neon Barkada) is now being delivered to you. Please be ready to receive it. See you soon!', 1, '2026-06-26 00:10:49'),
(2, 1, 3, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 02 (Neon Barkada) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-26 00:12:25'),
(3, 3, 4, 'status_update', '???? Your Unit is On the Way!', 'Unit 01 (Party King Pro) is now being delivered to you. Please be ready to receive it. See you soon!', 1, '2026-06-26 00:13:53'),
(4, 3, 4, 'status_update', '???? Rental is Now Active', 'Your rental for Unit 01 (Party King Pro) is now active. Enjoy singing! ????', 1, '2026-06-26 00:14:21'),
(5, 4, 5, 'status_update', '✅ Reservation Confirmed!', 'Great news! Your reservation for Unit 02 (Neon Barkada) has been confirmed. We\'ll prepare everything for your rental dates (2026-06-27 – 2026-06-30).', 1, '2026-06-26 00:17:54'),
(6, 4, 5, 'status_update', '???? Your Unit is On the Way!', 'Unit 02 (Neon Barkada) is now being delivered to you. Please be ready to receive it. See you soon!', 1, '2026-06-26 00:18:34'),
(7, 4, 5, 'status_update', '???? Rental is Now Active', 'Your rental for Unit 02 (Neon Barkada) is now active. Enjoy singing! ????', 1, '2026-06-26 00:19:00'),
(8, 4, 5, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 02 (Neon Barkada) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-26 00:19:45'),
(9, 3, 4, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-26 00:20:12'),
(10, 1, 6, 'status_update', '✅ Reservation Confirmed!', 'Great news! Your reservation for Unit 01 (Party King Pro) has been confirmed. We\'ll prepare everything for your rental dates (2026-06-26 – 2026-06-29).', 1, '2026-06-26 00:21:17'),
(11, 1, 6, 'status_update', '???? Your Unit is On the Way!', 'Unit 01 (Party King Pro) is now being delivered to you. Please be ready to receive it. See you soon!', 1, '2026-06-26 00:21:22'),
(12, 1, 6, 'status_update', '???? Rental is Now Active', 'Your rental for Unit 01 (Party King Pro) is now active. Enjoy singing! ????', 1, '2026-06-26 00:21:49'),
(13, 3, 7, 'status_update', '❌ Reservation Cancelled', 'Your reservation for Unit 02 (Neon Barkada) has been cancelled. If you have questions, please contact us.', 1, '2026-06-26 14:32:07'),
(14, 1, 6, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-26 14:32:34'),
(15, 1, 6, 'support', '???? New message from JKS Support', 'Hi! Thank you for contacting us. We\'re happy to let you know that your reservation is confirmed. Your videoke unit will be delivered on your scheduled reservation date. We appreciate your trust and look forward to serving you!', 1, '2026-06-27 07:58:06'),
(16, 1, 8, 'status_update', '✅ Reservation Confirmed!', 'Great news! Your reservation for Unit 01 (Party King Pro) has been confirmed. We\'ll prepare everything for your rental dates (2026-07-01 – 2026-07-04).', 1, '2026-06-27 08:00:37'),
(17, 3, 9, 'status_update', '✅ Reservation Confirmed!', 'Great news! Your reservation for Unit 01 (Party King Pro) has been confirmed. We\'ll prepare everything for your rental dates (2026-07-05 – 2026-07-08).', 1, '2026-06-27 08:00:40'),
(18, 3, 10, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 0, '2026-06-27 08:21:11'),
(19, 3, 9, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 0, '2026-06-27 08:21:14'),
(20, 1, 8, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-27 08:21:17'),
(21, 4, 13, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 0, '2026-06-27 15:15:04'),
(22, 3, 12, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 0, '2026-06-27 15:15:07'),
(23, 1, 11, 'status_update', '???? Unit Returned — Thank You!', 'Your rental for Unit 01 (Party King Pro) has been marked as returned. Thank you for choosing JKS Videoke! ????', 1, '2026-06-27 15:15:10');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `videoke_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','confirmed','active','delivering','returned','cancelled') NOT NULL DEFAULT 'pending',
  `total_price` decimal(8,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `videoke_id`, `start_date`, `end_date`, `status`, `total_price`, `notes`, `created_at`) VALUES
(2, 1, 2, '2026-06-25', '2026-06-28', 'returned', 2400.00, 'Phone: 09560117773 | Barangay: Valencia | Sitio: Tina-an', '2026-06-25 09:52:37'),
(3, 1, 2, '2026-06-28', '2026-07-01', 'returned', 1800.00, 'Phone: 09560117771 | Barangay: Calidngan | Sitio: Virsaros | Notes: silingan ni bari', '2026-06-26 00:00:24'),
(4, 3, 1, '2026-06-26', '2026-06-29', 'returned', 2400.00, 'Phone: 09560117771 | Barangay: Valencia | Sitio: Tina-an | Notes: basketan', '2026-06-26 00:13:16'),
(5, 4, 2, '2026-06-27', '2026-06-30', 'returned', 2000.00, 'Phone: 09560117773 | Barangay: Buenavista | Sitio: Proper | Notes: atbang basketan', '2026-06-26 00:17:21'),
(6, 1, 1, '2026-06-26', '2026-06-29', 'returned', 2000.00, 'Phone: 09560117773 | Barangay: Buenavista | Sitio: Kalubihan', '2026-06-26 00:21:07'),
(7, 3, 2, '2026-06-28', '2026-07-01', 'cancelled', 1900.00, 'Phone: 09560117771 | Barangay: Calidngan | Sitio: Baño', '2026-06-26 00:23:06'),
(8, 1, 1, '2026-07-01', '2026-07-04', 'returned', 2000.00, 'Phone: 09560117773 | Barangay: Buenavista | Sitio: Proper', '2026-06-27 07:58:45'),
(9, 3, 1, '2026-07-05', '2026-07-08', 'returned', 2100.00, 'Phone: 09560117771 | Barangay: Buenavista | Sitio: Cabiawon', '2026-06-27 08:00:22'),
(10, 3, 1, '2026-07-09', '2026-07-12', 'returned', 2100.00, 'Phone: 09560117773 | Barangay: Buenavista | Sitio: Cabiawon', '2026-06-27 08:20:26'),
(11, 1, 1, '2026-07-01', '2026-07-04', 'returned', 1800.00, 'Phone: 09560117771 | Barangay: Calidngan | Sitio: Proper', '2026-06-27 08:21:40'),
(12, 3, 1, '2026-07-09', '2026-07-12', 'returned', 2100.00, 'Phone: 09560117773 | Barangay: Buenavista | Sitio: Cabiawon', '2026-06-27 08:22:23'),
(13, 4, 1, '2026-07-16', '2026-07-17', 'returned', 1900.00, 'Phone: 09560117771 | Barangay: Calidngan | Sitio: Baño', '2026-06-27 15:14:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `location` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `location`, `avatar`, `password`, `reset_token`, `reset_token_expires`, `role`, `created_at`) VALUES
(1, 'Crisanto', 'crisanto@gmail.com', '09560117771', 'Proper, Calidngan Carcar City', 'uploads/avatars/avatar_1_1782732642.jpg', '$2y$10$akREZ.swypVvgcQkyxEhz.fARvYBPOWibarPkAMITKtV1UVEsz2Ie', 'a9d1c634c50eac052796e53c290ebcc0fe0d0adb7f116d27cc0b6a30cf333b41', '2026-06-24 05:01:17', 'user', '2026-06-23 12:46:05'),
(2, 'Admin', 'admin@jksvideoke.com', '09000000000', 'Main Office', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'admin', '2026-06-23 23:46:36'),
(3, 'Felisa', 'felisa@gmail.com', '09560117772', 'Proper, Calidngan, Carcar City', NULL, '$2y$10$UHxBsutZHvM4F2cuzNkNzOaD7udWx6lnwUhiTIqqTEPdA4jIykYqu', 'f43fcb41e0286636a0f7262a870913f3041014fe3621ee6adcb8ecec4ae0fa31', '2026-06-24 05:05:42', 'user', '2026-06-24 02:05:36'),
(4, 'Kathleen', 'kathleen@gmail.com', '09560117773', 'Proper, Calidngan, Carcar City', NULL, '$2y$10$flE1D9VAn.nviQ4gcrr.6ukblvntMVOajzVF0zAU8PDYOlV.h9Zui', NULL, NULL, 'user', '2026-06-24 10:54:41');

-- --------------------------------------------------------

--
-- Table structure for table `videokes`
--

CREATE TABLE `videokes` (
  `id` int(11) NOT NULL,
  `unit_number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `brand` varchar(80) NOT NULL,
  `model` varchar(80) NOT NULL,
  `screen_size` varchar(20) NOT NULL,
  `song_count` int(11) NOT NULL DEFAULT 0,
  `mic_count` int(11) NOT NULL DEFAULT 1,
  `has_bluetooth` tinyint(1) NOT NULL DEFAULT 0,
  `has_recording` tinyint(1) NOT NULL DEFAULT 0,
  `price_3days` decimal(8,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `videokes`
--

INSERT INTO `videokes` (`id`, `unit_number`, `name`, `brand`, `model`, `screen_size`, `song_count`, `mic_count`, `has_bluetooth`, `has_recording`, `price_3days`, `description`, `image_url`, `created_at`) VALUES
(1, 1, 'Party King Pro', 'Magic Sing', 'ET-25KH', '21\"', 28000, 2, 1, 1, 1500.00, 'Our flagship unit. Huge song library, crystal-clear display, and built-in recording.', NULL, '2026-06-23 23:46:36'),
(2, 2, 'Neon Barkada', 'Magic Sing', 'KTV-900', '19\"', 22000, 2, 1, 1, 1200.00, 'Perfect for group sessions. Dual mics and vibrant screen for lively parties.', NULL, '2026-06-23 23:46:36'),
(3, 3, 'Pocket Star', 'Videoke PH', 'VS-Mini', '15\"', 15000, 1, 1, 0, 900.00, 'Compact and portable. Great for small gatherings or bedroom use.', NULL, '2026-06-23 23:46:36'),
(4, 4, 'Bass Boomer Deluxe', 'SoundPro', 'SP-4000K', '22\"', 25000, 2, 1, 1, 1400.00, 'Extra-loud speakers with deep bass. Built-in Bluetooth for streaming.', NULL, '2026-06-23 23:46:36'),
(5, 5, 'Classic Gold', 'Karaoke PH', 'KPH-77', '17\"', 18000, 1, 0, 0, 950.00, 'Reliable classic unit. Great sound, simple controls, easy for all ages.', NULL, '2026-06-23 23:46:36'),
(6, 6, 'Fiesta Grande', 'Magic Sing', 'KTV-1200', '24\"', 30000, 2, 1, 1, 1800.00, 'Biggest screen in the fleet. Made for outdoor events and large gatherings.', NULL, '2026-06-23 23:46:36'),
(7, 7, 'Studio Ace', 'SoundPro', 'SP-7000R', '20\"', 26000, 2, 1, 1, 1600.00, 'Studio-grade reverb and echo effects. Record your performance in high quality.', NULL, '2026-06-23 23:46:36'),
(8, 8, 'Chill & Sing', 'Videoke PH', 'VS-300', '16\"', 16000, 1, 1, 0, 850.00, 'Lightweight unit with Bluetooth speaker pairing. Cozy home sessions.', NULL, '2026-06-23 23:46:36'),
(9, 9, 'Ultimate Wireless', 'KTV Master', 'KM-X2', '21\"', 24000, 2, 1, 0, 1350.00, 'Fully wireless mics with 10-meter range. No tangled cables ever.', NULL, '2026-06-23 23:46:36'),
(10, 10, 'Mega Party Beast', 'KTV Master', 'KM-PRO5', '26\"', 32000, 2, 1, 1, 2000.00, 'Top-of-the-line. Largest screen, most songs, loudest sound. Built for fiestas.', NULL, '2026-06-23 23:46:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`videoke_id`),
  ADD KEY `videoke_id` (`videoke_id`);

--
-- Indexes for table `delivery_areas`
--
ALTER TABLE `delivery_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`videoke_id`),
  ADD KEY `videoke_id` (`videoke_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `res_id` (`res_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `videoke_id` (`videoke_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `videokes`
--
ALTER TABLE `videokes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_number` (`unit_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `delivery_areas`
--
ALTER TABLE `delivery_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `videokes`
--
ALTER TABLE `videokes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`videoke_id`) REFERENCES `videokes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`videoke_id`) REFERENCES `videokes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `msg_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notif_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notif_ibfk_2` FOREIGN KEY (`res_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`videoke_id`) REFERENCES `videokes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
