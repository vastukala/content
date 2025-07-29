-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2025 at 01:02 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `content_viewer`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_restricted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `content_id`, `user_id`, `comment`, `created_at`, `is_restricted`) VALUES
(8, 1, 4, 'good book with great and in depth explanations', '2025-07-14 05:46:23', 0),
(9, 2, 2, 'good content', '2025-07-16 07:11:28', 0),
(10, 2, 2, 'good video i liked it', '2025-07-17 06:53:29', 0),
(11, 1, 2, 'good book', '2025-07-17 06:55:04', 0);

-- --------------------------------------------------------

--
-- Table structure for table `content`
--

CREATE TABLE `content` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `file_type` enum('pdf','video','audio','ppt','doc','excel','image') NOT NULL,
  `video_type` enum('file','youtube') DEFAULT 'file',
  `file_path` varchar(512) NOT NULL,
  `useful_to` set('student','teacher','public','others') NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 1.00,
  `is_restricted` tinyint(1) NOT NULL DEFAULT 0,
  `view_restricted` tinyint(1) NOT NULL DEFAULT 0,
  `converted_file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `content`
--

INSERT INTO `content` (`id`, `title`, `description`, `file_type`, `video_type`, `file_path`, `useful_to`, `tags`, `upload_date`, `admin_id`, `price`, `is_restricted`, `view_restricted`, `converted_file_path`) VALUES
(1, 'Trignomentry', 'this book have content releated trignomentry', 'pdf', 'file', 'uploads/pdf/1751880087_Trigonometry_Short_Course_Tutorial_Lauren_Johnson.pdf', 'student', 'trignometnry, maths', '2025-07-07 09:21:27', 1, 1.00, 0, 0, NULL),
(2, 'galaxy trivia', 'this video tell you about early formation and clustering of galaxies which lead to creation of our own galaxy milky way', 'video', 'file', 'uploads/video/1751883195_1 minute preview of _Galaxies_ educational science video..mp4', 'student', 'space, galaxy, education', '2025-07-07 10:13:15', 1, 1.00, 0, 0, NULL),
(8, 'college list in pune', 'this is list of colleges in pune', 'doc', 'file', 'uploads/doc/College_List_Pune_by_admin_1752745761.docx', 'student', 'pune, college', '2025-07-17 09:49:21', 6, 1.00, 0, 0, NULL),
(9, 'college in pune', 'pune colleges', 'doc', 'file', 'uploads/doc/College_List_Pune_by_Bhushan_1752746847.docx', 'student', 'pune', '2025-07-17 10:07:28', 1, 1.00, 0, 0, NULL),
(12, 'milk entry report', 'report of milk entry', 'excel', 'file', 'uploads/excel/milk_entry_report (1)_by_admin_1753083763.xls', 'public', 'milk, entry', '2025-07-21 07:42:44', 6, 22.00, 0, 0, NULL),
(13, 'california dreaming', 'california dreaming is a famous pops song', 'audio', 'file', 'uploads/audio/california-dreamin_by_Bhushan_1753084800.mp3', 'public', 'california, song', '2025-07-21 08:00:00', 1, 100.00, 0, 0, NULL),
(15, 'data flow diagram', 'data flow diagram', 'image', 'file', 'uploads/image/1751883300_clean_data_flow_diagram_by_admin_1753088193.jpg', 'student', 'data flow, diagram', '2025-07-21 08:56:33', 6, 1.00, 0, 0, NULL),
(17, 'ppt', 'this is a ppt', 'ppt', 'file', 'uploads/ppt/stay-ppt_by_admin_1753096162.pptx', 'student', 'ppt', '2025-07-21 11:09:23', 6, 1.00, 0, 0, NULL),
(18, 'online book shop', 'this is presentation for online book shop', 'ppt', 'file', 'uploads/ppt/online book shop.ppt_by_Bhushan_1753167868.pptx', 'student', 'online, book, shop', '2025-07-22 07:04:28', 1, 20.00, 0, 0, NULL),
(19, 'Manage student', 'this doc file managing students', 'doc', 'file', 'uploads/doc/manage student_by_admin_1753168869.docx', 'teacher', 'manage, student', '2025-07-22 07:21:09', 6, 20.00, 0, 0, NULL),
(20, 'final report', 'this is final report', 'doc', 'file', 'uploads/doc/report_final_by_Bhushan_1753179137.docx', 'student', 'report, student', '2025-07-22 10:12:17', 1, 10.00, 0, 0, NULL),
(21, 'youtube video', 'this is youtube video', 'video', 'youtube', 'https://www.youtube.com/watch?v=8V5mnqOtCnY', 'student', 'youtube', '2025-07-25 10:59:47', 1, 20.00, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `file_requests`
--

CREATE TABLE `file_requests` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `approved_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `file_requests`
--

INSERT INTO `file_requests` (`id`, `content_id`, `user_id`, `request_date`, `status`, `payment_status`, `approved_date`) VALUES
(8, 2, 2, '2025-07-09 11:16:38', 'approved', 'pending', NULL),
(10, 1, 4, '2025-07-14 05:46:05', 'pending', 'pending', NULL),
(11, 1, 2, '2025-07-16 07:01:51', 'approved', 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Bhushan', '$2y$10$L5WU/TVR3Kb3ZDYlWnSgkeL/7ujjtuOv2Tx1e5w5ath6mCDp/ETLa', 'admin', '2025-07-07 09:20:18'),
(2, 'Pratiksha', '$2y$10$5xuqvd1GO3YvCMGTUOgNc./8tHHvsKxht4qIwzybHbmpUhnlzKHKK', 'user', '2025-07-07 09:34:47'),
(3, 'shiv', '$2y$10$xr9.vTwy.s31O60OrdzuO.QnDMFUs.F3TWbKJ.aN26D7TeBbnOPaS', 'admin', '2025-07-07 10:27:35'),
(4, 'Lata', '$2y$10$/CrXprc7eyjhw63MnQdwFuSH2tcKhDVgH57S1yuKQOXCIrHKcPpSa', 'user', '2025-07-07 10:28:06'),
(5, 'vastukala', '$2y$10$fx1stwayA0T32FKJBI/ipecuQBQDp/BFFzkd0wd.g3hUVLqcPhfga', 'admin', '2025-07-07 10:30:04'),
(6, 'admin', '$2y$10$e6xWbIKbFMG6hPiBbMxZ.enZU9h33OSkzpGIHaz75lX3HGdMxWkD2', 'admin', '2025-07-07 10:40:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `content_id` (`content_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `content`
--
ALTER TABLE `content`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `file_requests`
--
ALTER TABLE `file_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `content_id` (`content_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `content`
--
ALTER TABLE `content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `file_requests`
--
ALTER TABLE `file_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `content`
--
ALTER TABLE `content`
  ADD CONSTRAINT `content_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `file_requests`
--
ALTER TABLE `file_requests`
  ADD CONSTRAINT `file_requests_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
