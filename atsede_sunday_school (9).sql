-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2026 at 05:11 PM
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
-- Database: `atsede_sunday_school`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `ethiopian_year` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `promotion_done` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `ethiopian_year`, `start_date`, `end_date`, `status`, `promotion_done`, `created_at`) VALUES
(1, 2018, '2025-09-11', NULL, 'active', 0, '2026-05-08 22:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_assignments`
--

CREATE TABLE `attendance_assignments` (
  `id` int(11) NOT NULL,
  `submitter_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_assignments`
--

INSERT INTO `attendance_assignments` (`id`, `submitter_id`, `class_id`, `semester_id`, `assigned_at`) VALUES
(1, 11, 2, 1, '2026-05-08 22:39:46'),
(2, 10, 2, 1, '2026-05-08 22:39:54'),
(3, 12, 4, 1, '2026-05-08 22:42:04'),
(4, 13, 4, 1, '2026-05-08 22:42:11');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_days`
--

CREATE TABLE `attendance_days` (
  `id` int(11) NOT NULL,
  `date_gregorian` date NOT NULL,
  `ethiopian_year` int(11) NOT NULL,
  `ethiopian_month` int(11) NOT NULL,
  `ethiopian_day` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `is_school_day` tinyint(1) DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_days`
--

INSERT INTO `attendance_days` (`id`, `date_gregorian`, `ethiopian_year`, `ethiopian_month`, `ethiopian_day`, `day_of_week`, `class_id`, `is_school_day`, `reason`, `created_by`, `created_at`) VALUES
(1, '2026-06-07', 2018, 9, 30, 'Sunday', NULL, 0, NULL, 1, '2026-05-10 20:04:10'),
(2, '2026-04-12', 2018, 8, 4, 'Sunday', NULL, 0, NULL, 1, '2026-05-10 20:04:26'),
(4, '2026-08-22', 2018, 12, 16, 'Saturday', NULL, 1, NULL, 1, '2026-09-17 13:52:46');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','permission','late','excused') NOT NULL DEFAULT 'absent',
  `marked_by` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `local_uuid` varchar(36) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `student_id`, `class_id`, `teacher_id`, `attendance_date`, `status`, `marked_by`, `marked_at`, `last_updated`, `local_uuid`, `is_deleted`) VALUES
(1, 48, 2, 6, '2026-05-10', 'permission', 10, '2026-05-10 16:19:13', '2026-05-10 19:18:24', NULL, 0),
(4, 48, 2, 6, '2026-05-09', 'permission', 10, '2026-05-10 16:19:14', '2026-05-10 19:18:26', NULL, 0),
(34, 48, 2, 6, '2026-05-03', 'absent', 10, '2026-05-10 19:18:30', '2026-05-10 19:18:31', NULL, 0),
(40, 48, 2, 6, '2026-05-02', 'permission', 10, '2026-05-10 19:18:33', '2026-05-10 19:18:33', NULL, 0),
(43, 48, 2, 6, '2026-04-26', 'permission', 10, '2026-05-10 19:18:34', '2026-05-10 19:18:34', NULL, 0),
(46, 48, 2, 6, '2026-04-25', 'permission', 10, '2026-05-10 19:18:35', '2026-05-10 19:18:35', NULL, 0),
(49, 48, 2, 6, '2026-04-19', 'permission', 10, '2026-05-10 19:18:37', '2026-05-10 19:18:37', NULL, 0),
(52, 48, 2, 6, '2026-04-18', 'permission', 10, '2026-05-10 19:18:38', '2026-05-10 19:18:38', NULL, 0),
(55, 48, 2, 6, '2026-04-12', 'permission', 10, '2026-05-10 19:18:40', '2026-05-10 19:18:40', NULL, 0),
(58, 48, 2, 6, '2026-04-11', 'permission', 10, '2026-05-10 19:18:41', '2026-05-10 19:18:42', NULL, 0),
(61, 48, 2, 6, '2026-04-05', 'permission', 10, '2026-05-10 19:18:48', '2026-05-10 19:19:04', NULL, 0),
(64, 48, 2, 6, '2026-04-04', 'absent', 10, '2026-05-10 19:18:49', '2026-05-10 19:19:01', NULL, 0),
(67, 48, 2, 6, '2026-03-29', 'absent', 10, '2026-05-10 19:18:50', '2026-05-10 19:19:00', NULL, 0),
(70, 48, 2, 6, '2026-03-28', 'absent', 10, '2026-05-10 19:18:50', '2026-05-10 19:18:59', NULL, 0),
(73, 48, 2, 6, '2026-03-22', 'permission', 10, '2026-05-10 19:18:52', '2026-05-10 19:18:52', NULL, 0),
(76, 48, 2, 6, '2026-03-21', 'present', 10, '2026-05-10 19:18:53', '2026-05-10 19:18:58', NULL, 0),
(79, 48, 2, 6, '2026-03-15', 'present', 10, '2026-05-10 19:18:55', '2026-05-10 19:18:55', NULL, 0),
(82, 48, 2, 6, '2026-03-14', 'present', 10, '2026-05-10 19:18:56', '2026-05-10 19:18:57', NULL, 0),
(103, 94, 7, 15, '2026-09-13', 'excused', 15, '2026-09-13 10:19:19', '2026-09-16 19:44:01', 'f7edc83a-4bf5-47db-90e3-f03b35460325', 0),
(107, 94, 7, 15, '2026-08-22', 'present', 15, '2026-09-16 20:19:23', '2026-09-16 20:19:27', 'e76ff898-0371-4ba1-a359-76233c7d1b27', 0),
(122, 88, 7, 15, '2026-09-13', 'absent', 15, '2026-09-17 14:53:36', '2026-09-17 14:53:36', '4fa66b3e-d752-448c-a268-0ab7cd04153c', 0),
(125, 94, 7, 15, '2026-09-12', 'present', 15, '2026-09-17 21:20:00', '2026-09-17 21:20:00', '7687ecbd-ebb4-4c6b-a367-8061554341ab', 0);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-11 14:40:31'),
(2, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-11 14:40:35'),
(3, 15, 'ocr_lesson_plan_processed', 'lesson_plans', NULL, 'Provider: local_template_fallback', '10.180.203.4', '2026-09-11 17:07:28'),
(4, 15, 'ocr_lesson_plan_processed', 'lesson_plans', NULL, 'Provider: local_template_fallback', '10.180.203.4', '2026-09-11 17:08:13'),
(5, 15, 'ocr_lesson_plan_processed', 'lesson_plans', NULL, 'Provider: local_template_fallback', '10.180.203.4', '2026-09-11 17:10:35'),
(6, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-11 17:29:22'),
(7, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-11 17:29:47'),
(8, 1, 'calendar_event_created', 'calendar_events', 1, 'test exam', '::1', '2026-09-11 18:03:47'),
(9, 1, 'lesson_plan_reviewed', 'lesson_plans', 2, 'በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', '::1', '2026-09-11 19:14:20'),
(10, 1, 'lesson_plan_reviewed', 'lesson_plans', 2, 'በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', '::1', '2026-09-11 19:21:26'),
(11, 1, 'lesson_plan_approved', 'lesson_plans', 2, 'በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', '::1', '2026-09-11 19:23:00'),
(12, 1, 'exam_reminders_run', NULL, NULL, '0 reminder(s) sent', '::1', '2026-09-11 19:33:37'),
(13, 1, 'exam_reminders_run', NULL, NULL, '0 reminder(s) sent', '::1', '2026-09-11 19:33:39'),
(14, 2, 'lesson_plan_updated', 'lesson_plans', 3, NULL, '::1', '2026-09-12 09:03:24'),
(15, 1, 'lesson_plan_needs_correction', 'lesson_plans', 1, 'good', '::1', '2026-09-12 09:36:15'),
(16, 1, 'lesson_plan_approved', 'lesson_plans', 1, 'good', '::1', '2026-09-12 09:37:55'),
(17, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-12 12:18:48'),
(18, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-13 09:19:15'),
(19, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-13 09:20:10'),
(20, 1, 'lesson_plan_created', 'lesson_plans', 5, NULL, NULL, '2026-09-13 20:48:13'),
(21, 1, 'lesson_plan_created', 'lesson_plans', 6, NULL, NULL, '2026-09-13 20:48:38'),
(22, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 21:20:39'),
(23, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 21:29:23'),
(24, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-16 22:24:11'),
(25, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-16 22:24:13'),
(26, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-16 22:25:15'),
(27, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 22:31:00'),
(28, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 22:31:18'),
(29, 1, 'database_backup_created', NULL, NULL, 'backup_20260917_014604.sql', '::1', '2026-09-16 22:46:04'),
(30, 1, 'database_backup_downloaded', NULL, NULL, 'backup_20260917_014604.sql', '::1', '2026-09-16 22:46:08'),
(31, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 22:53:21'),
(32, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:17:14'),
(33, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-16 23:18:03'),
(34, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:21:03'),
(35, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:21:04'),
(36, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:30:51'),
(37, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:30:51'),
(38, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-16 23:32:59'),
(39, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-16 23:37:34'),
(40, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-17 00:07:04'),
(41, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-17 00:07:04'),
(42, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:07:29'),
(43, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:07:30'),
(44, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:07:30'),
(45, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '::1', '2026-09-17 00:13:04'),
(46, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:29:41'),
(47, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:44:38'),
(48, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 00:45:58'),
(49, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '::1', '2026-09-17 01:09:42'),
(50, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 09:57:55'),
(51, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 10:03:22'),
(52, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 12:26:11'),
(53, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 13:18:03'),
(54, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 13:18:03'),
(55, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 13:46:59'),
(56, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 13:47:55'),
(57, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 13:49:59'),
(58, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.49 Mobile Safari/537.36', '::1', '2026-09-17 14:01:37'),
(59, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 14:01:55'),
(60, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 14:01:55'),
(61, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 14:30:52'),
(62, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 15:01:56'),
(63, 1, 'calendar_event_deleted', 'calendar_events', 2, NULL, '::1', '2026-09-17 15:15:13'),
(64, 1, 'calendar_event_deleted', 'calendar_events', 1, NULL, '::1', '2026-09-17 15:15:21'),
(65, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 15:58:43'),
(66, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:00:27'),
(67, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:00:27'),
(68, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:01:05'),
(69, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:01:06'),
(70, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:14:08'),
(71, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 16:14:30'),
(72, 1, 'calendar_event_deleted', 'calendar_events', 1, NULL, '::1', '2026-09-17 19:55:38'),
(73, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 20:14:37'),
(74, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 20:14:37'),
(75, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 20:24:22'),
(76, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.49 Mobile Safari/537.36', '::1', '2026-09-17 20:25:49'),
(77, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 20:26:03'),
(78, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 20:28:02'),
(79, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:13:25'),
(80, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:19:57'),
(81, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:20:00'),
(82, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:20:00'),
(83, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:20:30'),
(84, 1, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 21:54:00'),
(85, 15, 'push_subscription_registered', 'push_subscriptions', NULL, 'Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '::1', '2026-09-17 22:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `role` enum('admin','teacher','attendance_submitter','student') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `user_id`, `token`, `role`, `created_at`, `expires_at`, `revoked`) VALUES
(1, 1, 'a4c74c204dab8ec427226618402287c411fd53469dff2892ae16d069b415c8cc', 'admin', '2026-09-09 20:54:04', '2026-10-09 19:54:04', 0),
(2, 1, '4600cc15d3274552270a2b3ed252da3408bbfce0d910d2525c9e62ae53caf7ab', 'admin', '2026-09-10 00:02:06', '2026-10-09 23:02:06', 0),
(3, 1, '2a3adf209fa62c0dec6fe431e23abbb09b264e6161a9f9440cb66448c5e704f6', 'admin', '2026-09-10 00:17:46', '2026-10-09 23:17:46', 0),
(4, 1, 'c543bb2872be9e311965b98ad6d76161dc0f71a3e97401da55b35d51906f1dee', 'admin', '2026-09-10 01:32:41', '2026-10-10 00:32:41', 0),
(5, 1, '54ce0328dc99e2503785d27d08b783a653d93e8dfc0b284d5f7c1b9e4ca125fb', 'admin', '2026-09-11 14:23:26', '2026-10-11 13:23:26', 0),
(6, 1, '68d2332bf89a507860a1b6f8bf71e0075a27001ee142c5e08bd3f0dbb79e7678', 'admin', '2026-09-11 14:50:26', '2026-10-11 13:50:25', 0),
(7, 15, 'bcb064051ef070e8e3b34cb078e4e60a79ca436710e4b8649e9556d895f6a1be', 'teacher', '2026-09-11 14:51:53', '2026-10-11 13:51:53', 0),
(8, 1, '1cd272afff91b76818144066705012adee5035643af9299edeb49bc2b75bdf1d', 'admin', '2026-09-11 14:52:38', '2026-10-11 13:52:38', 0),
(9, 15, '346a76bd0d2f7b361e2cb6050ddd8253af9ef6c7babce64b5768bb4efeffe46a', 'teacher', '2026-09-11 14:53:14', '2026-10-11 13:53:14', 0),
(10, 15, 'a8209cdc3f4071d13d3eecb53525bdb4a81ef5d47c1100a7834f304aaa8a1579', 'teacher', '2026-09-11 16:46:01', '2026-10-11 15:46:01', 0),
(11, 15, '715c4f439ec6c90237ef1a56b3a773baca40656f6e2751bf7799dbec7d091172', 'teacher', '2026-09-11 16:47:05', '2026-10-11 15:47:05', 0),
(12, 15, 'be6665c4f9da82a52e0f5ba59c604c70ee99d44ead01a2ee183bc238c42e08ec', 'teacher', '2026-09-11 16:48:12', '2026-10-11 15:48:12', 0),
(13, 1, 'b683f4608974aabf25e14001b782f1606046ebee9a7040242aa3b40c92411b0b', 'admin', '2026-09-11 18:02:29', '2026-10-11 17:02:29', 0),
(14, 2, '822d04b5f5ad7fda31b1fd1722ea703d2fe404db4d6fbdc4c1e24f5d133b58ee', 'teacher', '2026-09-11 18:14:58', '2026-10-11 17:14:58', 0),
(15, 2, '31bd78b0b1ba51441887eacd2ffb277bccec60335285c35b65ed999d939d13c4', 'teacher', '2026-09-11 18:22:55', '2026-10-11 17:22:55', 0),
(16, 3, 'fa65fe80e03440af39c951bc0fd053ff32c18302a0fab15d9da2f9e93f925966', 'teacher', '2026-09-11 18:43:16', '2026-10-11 17:43:16', 0),
(17, 1, 'fa737fc572e34dea39824b3a8fffc39fd6c5b5f6e23ece953d1fb224efd211dd', 'admin', '2026-09-12 08:38:44', '2026-10-12 07:38:44', 0),
(18, 15, 'a7efa2cfef0cc9de43a4277c6db0f0d3e3f9dd24e953a0d4cf470e0e11376245', 'teacher', '2026-09-12 08:39:13', '2026-10-12 07:39:13', 0),
(19, 2, '7f72e39ec7101fdeaeb6838b593b4a0dc0a20be9bfbf2a352ad2276a566e83a4', 'teacher', '2026-09-12 08:41:42', '2026-10-12 07:41:42', 0),
(20, 2, '741497a4fa1c468f0a57654078691100eb237110d95bdc43858a498bc5eadce8', 'teacher', '2026-09-12 12:12:24', '2026-10-12 12:12:23', 0),
(21, 1, 'f420472d4096da46044ede348ec32ac2a0a623184533d17bfe825f21059405c8', 'admin', '2026-09-13 09:06:01', '2026-10-13 09:06:01', 0),
(22, 15, '9736f01d5273ee3259e81cc597cb510297f339756fb45ab5753cebc4390f65bf', 'teacher', '2026-09-13 09:34:52', '2026-10-13 09:34:52', 0),
(23, 15, 'c28d2826d8aec511e0e59deaa1b3415fa7a75775234be0f3d184b3cb581fc3d4', 'teacher', '2026-09-13 10:44:55', '2026-10-13 10:44:55', 0),
(24, 1, '0d5fb1acf666982bbbcf89d3add89118808a6943433db2c572fbf1e45cd702e6', 'admin', '2026-09-13 10:47:56', '2026-10-13 10:47:56', 0),
(25, 1, 'ca8924e7e533ccfb056be0db5cd1b67cc4a9a8bcfd74a90b9b659eddecc9286a', 'teacher', '2026-09-13 20:47:35', '2026-10-13 20:47:35', 0),
(26, 15, '516bfc7735e605fe95f4461ca21446f53e2bd9076687d59b5fb7835109133288', 'teacher', '2026-09-13 20:49:46', '2026-10-13 20:49:46', 0),
(27, 15, '9696726f5dd9e0dd6f7e31c707a20172324bf840fc9c35c031b40e9d62ad4912', 'teacher', '2026-09-15 22:24:21', '2026-10-15 22:24:21', 0),
(28, 1, '6849457f96967a69f789291027ab8dbadd0d0e90dd58ad7b89ed4dcf373841e7', 'admin', '2026-09-16 17:43:31', '2026-10-16 17:43:30', 0),
(29, 15, '0cbb6d61fb1874c094ba4d2e6ba2456c3a7a83eb44a627d1c536b790fa494771', 'teacher', '2026-09-16 17:43:53', '2026-10-16 17:43:53', 0),
(30, 15, 'ef2727ea9c4c61ac87be1dc10fe0230dcf7aa2d3ac151afa92f3c15e9dbf58c0', 'teacher', '2026-09-16 21:11:56', '2026-10-16 21:11:56', 0),
(31, 1, 'a77e9b932931b81758d02158056830201b81a0cd21b132f41e77449f892d4ef7', 'admin', '2026-09-16 21:12:56', '2026-10-16 21:12:56', 0),
(32, 15, '74521ee0e4fb24b21d44f4035c078b4a3dc1550bff8390d81826c397f4c62b95', 'teacher', '2026-09-16 22:23:50', '2026-10-16 22:23:50', 0),
(33, 1, 'ba4b313072b64482be758d2967670f2638d7b1175bf5f2e65a9df059f848e9d4', 'admin', '2026-09-16 22:45:52', '2026-10-16 22:45:51', 0),
(34, 1, '89c611d5ee4a58451decc5b8e670b6aeabe5039795940d620cfcc301f23e9f46', 'admin', '2026-09-16 22:47:32', '2026-10-16 22:47:32', 0),
(35, 8, 'fd969c9d6465eb39d3805360624a75b44f2f883c082725792c9970e209069175', 'teacher', '2026-09-16 22:49:12', '2026-10-16 22:49:12', 0),
(36, 15, '1de3102fc54d26039c61a774cdfe83cae18cbcdd2e973d717be10c65b5f8b9e1', 'teacher', '2026-09-16 22:52:58', '2026-10-16 22:52:58', 0),
(37, 1, '941af5a25ed0a36569b8a8a03a517abda4b68b145f4c59e18342ba38e614c155', 'admin', '2026-09-17 00:23:49', '2026-10-17 00:23:49', 0),
(38, 1, '526c2a4bf9fa899c0494a9da12d86f9eb0d597b1758f9cf54a5c136226d5a9ab', 'admin', '2026-09-17 09:53:39', '2026-10-17 09:53:38', 0),
(39, 1, 'dfd0554cdb30df597a045861165de27a60b976722f33e993a32cc6eaba2e7712', 'admin', '2026-09-17 13:13:12', '2026-10-17 13:13:12', 0),
(40, 15, 'adf8b5a36c67acbde2f484e5611d5ba5105228acc8efb32cd35b635f050efb8d', 'teacher', '2026-09-17 13:51:35', '2026-10-17 13:51:35', 0),
(41, 8, '61a3af8ae27496f85eb0dc4f429ac0ef332e807f75aa5c0d177c28c05e20b52d', 'teacher', '2026-09-17 21:35:36', '2026-10-17 21:35:36', 0),
(42, 15, '4e784000a70d16bb6b52def7a7570d1d74f36d42b567ca4243f3653eca191b8d', 'teacher', '2026-09-17 22:26:43', '2026-10-17 22:26:43', 0),
(43, 15, 'c547cf90d3c963af72d8aa092333a8f0fe27ed4ae3a9d7395835cd2b3d8e8bdd', 'teacher', '2026-09-17 22:34:21', '2026-10-17 22:34:21', 0),
(44, 1, '3a26eee4d157e795bc7b9c4f84dae8e4451a13bf6fe2e0ce240555b1c69ff082', 'admin', '2026-09-18 10:44:05', '2026-10-18 10:44:05', 0);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('teaching','exam','revision','holiday','church','meeting','assessment','announcement','important') NOT NULL DEFAULT 'announcement',
  `event_date` date NOT NULL COMMENT 'Gregorian date stored; Ethiopian date is derived for display via db.php gregorianToEthiopian()',
  `ethiopian_year` int(11) DEFAULT NULL,
  `ethiopian_month` tinyint(4) DEFAULT NULL,
  `ethiopian_day` tinyint(4) DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `reminder_days_before` varchar(50) DEFAULT NULL COMMENT 'CSV of days, e.g. "14,7,3,1,0"',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `title`, `description`, `event_type`, `event_date`, `ethiopian_year`, `ethiopian_month`, `ethiopian_day`, `priority`, `reminder_days_before`, `created_by`, `created_at`, `updated_at`, `is_deleted`) VALUES
(1, 'test exam', '', 'exam', '2026-09-13', 2019, 1, 3, 'normal', '', 1, '2026-09-11 18:03:47', '2026-09-17 15:15:21', 1),
(2, 'የተማሪዎች ምዝገባ ጊዜ', 'የምዝገባ ጊዜ ከጳጉሜ 1/2018 ዓ.ም - ጥቅምት 1/2019 ዓ.ም ብቻ ይሆናል፡፡', 'announcement', '2026-09-11', 2019, 1, 1, 'normal', NULL, 1, '2026-09-12 10:16:19', '2026-09-17 15:15:13', 1),
(3, 'የትምህርት መጀመሪያ ቀን እና አጠቃላይ ገለጻ', 'ለተመዘገቡ ተማሪዎች አጠቃላይ የትምህርት ካላንደርን እና የትምህርቱን ሥርዓት በተመለከተ ገለጻ የሚሰጥበት እና ትምህርት የሚጀመርበት ቀን፡፡', 'teaching', '2026-10-04', 2019, 1, 24, 'normal', NULL, 1, '2026-09-12 10:16:19', '2026-09-12 10:16:19', 0),
(4, 'የመጀመሪያ የክፍል ምዘና (Mid Exam - 20%)', 'የመጀመሪያ የክፍል ምዘና ከ20% ህዳር 26-27/2019 ዓ.ም (ሳምንት ፬ ቅዳሜ እና እሁድ)፡፡', 'exam', '2026-12-05', 2019, 3, 26, 'high', NULL, 1, '2026-09-12 10:16:19', '2026-09-12 10:16:19', 0),
(5, 'የ1ኛ መንፈቀ ዓመት የትምህርት ማጠናቀቂያ ቀን', 'የመጀመሪያ መንፈቅ ዓመት የትምህርት ማጠናቀቂያ ጊዜ የካቲት 13-14/2019 ዓ.ም ይሆናል፡፡', 'important', '2027-02-20', 2019, 6, 13, 'high', NULL, 1, '2026-09-12 10:16:19', '2026-09-12 10:16:19', 0),
(6, 'የ1ኛ መንፈቀ ዓመት ማጠቃለያ ምዘና (Final Exam - 30%)', 'የመጀመሪያ መንፈቅ ዓመት ማጠቃለያ ምዘና ከ30% የሚሰጥበት ጊዜ የካቲት 20-21/2019 ዓ.ም ይሆናል፡፡', 'exam', '2027-02-27', 2019, 6, 20, 'high', NULL, 1, '2026-09-12 10:16:19', '2026-09-12 10:16:19', 0),
(7, 'የፈተና ወረቀት እና ከ100% ውጤት ማሳወቂያ ቀን', 'ለተማሪዎች የፈተና ወረቀት እና ከ100% ውጤት የሚሰጥበት ቀን የካቲት 27-28/2019 ዓ.ም፡፡', 'assessment', '2027-03-06', 2019, 6, 27, 'high', NULL, 1, '2026-09-12 10:16:20', '2026-09-12 10:16:20', 0),
(8, 'የውጤት ማስተላለፊያ ወረቀት ማስረከቢያ የመጨረሻ ቀን', 'መምህራን የመጀመሪያ መንፈቅ ዓመት ከ100% ውጤት ለተማሪዎች አሳይተው አጠናቀው በውጤት ማስተላለፊያ ወረቀት እስከ መጋቢት 4 እና 5/2019 ዓ.ም ገቢ ማድረግ ይጠበቅባቸዋል፡፡', 'important', '2027-03-13', 2019, 7, 4, 'high', NULL, 1, '2026-09-12 10:16:20', '2026-09-12 10:16:20', 0);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_event_targets`
--

CREATE TABLE `calendar_event_targets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `grade_id`, `name`, `description`, `created_at`) VALUES
(1, 12, '7ኛ ክፍል (Grade 7)', 'የ7ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(2, 11, '8ኛ ክፍል (Grade 8)', 'የ8ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(3, 10, '9ኛ ክፍል (Grade 9)', 'የ9ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(4, 9, '10ኛ ክፍል (Grade 10)', 'የ10ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(5, 8, '11ኛ ክፍል (Grade 11)', 'የ11ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(6, 7, '12ኛ ክፍል (Grade 12)', 'የ12ኛ ክፍል ሰንበት ትምህርት', '2026-05-08 22:30:45'),
(7, 6, '1ኛ ክፍል (Grade 1)', 'የ1ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23'),
(8, 5, '2ኛ ክፍል (Grade 2)', 'የ2ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23'),
(9, 4, '3ኛ ክፍል (Grade 3)', 'የ3ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23'),
(10, 3, '4ኛ ክፍል (Grade 4)', 'የ4ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23'),
(11, 2, '5ኛ ክፍል (Grade 5)', 'የ5ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23'),
(12, 1, '6ኛ ክፍል (Grade 6)', 'የ6ኛ ክፍል ሰንበት ትምህርት (ህፃናት)', '2026-09-09 18:01:23');

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_topics`
--

CREATE TABLE `curriculum_topics` (
  `id` int(11) NOT NULL,
  `grade_level` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `chapter_name` varchar(255) NOT NULL,
  `sub_topic_name` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `curriculum_topics`
--

INSERT INTO `curriculum_topics` (`id`, `grade_level`, `subject_name`, `chapter_name`, `sub_topic_name`, `sort_order`, `created_at`) VALUES
(1, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምልኮተ እግዚአብሔር', '፩.፩ አምልኮተ እግዚአብሔር በዘመነ አበው', 1, '2026-09-15 22:24:01'),
(2, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምልኮተ እግዚአብሔር', '፩.፪ አምልኮተ እግዚአብሔር በዘመነ ኦሪት', 2, '2026-09-15 22:24:01'),
(3, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምልኮተ እግዚአብሔር', '፩.፫ አምልኮተ እግዚአብሔር በዘመነ ሐዲስ', 3, '2026-09-15 22:24:02'),
(4, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ የሃይማኖት ምንነት', '፪.፩ የሃይማኖት ትርጉም', 4, '2026-09-15 22:24:02'),
(5, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ የሃይማኖት ምንነት', '፪.፪ ሃይማኖት ለምን ያስፈልጋል?', 5, '2026-09-15 22:24:02'),
(6, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ የሃይማኖት ምንነት', '፪.፫ ሃይማኖትና ምግባር', 6, '2026-09-15 22:24:02'),
(7, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ መሠረተ ሃይማኖት (አዕማደ ምሥጢር)', '፫.፩ አምስቱ አዕማደ ምሥጢር መግቢያ', 7, '2026-09-15 22:24:02'),
(8, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ መሠረተ ሃይማኖት (አዕማደ ምሥጢር)', '፫.፪ ምሥጢረ ሥላሴ', 8, '2026-09-15 22:24:02'),
(9, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ መሠረተ ሃይማኖት (አዕማደ ምሥጢር)', '፫.፫ ምሥጢረ ሥጋዌ', 9, '2026-09-15 22:24:02'),
(10, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ እመቤታችን ቅድስት ድንግል ማርያም', '፬.፩ የእመቤታችን የዘር ሐረግና ልደት', 10, '2026-09-15 22:24:02'),
(11, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ እመቤታችን ቅድስት ድንግል ማርያም', '፬.፪ እመቤታችን በቤተ መቅደስ', 11, '2026-09-15 22:24:02'),
(12, 1, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ እመቤታችን ቅድስት ድንግል ማርያም', '፬.፫ ብሥራተ ገብርኤልና የአምላክ እናት መሆን', 12, '2026-09-15 22:24:02'),
(13, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ክርስቲያናዊ ሥነ ምግባር ምንነት', '፩.፩ ሥነ ምግባር ማለት ምን ማለት ነው?', 13, '2026-09-15 22:24:02'),
(14, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ክርስቲያናዊ ሥነ ምግባር ምንነት', '፩.፪ መልካም ሥነ ምግባርና ክርስቲያናዊ ሕይወት', 14, '2026-09-15 22:24:02'),
(15, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ታዛዥነትና አክብሮት', '፪.፩ ለእግዚአብሔር መታዘዝ', 15, '2026-09-15 22:24:02'),
(16, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ታዛዥነትና አክብሮት', '፪.፪ ለወላጆች መታዘዝና ማክበር', 16, '2026-09-15 22:24:02'),
(17, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ታዛዥነትና አክብሮት', '፪.፫ ለመምህራንና ለአበው ታዛዥ መሆን', 17, '2026-09-15 22:24:03'),
(18, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ፍቅርና ሰላም', '፫.፩ እርስ በእርስ መዋደድ', 18, '2026-09-15 22:24:03'),
(19, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ፍቅርና ሰላም', '፫.፪ ይቅርታ ማድረግና በሰላም መኖር', 19, '2026-09-15 22:24:03'),
(20, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ፍቅርና ሰላም', '፫.፫ እውነትን መናገርና ከሐሰት መራቅ', 20, '2026-09-15 22:24:03'),
(21, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጸሎትና መንፈሳዊ ልምምድ', '፬.፩ ጸሎት ምንድን ነው? እንዴት እንጸልያለን?', 21, '2026-09-15 22:24:03'),
(22, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጸሎትና መንፈሳዊ ልምምድ', '፬.፪ የጠዋትና የማታ ጸሎት', 22, '2026-09-15 22:24:03'),
(23, 1, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጸሎትና መንፈሳዊ ልምምድ', '፬.፫ ከምግብ በፊትና በኋላ የሚደረግ ጸሎት', 23, '2026-09-15 22:24:03'),
(24, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ ምንነት', '፩.፩ መጽሐፍ ቅዱስ ምንድን ነው?', 24, '2026-09-15 22:24:03'),
(25, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ ምንነት', '፩.፪ መጽሐፍ ቅዱስ የእግዚአብሔር ቃል መሆኑ', 25, '2026-09-15 22:24:03'),
(26, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ አዳም እና ሔዋን', '፪.፩ የመጀመሪያዎቹ ሰዎች አዳምና ሔዋን', 26, '2026-09-15 22:24:03'),
(27, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ አዳም እና ሔዋን', '፪.፪ ገነትና የእግዚአብሔር ትእዛዝ', 27, '2026-09-15 22:24:03'),
(28, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ ኖኅ እና መርከቡ', '፫.፩ የጻድቁ ኖኅ ታሪክ', 28, '2026-09-15 22:24:04'),
(29, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ ኖኅ እና መርከቡ', '፫.፪ የኖኅ መርከብና የቃል ኪዳን ቀስተ ደመና', 29, '2026-09-15 22:24:04'),
(30, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ አብርሃምና ይስሐቅ', '፬.፩ የአባታችን አብርሃም ታዛዥነት', 30, '2026-09-15 22:24:04'),
(31, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ አብርሃምና ይስሐቅ', '፬.፪ ይስሐቅና የእግዚአብሔር በረከት', 31, '2026-09-15 22:24:04'),
(32, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ የጌታችን የኢየሱስ ክርስቶስ ልደት', '፭.፩ እመቤታችን ቅድስት ድንግል ማርያም', 32, '2026-09-15 22:24:04'),
(33, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ የጌታችን የኢየሱስ ክርስቶስ ልደት', '፭.፪ የጌታችን ልደት በቤተልሔም', 33, '2026-09-15 22:24:04'),
(34, 1, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ የጌታችን የኢየሱስ ክርስቶስ ልደት', '፭.፫ እረኞችና ሰብአ ሰገል', 34, '2026-09-15 22:24:04'),
(35, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ የቤተ ክርስቲያን ምንነት', '፩.፩ የቤተ ክርስቲያን ትርጉም', 35, '2026-09-15 22:24:04'),
(36, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ የቤተ ክርስቲያን ምንነት', '፩.፪ የቤተ ክርስቲያን ሕንፃና አገልግሎት', 36, '2026-09-15 22:24:04'),
(37, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ ወደ ቤተ ክርስቲያን ስንሄድ የሚደረግ ሥርዓት', '፪.፩ የአለባበስና የአካሄድ ሥርዓት', 37, '2026-09-15 22:24:04'),
(38, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ ወደ ቤተ ክርስቲያን ስንሄድ የሚደረግ ሥርዓት', '፪.፪ የቤተ ክርስቲያን በር ላይ የሚደረግ ጸሎትና ስግደት', 38, '2026-09-15 22:24:04'),
(39, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ በቤተ ክርስቲያን ውስጥ የሚደረግ ጥንቃቄ', '፫.፩ በቤተ ክርስቲያን ውስጥ ዝምታና አክብሮት መጠበቅ', 39, '2026-09-15 22:24:04'),
(40, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ በቤተ ክርስቲያን ውስጥ የሚደረግ ጥንቃቄ', '፫.፪ ከቅዳሴና ጸሎት በኋላ የሚደረግ ሥርዓት', 40, '2026-09-15 22:24:04'),
(41, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ታቦትና መስቀል', '፬.፩ የታቦት ክብርና አገልግሎት', 41, '2026-09-15 22:24:04'),
(42, 1, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ታቦትና መስቀል', '፬.፪ የመስቀል ክብርና የመስቀል ምልክት ማድረግ', 42, '2026-09-15 22:24:04'),
(43, 1, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ ፊደላት', '፩.፩ የግእዝ ፊደላት አነባበብ እና አጠቃቀም (ሀ - ፐ)', 43, '2026-09-15 22:24:04'),
(44, 1, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ ፊደላት', '፩.፪ የግእዝ ሰባቱ ድምፆች (ግእዝ እስከ ሳብዕ)', 44, '2026-09-15 22:24:04'),
(45, 1, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ቁጥሮች', '፪.፩ የግእዝ ቁጥሮች ከ፩ እስከ ፲ (ከ1 እስከ 10)', 45, '2026-09-15 22:24:04'),
(46, 1, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ቁጥሮች', '፪.፪ የግእዝ ቁጥሮች ከ፲፩ እስከ ፳ (ከ11 እስከ 20)', 46, '2026-09-15 22:24:04'),
(47, 1, 'ግእዝ', 'ምዕራፍ ፫ ቀላል የግእዝ ቃላት', '፫.፩ ቀላል የግእዝ ስሞችና ትርጉማቸው', 47, '2026-09-15 22:24:04'),
(48, 1, 'ግእዝ', 'ምዕራፍ ፫ ቀላል የግእዝ ቃላት', '፫.፪ የቤተሰብ እና የዕለት ተዕለት የግእዝ ቃላት', 48, '2026-09-15 22:24:04'),
(49, 1, 'ግእዝ', 'ምዕራፍ ፬ አጫጭር የግእዝ ሐረጋትና ጸሎታት', '፬.፩ አጫጭር የግእዝ ሐረጋትና መሠረታዊ ንግግሮች', 49, '2026-09-15 22:24:04'),
(50, 1, 'ግእዝ', 'ምዕራፍ ፬ አጫጭር የግእዝ ሐረጋትና ጸሎታት', '፬.፪ በስመ አብ ወወልድ ወመንፈስ ቅዱስ አሐዱ አምላክ', 50, '2026-09-15 22:24:04'),
(51, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ ምሥጢረ ሥላሴ', '፩.፩ የሥላሴ አንድነትና ሦስትነት', 51, '2026-09-15 22:24:05'),
(52, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ ምሥጢረ ሥላሴ', '፩.፪ የስም፣ የአካል፣ የግብር ሦስትነት', 52, '2026-09-15 22:24:05'),
(53, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ ምሥጢረ ሥላሴ', '፩.፫ በሥላሴ ስም ማመንና መጠመቅ', 53, '2026-09-15 22:24:05'),
(54, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ምሥጢረ ሥጋዌ', '፪.፩ ጌታችን ሰው የመሆኑ ምስጢር', 54, '2026-09-15 22:24:05'),
(55, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ምሥጢረ ሥጋዌ', '፪.፪ የተዋሕዶ ትርጉም', 55, '2026-09-15 22:24:05'),
(56, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ምሥጢረ ሥጋዌ', '፪.፫ ፍጹም አምላክ ፍጹም ሰው', 56, '2026-09-15 22:24:05'),
(57, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ምሥጢረ ጥምቀት', '፫.፩ የጥምቀት ትርጉምና አስፈላጊነት', 57, '2026-09-15 22:24:05'),
(58, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ምሥጢረ ጥምቀት', '፫.፪ የጌታችን ጥምቀት በዮርዳኖስ', 58, '2026-09-15 22:24:05'),
(59, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ምሥጢረ ጥምቀት', '፫.፫ የክርስትና ጥምቀት ለድነት', 59, '2026-09-15 22:24:05'),
(60, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሥጢረ ቁርባን', '፬.፩ የቅዱስ ቁርባን ምንነት', 60, '2026-09-15 22:24:05'),
(61, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሥጢረ ቁርባን', '፬.፪ የጌታችን ሥጋና ደም ክብር', 61, '2026-09-15 22:24:05'),
(62, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሥጢረ ቁርባን', '፬.፫ ለቁርባን የሚደረግ ቅድመ ዝግጅት', 62, '2026-09-15 22:24:05'),
(63, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፭ ምሥጢረ ትንሣኤ ሙታን', '፭.፩ የሙታን መነሣት', 63, '2026-09-15 22:24:05'),
(64, 2, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፭ ምሥጢረ ትንሣኤ ሙታን', '፭.፪ ዳግም ምጽአትና የዘላለም ሕይወት', 64, '2026-09-15 22:24:05'),
(65, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ታሪኮች', '፩.፩ የዮሴፍ ታሪክና ይቅር ባይነት', 65, '2026-09-15 22:24:05'),
(66, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ታሪኮች', '፩.፪ የነቢዩ ሙሴ ታሪክና ሕዝበ እስራኤል', 66, '2026-09-15 22:24:05'),
(67, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ታሪኮች', '፩.፫ የዳዊትና ጎልያድ ታሪክ', 67, '2026-09-15 22:24:05'),
(68, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ታሪኮች', '፩.፬ ንጉሥ ሰሎሞንና ጥበቡ', 68, '2026-09-15 22:24:05'),
(69, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን ታሪኮች', '፪.፩ የመጥምቁ ዮሐንስ ልደትና አገልግሎት', 69, '2026-09-15 22:24:06'),
(70, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን ታሪኮች', '፪.፪ የጌታችን ጥምቀትና ፈተና በገዳመ ቆሮንቶስ', 70, '2026-09-15 22:24:06'),
(71, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን ታሪኮች', '፪.፫ አሥራ ሁለቱ ሐዋርያት መጠራት', 71, '2026-09-15 22:24:06'),
(72, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ተአምራት', '፫.፩ በቃና ዘገሊላ ውኃውን ወደ ወይን መለወጥ', 72, '2026-09-15 22:24:06'),
(73, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ተአምራት', '፫.፪ አምስቱን እንጀራና ሁለቱን ዓሣ ማበርከት', 73, '2026-09-15 22:24:06'),
(74, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ተአምራት', '፫.፫ ማዕበሉን ፀጥ ማሰኘትና በባሕር ላይ መራመድ', 74, '2026-09-15 22:24:06'),
(75, 2, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ተአምራት', '፫.፬ አልዓዛርን ከሞት ማስነሣት', 75, '2026-09-15 22:24:06'),
(76, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ የቤተ ክርስቲያን አባቶችና አገልጋዮች', '፩.፩ ሦስቱ የክህነት ማዕረጋት (ዲያቆን፣ ቀሲስ፣ ጳጳስ)', 76, '2026-09-15 22:24:06'),
(77, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ የቤተ ክርስቲያን አባቶችና አገልጋዮች', '፩.፪ የመምህራንና የዲያቆናት አገልግሎት', 77, '2026-09-15 22:24:06'),
(78, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የሰንበት ክብር', '፪.፩ የቀዳሚትና የእሑድ ሰንበት ክብር', 78, '2026-09-15 22:24:06'),
(79, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የሰንበት ክብር', '፪.፪ ሰንበትን እንዴት እናከብራለን?', 79, '2026-09-15 22:24:06'),
(80, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ የጾም ሥርዓት', '፫.፩ ጾም ምንድን ነው? ለምን እንጾማለን?', 80, '2026-09-15 22:24:06'),
(81, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ የጾም ሥርዓት', '፫.፪ የሰባቱ አጽዋማት አጠቃላይ እይታ', 81, '2026-09-15 22:24:06'),
(82, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ የቅዳሴ ሥርዓት', '፬.፩ ቅዳሴ ምንድን ነው?', 82, '2026-09-15 22:24:06'),
(83, 2, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ የቅዳሴ ሥርዓት', '፬.፪ በቅዳሴ ጊዜ ምእመናን የሚኖራቸው ተሳትፎ', 83, '2026-09-15 22:24:06'),
(84, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ንጽሕናና ራስን መጠበቅ', '፩.፩ የሰውነትና የአካባቢ ንጽሕና', 84, '2026-09-15 22:24:06'),
(85, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ንጽሕናና ራስን መጠበቅ', '፩.፪ የልቦናና የሐሳብ ንጽሕና', 85, '2026-09-15 22:24:06'),
(86, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ታማኝነትና ቅንነት', '፪.፩ በንግግርና በሥራ ታማኝ መሆን', 86, '2026-09-15 22:24:06'),
(87, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ታማኝነትና ቅንነት', '፪.፪ የሰውን ንብረት መጠበቅና አለመመኘት', 87, '2026-09-15 22:24:06'),
(88, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ትዕግሥትና ትሕትና', '፫.፩ ትሕትና የክርስቲያን መገለጫ', 88, '2026-09-15 22:24:06'),
(89, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ትዕግሥትና ትሕትና', '፫.፪ ችግሮችን በትዕግሥት ማሳለፍ', 89, '2026-09-15 22:24:06'),
(90, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ምጽዋትና ለተቸገሩ መድረስ', '፬.፩ ምጽዋት መስጠትና በረከቱ', 90, '2026-09-15 22:24:07'),
(91, 2, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ምጽዋትና ለተቸገሩ መድረስ', '፬.፪ አረጋውያንንና ድሆችን መርዳት', 91, '2026-09-15 22:24:07'),
(92, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የክርስትና መግቢያ በኢትዮጵያ', '፩.፩ ኢትዮጵያውያን ወደ ኢየሩሳሌም ያደርጉት የነበረው ጉዞ', 92, '2026-09-15 22:24:07'),
(93, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የክርስትና መግቢያ በኢትዮጵያ', '፩.፪ የኢትዮጵያዊው ጃንደረባ ባኮስ ታሪክ', 93, '2026-09-15 22:24:07'),
(94, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የክርስትና መግቢያ በኢትዮጵያ', '፩.፫ ሐዋርያው ቅዱስ ማቴዎስ በኢትዮጵያ', 94, '2026-09-15 22:24:07'),
(95, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ አበው ቅዱሳን በኢትዮጵያ', '፪.፩ ፍሬምናጦስ (አባ ሰላማ ከሣቴ ብርሃን)', 95, '2026-09-15 22:24:07'),
(96, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ አበው ቅዱሳን በኢትዮጵያ', '፪.፪ ነገሥታቱ አብርሃና አጽብሐ', 96, '2026-09-15 22:24:07'),
(97, 2, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ አበው ቅዱሳን በኢትዮጵያ', '፪.፫ ክርስትና የኢትዮጵያ ብሔራዊ ሃይማኖት መሆኑ', 97, '2026-09-15 22:24:07'),
(98, 2, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ ንባብ ልምምድ', '፩.፩ የቃላት ቅንብርና ንባብ', 98, '2026-09-15 22:24:07'),
(99, 2, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ ንባብ ልምምድ', '፩.፪ አጫጭር የግእዝ ዐረፍተ ነገሮች', 99, '2026-09-15 22:24:07'),
(100, 2, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ቁጥሮች ቀጣይ', '፪.፩ የግእዝ ቁጥሮች ከ፳ እስከ ፻ (ከ20 እስከ 100)', 100, '2026-09-15 22:24:07'),
(101, 2, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ቁጥሮች ቀጣይ', '፪.፪ የቁጥሮች አጣጣልና አጠቃቀም', 101, '2026-09-15 22:24:08'),
(102, 2, 'ግእዝ', 'ምዕራፍ ፫ መሠረታዊ የግእዝ ሰዋስው', '፫.፩ የግእዝ ስሞችና አመልካች ቃላት (ዝንቱ፣ ዛቲ፣ እሉ)', 102, '2026-09-15 22:24:08'),
(103, 2, 'ግእዝ', 'ምዕራፍ ፫ መሠረታዊ የግእዝ ሰዋስው', '፫.፪ መራሕያን (አነ፣ ንሕነ፣ አንተ፣ አንቲ)', 103, '2026-09-15 22:24:08'),
(104, 2, 'ግእዝ', 'ምዕራፍ ፬ የጸሎት ንባብ በግእዝ', '፬.፩ ጸሎተ እግዝእትነ ማርያም (ተዓብዮ ነፍስየ)', 104, '2026-09-15 22:24:08'),
(105, 2, 'ግእዝ', 'ምዕራፍ ፬ የጸሎት ንባብ በግእዝ', '፬.፪ ሰላም ለኪ ማርያም ድንግል', 105, '2026-09-15 22:24:08'),
(106, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምስቱ አዕማደ ምሥጢር በዝርዝር', '፩.፩ ምሥጢረ ሥላሴ እና ምሥጢረ ሥጋዌ', 106, '2026-09-15 22:24:08'),
(107, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምስቱ አዕማደ ምሥጢር በዝርዝር', '፩.፪ ምሥጢረ ጥምቀት እና ምሥጢረ ቁርባን', 107, '2026-09-15 22:24:08'),
(108, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ አምስቱ አዕማደ ምሥጢር በዝርዝር', '፩.፫ ምሥጢረ ትንሣኤ ሙታን', 108, '2026-09-15 22:24:08'),
(109, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ እመቤታችን ቅድስት ድንግል ማርያም', '፪.፩ የዘላለም ድንግልናዋ', 109, '2026-09-15 22:24:08'),
(110, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ እመቤታችን ቅድስት ድንግል ማርያም', '፪.፪ ወላዲተ አምላክነቷ', 110, '2026-09-15 22:24:08'),
(111, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ እመቤታችን ቅድስት ድንግል ማርያም', '፪.፫ አማላጅነቷና ክብሯ', 111, '2026-09-15 22:24:08'),
(112, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ቅዱሳን መላእክት', '፫.፩ የመላእክት ተፈጥሮና አገልግሎት', 112, '2026-09-15 22:24:09'),
(113, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ቅዱሳን መላእክት', '፫.፪ የመላእክት አማላጅነትና ተራዳኢነት', 113, '2026-09-15 22:24:09'),
(114, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ቅዱሳን መላእክት', '፫.፫ ሊቃነ መላእክት (ሚካኤል፣ ገብርኤል፣ ሩፋኤል)', 114, '2026-09-15 22:24:09'),
(115, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ቅዱሳን ጻድቃንና ሰማዕታት', '፬.፩ የቅድስና ትርጉም', 115, '2026-09-15 22:24:09'),
(116, 3, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ቅዱሳን ጻድቃንና ሰማዕታት', '፬.፪ የጻድቃንና የሰማዕታት ክብርና ቃል ኪዳን', 116, '2026-09-15 22:24:09'),
(117, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ነቢያት', '፩.፩ ነቢዩ ኤልያስና ተአምራቱ', 117, '2026-09-15 22:24:09'),
(118, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ነቢያት', '፩.፪ ነቢዩ ኤልሳዕ', 118, '2026-09-15 22:24:09'),
(119, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ነቢያት', '፩.፫ ነቢዩ ዳንኤልና ሠለስቱ ደቂቅ', 119, '2026-09-15 22:24:09'),
(120, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የብሉይ ኪዳን ነቢያት', '፩.፬ ነቢዩ ዮናስና የነነዌ ሰዎች ንስሐ', 120, '2026-09-15 22:24:09'),
(121, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የጌታችን ኢየሱስ ክርስቶስ ምሳሌዎች', '፪.፩ የዘሪው ምሳሌ', 121, '2026-09-15 22:24:09'),
(122, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የጌታችን ኢየሱስ ክርስቶስ ምሳሌዎች', '፪.፪ የጠፋው በግና የጠፋው ልጅ ምሳሌ', 122, '2026-09-15 22:24:10'),
(123, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የጌታችን ኢየሱስ ክርስቶስ ምሳሌዎች', '፪.፫ የደጉ ሳምራዊ ምሳሌ', 123, '2026-09-15 22:24:11'),
(124, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ሕማማት፣ ስቅለትና ትንሣኤ', '፫.፩ የሆሣዕና በዓልና የጌታችን ወደ ኢየሩሳሌም መግባት', 124, '2026-09-15 22:24:12'),
(125, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ሕማማት፣ ስቅለትና ትንሣኤ', '፫.፪ የሕማማት ሳምንትና ጸሎተ ሐሙስ', 125, '2026-09-15 22:24:14'),
(126, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ሕማማት፣ ስቅለትና ትንሣኤ', '፫.፫ የስቅለተ ዓርብ ውሎና ሞቱ', 126, '2026-09-15 22:24:14'),
(127, 3, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የጌታችን ሕማማት፣ ስቅለትና ትንሣኤ', '፫.፬ ክብር ትንሣኤውና ዕርገቱ', 127, '2026-09-15 22:24:15'),
(128, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሰባቱ ምሥጢራተ ቤተ ክርስቲያን መግቢያ', '፩.፩ የምሥጢራተ ቤተ ክርስቲያን ትርጉምና ዓላማ', 128, '2026-09-15 22:24:15'),
(129, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሰባቱ ምሥጢራተ ቤተ ክርስቲያን መግቢያ', '፩.፪ ማይሞቱ ምሥጢራትና ተደጋጋሚ ምሥጢራት', 129, '2026-09-15 22:24:16'),
(130, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የጸሎት ዓይነቶችና ጊዜያት', '፪.፩ ሰባቱ የጸሎት ጊዜያት', 130, '2026-09-15 22:24:16'),
(131, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የጸሎት ዓይነቶችና ጊዜያት', '፪.፪ የግልና የማኅበር ጸሎት', 131, '2026-09-15 22:24:16'),
(132, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ የቤተ ክርስቲያን ንዋያተ ቅድሳት', '፫.፩ ጽላት፣ ጽዋ፣ ጻሕል፣ መስቀል', 132, '2026-09-15 22:24:16'),
(133, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ የቤተ ክርስቲያን ንዋያተ ቅድሳት', '፫.፪ ማዕጠንት፣ መብራት፣ ጥላ', 133, '2026-09-15 22:24:17'),
(134, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ የቤተ ክርስቲያን በዓላት አከፋፈል', '፬.፩ የጌታችን ዐበይትና ንዑሳን በዓላት', 134, '2026-09-15 22:24:17'),
(135, 3, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ የቤተ ክርስቲያን በዓላት አከፋፈል', '፬.፪ የእመቤታችን፣ የመላእክትና የቅዱሳን በዓላት', 135, '2026-09-15 22:24:17'),
(136, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ዐሥርቱ ቃላት (ትእዛዛት)', '፩.፩ ትእዛዛት ከ፩ እስከ ፬ (ለእግዚአብሔር የሚገቡ)', 136, '2026-09-15 22:24:18'),
(137, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ዐሥርቱ ቃላት (ትእዛዛት)', '፩.፪ ትእዛዛት ከ፭ እስከ ፲ (ለባልንጀራ የሚገቡ)', 137, '2026-09-15 22:24:18'),
(138, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ክርስቲያናዊ ባሕርያት', '፪.፩ ቸርነት፣ የዋህነትና ደግነት', 138, '2026-09-15 22:24:18'),
(139, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ክርስቲያናዊ ባሕርያት', '፪.፪ ንጽሕና፣ ልከኝነትና ጨዋነት', 139, '2026-09-15 22:24:18'),
(140, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ጓደኝነትና ማኅበራዊ ሕይወት', '፫.፩ ጥሩ ጓደኛን መምረጥ', 140, '2026-09-15 22:24:19'),
(141, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ጓደኝነትና ማኅበራዊ ሕይወት', '፫.፪ ከመጥፎ ጓደኛና ሱስ መራቅ', 141, '2026-09-15 22:24:19'),
(142, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጊዜን በአግባቡ መጠቀም', '፬.፩ የጊዜ ክብርና ጥቅም', 142, '2026-09-15 22:24:19'),
(143, 3, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጊዜን በአግባቡ መጠቀም', '፬.፪ ትምህርትንና መንፈሳዊ ሕይወትን ማመጣጠን', 143, '2026-09-15 22:24:19'),
(144, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ተስፋፋት ክርስትና በኢትዮጵያ', '፩.፩ ዘጠኙ ቅዱሳን ወደ ኢትዮጵያ መምጣት', 144, '2026-09-15 22:24:19'),
(145, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ተስፋፋት ክርስትና በኢትዮጵያ', '፩.፪ የዘጠኙ ቅዱሳን ሥራና አስተዋጽኦ (መጻሕፍት መተርጎም፣ ገዳማት ማቋቋም)', 145, '2026-09-15 22:24:20'),
(146, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ቅዱስ ያሬድና የዜማ ድርሰት', '፪.፩ የቅዱስ ያሬድ የሕይወት ታሪክ', 146, '2026-09-15 22:24:20'),
(147, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ቅዱስ ያሬድና የዜማ ድርሰት', '፪.፪ ሦስቱ የዜማ ስልቶች (ግእዝ፣ ዕዝል፣ አራራይ)', 147, '2026-09-15 22:24:20'),
(148, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ቅዱስ ያሬድና የዜማ ድርሰት', '፪.፫ የቅዱስ ያሬድ የዜማ መጻሕፍት (ድጓ፣ ጾመ ድጓ፣ ዝማሬ፣ መዋሥዕት፣ ምዕራፍ)', 148, '2026-09-15 22:24:20'),
(149, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ጥንታዊ ገዳማት በኢትዮጵያ', '፫.፩ ደብረ ዳሞ፣ ደብረ ሊባኖስ፣ ዋልድባ', 149, '2026-09-15 22:24:20'),
(150, 3, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ጥንታዊ ገዳማት በኢትዮጵያ', '፫.፪ የገዳማዊ ሕይወት መሠረት', 150, '2026-09-15 22:24:20'),
(151, 3, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ መራሕያን (አሥሩ መራሕያን)', '፩.፩ ውእቱ፣ ይእቲ፣ ውእቶሙ፣ ውእቶን', 151, '2026-09-15 22:24:20'),
(152, 3, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ መራሕያን (አሥሩ መራሕያን)', '፩.፪ አንተ፣ አንቲ፣ አንትሙ፣ አንትን', 152, '2026-09-15 22:24:20'),
(153, 3, 'ግእዝ', 'ምዕራፍ ፩ የግእዝ መራሕያን (አሥሩ መራሕያን)', '፩.፫ አነ፣ ንሕነ', 153, '2026-09-15 22:24:21'),
(154, 3, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ግሦች መግቢያ', '፪.፩ የቀዳማይ አንቀጽ ግሦች (ቀተለ፣ ገብረ፣ ሐወጸ)', 154, '2026-09-15 22:24:21'),
(155, 3, 'ግእዝ', 'ምዕራፍ ፪ የግእዝ ግሦች መግቢያ', '፪.፪ ግሥን ከመራሕያን ጋር ማዛመድ', 155, '2026-09-15 22:24:21'),
(156, 3, 'ግእዝ', 'ምዕራፍ ፫ የግእዝ ንባብና ትርጉም', '፫.፩ አቡነ ዘበሰማያት በግእዝና በአማርኛ', 156, '2026-09-15 22:24:21'),
(157, 3, 'ግእዝ', 'ምዕራፍ ፫ የግእዝ ንባብና ትርጉም', '፫.፪ ጸሎተ ሃይማኖት በግእዝ ንባብ', 157, '2026-09-15 22:24:21'),
(158, 3, 'ግእዝ', 'ምዕራፍ ፬ አባባሎችና መዝሙራት በግእዝ', '፬.፩ ጥበበ ሰሎሞን ጥቅሶች በግእዝ', 158, '2026-09-15 22:24:21'),
(159, 3, 'ግእዝ', 'ምዕራፍ ፬ አባባሎችና መዝሙራት በግእዝ', '፬.፪ ቀላል የግእዝ ያሬዳዊ መዝሙራት', 159, '2026-09-15 22:24:21'),
(160, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ እመቤታችን ቅድስት ድንግል ማርያም ከልደት እስከ አምላክን መውለድ', '፩.፩ ትውልድ፣ ፅንሰትና ልደት', 160, '2026-09-15 22:24:21'),
(161, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ እመቤታችን ቅድስት ድንግል ማርያም ከልደት እስከ አምላክን መውለድ', '፩.፪ እድገትና ወደ ቤተ መቅደስ መግባት', 161, '2026-09-15 22:24:21'),
(162, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ እመቤታችን ቅድስት ድንግል ማርያም ከልደት እስከ አምላክን መውለድ', '፩.፫ ሕይወት በቤተ መቅደስ', 162, '2026-09-15 22:24:22'),
(163, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ እመቤታችን ቅድስት ድንግል ማርያም ከልደት እስከ አምላክን መውለድ', '፩.፬ ወደ ዮሴፍ ቤት መሄድ', 163, '2026-09-15 22:24:22'),
(164, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ እመቤታችን ቅድስት ድንግል ማርያም ከልደት እስከ አምላክን መውለድ', '፩.፭ ብሥራተ ገብርኤልና አምላክን መፅነስ', 164, '2026-09-15 22:24:22'),
(165, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ከስደት እስከ ዕረፍት', '፪.፩ ስደት ወደ ግብፅ', 165, '2026-09-15 22:24:22'),
(166, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ከስደት እስከ ዕረፍት', '፪.፪ ተመልሶ በናዝሬት መኖር', 166, '2026-09-15 22:24:22'),
(167, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ከስደት እስከ ዕረፍት', '፪.፫ ዕረፍት፣ ትንሣኤና ዕርገት', 167, '2026-09-15 22:24:22'),
(168, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ክብርና አማላጅነት', '፫.፩ ክብረ ድንግል ማርያም', 168, '2026-09-15 22:24:22'),
(169, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ክብርና አማላጅነት', '፫.፪ አማላጅነት', 169, '2026-09-15 22:24:22'),
(170, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ክብርና አማላጅነት', '፫.፫ ዘላለማዊ ድንግልና', 170, '2026-09-15 22:24:22'),
(171, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሳሌዎች', '፬.፩ የድንግል ማርያም የብሉይ ኪዳን ምሳሌዎች', 171, '2026-09-15 22:24:22'),
(172, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሳሌዎች', '፬.፪ ታቦት፣ ጽላት፣ መሶበ ወርቅ፣ ማዕጠንተ ወርቅ', 172, '2026-09-15 22:24:23'),
(173, 4, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ምሳሌዎች', '፬.፫ ዕፀ ጳጦስ፣ መሰላል፣ የጌዴዎን ፀምር', 173, '2026-09-15 22:24:23'),
(174, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፩ የመጽሐፍ ቅዱስ ትርጉም እና ምንነት', 174, '2026-09-15 22:24:23'),
(175, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፪ የመጽሐፍ ቅዱስ ጸሐፊያንና የተጻፈበት ዘመን', 175, '2026-09-15 22:24:23'),
(176, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፫ የመጽሐፍ ቅዱስ ዓላማ', 176, '2026-09-15 22:24:23'),
(177, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፬ የ፹፩ (81) መጻሕፍት አከፋፈል', 177, '2026-09-15 22:24:23'),
(178, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፭ የመጽሐፍ ቅዱስ ቋንቋዎችና ትርጉሞች', 178, '2026-09-15 22:24:23'),
(179, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የመጽሐፍ ቅዱስ አጠቃላይ ገጽታ', '፩.፮ የመጽሐፍ ቅዱስ አንድነትና ልዩነት', 179, '2026-09-15 22:24:23'),
(180, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ አከፋፈል እና ንባብ ሥርዓት', '፪.፩ የብሉይና የሐዲስ ኪዳን አከፋፈል', 180, '2026-09-15 22:24:24'),
(181, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ አከፋፈል እና ንባብ ሥርዓት', '፪.፪ የመጽሐፍ ቅዱስ ንባብ ሥርዓትና ዝግጅት', 181, '2026-09-15 22:24:24'),
(182, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ አከፋፈል እና ንባብ ሥርዓት', '፪.፫ የመጽሐፍ ቅዱስ ጥናት ዘዴዎች', 182, '2026-09-15 22:24:24'),
(183, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ መልክዓ ምድር', '፫.፩ የመጽሐፍ ቅዱስ መልክዓ ምድር መግቢያ', 183, '2026-09-15 22:24:24'),
(184, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ መልክዓ ምድር', '፫.፪ የከነዓን (የተስፋይቱ ምድር) አቀማመጥ', 184, '2026-09-15 22:24:24'),
(185, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ መልክዓ ምድር', '፫.፫ የኢየሩሳሌም ከተማና አካባቢዋ', 185, '2026-09-15 22:24:24'),
(186, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፩ የዮርዳኖስ ወንዝ', 186, '2026-09-15 22:24:24'),
(187, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፪ የገሊላ ባሕር', 187, '2026-09-15 22:24:24'),
(188, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፫ የሙት ባሕር (ጨው ባሕር)', 188, '2026-09-15 22:24:25'),
(189, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፬ ቀይ ባሕርና መሻገሪያው', 189, '2026-09-15 22:24:25'),
(190, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፭ የኤደን ገነት ወንዞች (ግዮን፣ ፊሶን፣ ጤግሮስ፣ ኤፍራጥስ)', 190, '2026-09-15 22:24:25'),
(191, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፮ የሲሎዋም መጠመቂያ', 191, '2026-09-15 22:24:25'),
(192, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፬ የውኃ አካላት እና ምስጢር', '፬.፯ የውኃ መንፈሳዊ ምሥጢር', 192, '2026-09-15 22:24:25'),
(193, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ ተራሮች እና የመስዋዕት ቦታዎች', '፭.፩ ደብረ ሲና (ኮሬብ)', 193, '2026-09-15 22:24:25'),
(194, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ ተራሮች እና የመስዋዕት ቦታዎች', '፭.፪ ደብረ ታቦር', 194, '2026-09-15 22:24:25'),
(195, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ ተራሮች እና የመስዋዕት ቦታዎች', '፭.፫ ደብረ ዘይት', 195, '2026-09-15 22:24:26'),
(196, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፭ ተራሮች እና የመስዋዕት ቦታዎች', '፭.፬ ቀራንዮና ደብረ ሞሪያ', 196, '2026-09-15 22:24:26'),
(197, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፮ እንስሳት እና ዕፅዋት', '፮.፩ በመጽሐፍ ቅዱስ የተጠቀሱ እንስሳትና ምሳሌነታቸው', 197, '2026-09-15 22:24:26'),
(198, 4, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፮ እንስሳት እና ዕፅዋት', '፮.፪ በመጽሐፍ ቅዱስ የተጠቀሱ ዕፅዋትና ምሳሌነታቸው', 198, '2026-09-15 22:24:26'),
(199, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓተ ቅዳሴ', '፩.፩ የቅዳሴ ምንነትና አመጣጥ', 199, '2026-09-15 22:24:26'),
(200, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓተ ቅዳሴ', '፩.፪ የቅዳሴ ክፍሎች (ግብረ ዲቁና፣ ግብረ ቅስና)', 200, '2026-09-15 22:24:26'),
(201, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓተ ቅዳሴ', '፩.፫ ዐሥራ አራቱ ቅዳሴያት', 201, '2026-09-15 22:24:26'),
(202, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓተ ቅዳሴ', '፩.፬ የሥርዓተ ቅዳሴ ዝግጅትና ፍጻሜ', 202, '2026-09-15 22:24:26'),
(203, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓተ ቅዳሴ', '፩.፭ በቅዳሴ ጊዜ የሚደረጉ ሥርዓቶች', 203, '2026-09-15 22:24:26'),
(204, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የቍርባን ሥርዓት', '፪.፩ የቅዱስ ቍርባን ትርጉምና ክብር', 204, '2026-09-15 22:24:26'),
(205, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የቍርባን ሥርዓት', '፪.፪ ለቍርባን የሚደረግ ቅድመና ድኅረ ዝግጅት', 205, '2026-09-15 22:24:26'),
(206, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የቍርባን ሥርዓት', '፪.፫ የቍርባን ጥቅም ለክርስቲያን', 206, '2026-09-15 22:24:26'),
(207, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ሥርዓተ በዓላትና ምጽዋት', '፫.፩ የቤተ ክርስቲያን ዓበይት በዓላት ሥርዓት', 207, '2026-09-15 22:24:27'),
(208, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ሥርዓተ በዓላትና ምጽዋት', '፫.፪ የንግሥ በዓላትና ታቦት ማክበር', 208, '2026-09-15 22:24:27'),
(209, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ሥርዓተ በዓላትና ምጽዋት', '፫.፫ የምጽዋት ሥርዓትና አሰጣጥ', 209, '2026-09-15 22:24:27'),
(210, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ሥርዓተ በዓላትና ምጽዋት', '፫.፬ አሥራትና በኩራት', 210, '2026-09-15 22:24:27'),
(211, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ሥርዓተ መዝሙር ወጸሎት', '፬.፩ የቤተ ክርስቲያን መዝሙር ሥርዓት', 211, '2026-09-15 22:24:28'),
(212, 4, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ሥርዓተ መዝሙር ወጸሎት', '፬.፪ የጸሎት ሥርዓትና አቋቋም', 212, '2026-09-15 22:24:28'),
(213, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ክርስቲያን እና ክርስትና', '፩.፩ ክርስቲያን የመሆን ትርጉም', 213, '2026-09-15 22:24:28'),
(214, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ክርስቲያን እና ክርስትና', '፩.፪ የክርስትና ሕይወት መገለጫዎች', 214, '2026-09-15 22:24:28'),
(215, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ የመንፈስ ቅዱስ ፍሬዎች እና የሥጋ ፍሬዎች', '፪.፩ ዘጠኙ የመንፈስ ቅዱስ ፍሬዎች', 215, '2026-09-15 22:24:28'),
(216, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ የመንፈስ ቅዱስ ፍሬዎች እና የሥጋ ፍሬዎች', '፪.፪ የሥጋ ሥራዎችና መዘዞቻቸው', 216, '2026-09-15 22:24:28'),
(217, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ የመንፈስ ቅዱስ ፍሬዎች እና የሥጋ ፍሬዎች', '፪.፫ ሥጋን በመንፈስ ማሸነፍ', 217, '2026-09-15 22:24:28'),
(218, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ስግደት', '፫.፩ የስግደት ምንነትና ዓይነቶች (የአምልኮ፣ የጸጋ)', 218, '2026-09-15 22:24:29'),
(219, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ስግደት', '፫.፪ ለሥላሴ፣ ለእመቤታችን፣ ለመስቀልና ለቅዱሳን የሚደረግ ስግደት', 219, '2026-09-15 22:24:29'),
(220, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ስግደት', '፫.፫ የስግደት ጥቅምና ሥርዓት', 220, '2026-09-15 22:24:30'),
(221, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ዘላለማዊ ሕይወት', '፬.፩ የዘላለም ሕይወት ምንነት', 221, '2026-09-15 22:24:30'),
(222, 4, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ዘላለማዊ ሕይወት', '፬.፪ ወደ ዘላለም ሕይወት የሚያደርስ ጎዳና', 222, '2026-09-15 22:24:30'),
(223, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ኢትዮጵያና አምልኮተ እግዚአብሔር በብሉይ ኪዳን', '፩.፩ ኢትዮጵያ በብሉይ ኪዳን', 223, '2026-09-15 22:24:30'),
(224, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ኢትዮጵያና አምልኮተ እግዚአብሔር በብሉይ ኪዳን', '፩.፪ ንግሥተ ሳባና ንጉሥ ሰሎሞን', 224, '2026-09-15 22:24:30'),
(225, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ኢትዮጵያና አምልኮተ እግዚአብሔር በብሉይ ኪዳን', '፩.፫ ቀዳማዊ ምኒልክና ታቦተ ጽዮን ወደ ኢትዮጵያ መምጣት', 225, '2026-09-15 22:24:31'),
(226, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ኢትዮጵያና አምልኮተ እግዚአብሔር በብሉይ ኪዳን', '፩.፬ የኦሪት ሥርዓት በኢትዮጵያ', 226, '2026-09-15 22:24:31'),
(227, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ኢትዮጵያና አምልኮተ እግዚአብሔር በብሉይ ኪዳን', '፩.፭ የብሉይ ኪዳን እምነት በኢትዮጵያ መስፋፋት', 227, '2026-09-15 22:24:31'),
(228, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ የብሉይ ኪዳን መቅደሶች በኢትዮጵያ', '፪.፩ አክሱም ጽዮን', 228, '2026-09-15 22:24:31'),
(229, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ የብሉይ ኪዳን መቅደሶች በኢትዮጵያ', '፪.፪ ጣና ቂርቆስ', 229, '2026-09-15 22:24:32'),
(230, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ የብሉይ ኪዳን መቅደሶች በኢትዮጵያ', '፪.፫ መርጡለ ማርያም', 230, '2026-09-15 22:24:32'),
(231, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ የብሉይ ኪዳን መቅደሶች በኢትዮጵያ', '፪.፬ ተድባበ ማርያም', 231, '2026-09-15 22:24:32'),
(232, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ የብሉይ ኪዳን መቅደሶች በኢትዮጵያ', '፪.፭ ሌሎች ጥንታውያን መካናት', 232, '2026-09-15 22:24:32'),
(233, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፩ ክርስትና ወደ ኢትዮጵያ መግባት', 233, '2026-09-15 22:24:32'),
(234, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፪ የኢትዮጵያዊው ጃንደረባ ጥምቀትና ስብከት', 234, '2026-09-15 22:24:33'),
(235, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፫ ቅዱስ ፍሬምናጦስ (አባ ሰላማ)', 235, '2026-09-15 22:24:33'),
(236, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፬ ነገሥታቱ አብርሃና አጽብሐ', 236, '2026-09-15 22:24:33'),
(237, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፭ ዘጠኙ ቅዱሳንና ገዳማዊ ሕይወት', 237, '2026-09-15 22:24:33'),
(238, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፮ ቅዱስ ያሬድና የዜማ ዕድገት', 238, '2026-09-15 22:24:33'),
(239, 4, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ኢትዮጵያና አምልኮተ እግዚአብሔር በሐዲስ ኪዳን', '፫.፯ የኢትዮጵያ ቤተ ክርስቲያን ጥንካሬና ፈተናዎች', 239, '2026-09-15 22:24:34'),
(240, 4, 'ግእዝ', 'ምዕራፍ ፩ መራሕያን', '፩.፩ አሥሩ መራሕያንና አጠቃቀማቸው', 240, '2026-09-15 22:24:34'),
(241, 4, 'ግእዝ', 'ምዕራፍ ፩ መራሕያን', '፩.፪ መራሕያን በነጠላና በብዙ', 241, '2026-09-15 22:24:34'),
(242, 4, 'ግእዝ', 'ምዕራፍ ፪ የንባብ ስልቶች', '፪.፩ የግእዝ ንባብ ስልቶች (ግእዝ፣ ውርደ ንባብ፣ ቁም ንባብ)', 242, '2026-09-15 22:24:34'),
(243, 4, 'ግእዝ', 'ምዕራፍ ፪ የንባብ ስልቶች', '፪.፪ የቃላት አነባበብና የድምፅ ቃና', 243, '2026-09-15 22:24:34'),
(244, 4, 'ግእዝ', 'ምዕራፍ ሦስት መጽሐፍ ቅዱሳዊ ታሪኮች', '፫.፩ የፍጥረት ታሪክ በግእዝ', 244, '2026-09-15 22:24:34'),
(245, 4, 'ግእዝ', 'ምዕራፍ ሦስት መጽሐፍ ቅዱሳዊ ታሪኮች', '፫.፪ የኖኅ ታሪክ በግእዝ', 245, '2026-09-15 22:24:34'),
(246, 4, 'ግእዝ', 'ምዕራፍ ሦስት መጽሐፍ ቅዱሳዊ ታሪኮች', '፫.፫ የጌታችን ልደት ታሪክ በግእዝ', 246, '2026-09-15 22:24:34'),
(247, 4, 'ግእዝ', 'ምዕራፍ ሦስት መጽሐፍ ቅዱሳዊ ታሪኮች', '፫.፬ ቀላል ንባባት ከተርጓሚ ጋር', 247, '2026-09-15 22:24:35'),
(248, 4, 'ግእዝ', 'ምዕራፍ አራት የንባብ አካላት', '፬.፩ የንባብ ምልክቶችና ሥርዓተ ነጥቦች በግእዝ', 248, '2026-09-15 22:24:35'),
(249, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የትምህርተ ሃይማኖት መመሪያ', '፩.፩ የሃይማኖት ምንነትና መመሪያ', 249, '2026-09-15 22:24:35'),
(250, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የትምህርተ ሃይማኖት መመሪያ', '፩.፪ የእምነት አስፈላጊነት', 250, '2026-09-15 22:24:35'),
(251, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ሥነ ፍጥረት', '፪.፩ ሥነ ፍጥረት መግቢያ', 251, '2026-09-15 22:24:35'),
(252, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ሥነ ፍጥረት', '፪.፪ እግዚአብሔር ፈጣሪ መሆኑ', 252, '2026-09-15 22:24:35'),
(253, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ሥነ ፍጥረት', '፪.፫ ፍጥረታት የተፈጠሩበት ዓላማ', 253, '2026-09-15 22:24:35'),
(254, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ሥነ ፍጥረት', '፪.፬ ባለ አእምሮና ግዑዛን ፍጥረታት', 254, '2026-09-15 22:24:35'),
(255, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ሥነ ፍጥረት', '፪.፭ የፍጥረት አከፋፈል', 255, '2026-09-15 22:24:35'),
(256, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ከእሑድ እስከ ሠሉስ ያሉ ፍጥረት', '፫.፩ የእሑድ ፍጥረታት (ሰባቱ ፍጥረታት)', 256, '2026-09-15 22:24:35'),
(257, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ከእሑድ እስከ ሠሉስ ያሉ ፍጥረት', '፫.፪ የሰኞ ፍጥረታት (ጠፈር)', 257, '2026-09-15 22:24:35'),
(258, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ ከእሑድ እስከ ሠሉስ ያሉ ፍጥረት', '፫.፫ የማክሰኞ ፍጥረታት (ዕፅዋትና አዝርዕት)', 258, '2026-09-15 22:24:35'),
(259, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ከረቡዕ እስከ ዐርብ ያሉ ፍጥረት', '፬.፩ የረቡዕ ፍጥረታት (ፀሐይ፣ ጨረቃ፣ ከዋክብት)', 259, '2026-09-15 22:24:35'),
(260, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ከረቡዕ እስከ ዐርብ ያሉ ፍጥረት', '፬.፪ የሐሙስ ፍጥረታት (የውኃና የሰማይ እንስሳት)', 260, '2026-09-15 22:24:36'),
(261, 5, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ ከረቡዕ እስከ ዐርብ ያሉ ፍጥረት', '፬.፫ የዐርብ ፍጥረታት (የምድር አራዊት፣ እንስሳትና የሰው ልጅ)', 261, '2026-09-15 22:24:36'),
(262, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የቅዱሳት መጻሕፍት ዝርዝር አከፋፈል', '፩.፩ የብሉይ ኪዳን መጻሕፍት አከፋፈል', 262, '2026-09-15 22:24:36'),
(263, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የቅዱሳት መጻሕፍት ዝርዝር አከፋፈል', '፩.፪ የሐዲስ ኪዳን መጻሕፍት አከፋፈል', 263, '2026-09-15 22:24:36'),
(264, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የብሉይ ኪዳን የሕግ እና የታሪክ መጻሕፍት', '፪.፩ ኦሪት (አምስቱ የሕግ መጻሕፍት)', 264, '2026-09-15 22:24:36'),
(265, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የብሉይ ኪዳን የሕግ እና የታሪክ መጻሕፍት', '፪.፪ የታሪክ መጻሕፍት (ኢያሱ፣ መሳፍንት፣ ሩት፣ ነገሥት ወዘተ)', 265, '2026-09-15 22:24:36'),
(266, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የመዝሙርና የጥበብ መጻሕፍት', '፫.፩ መዝሙረ ዳዊት', 266, '2026-09-15 22:24:36'),
(267, 5, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፫ የመዝሙርና የጥበብ መጻሕፍት', '፫.፪ የመጽሐፈ ምሳሌና መክብብ ጥናት', 267, '2026-09-15 22:24:36'),
(268, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓት', '፩.፩ የሥርዓት ምንነትና አስፈላጊነት', 268, '2026-09-15 22:24:36'),
(269, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓት', '፩.፪ የሥርዓተ ቤተ ክርስቲያን መሠረቶች', 269, '2026-09-15 22:24:36'),
(270, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓት', '፩.፫ ሕግ፣ ሥርዓትና ትውፊት', 270, '2026-09-15 22:24:36'),
(271, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓት', '፩.፬ ሥርዓትን የመጠበቅ ጥቅም', 271, '2026-09-15 22:24:36'),
(272, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ሥርዓት', '፩.፭ ሥርዓተ አምልኮ', 272, '2026-09-15 22:24:37'),
(273, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የአንጻጸ ቤተ ክርስቲያን ሥርዓት', '፪.፩ የቤተ መቅደስ ክፍሎችና ምሥጢራቸው', 273, '2026-09-15 22:24:37'),
(274, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የአንጻጸ ቤተ ክርስቲያን ሥርዓት', '፪.፪ ቅኔ ማኅሌት', 274, '2026-09-15 22:24:37'),
(275, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የአንጻጸ ቤተ ክርስቲያን ሥርዓት', '፪.፫ ቅድስት', 275, '2026-09-15 22:24:37'),
(276, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የአንጻጸ ቤተ ክርስቲያን ሥርዓት', '፪.፬ መቅደስ', 276, '2026-09-15 22:24:37'),
(277, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ የአንጻጸ ቤተ ክርስቲያን ሥርዓት', '፪.፭ የቤተ መቅደስ ንዋያት ሥርዓት', 277, '2026-09-15 22:24:37'),
(278, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ስለ ሥርዓተ የመጽሐፍ ቅዱስ ትምህርት', '፫.፩ በመጽሐፍ ቅዱስ የታዘዙ ሥርዓቶች', 278, '2026-09-15 22:24:37'),
(279, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ስለ ሥርዓተ የመጽሐፍ ቅዱስ ትምህርት', '፫.፪ የጸሎትና የስግደት ሥርዓት', 279, '2026-09-15 22:24:37'),
(280, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ስለ ሥርዓተ የመጽሐፍ ቅዱስ ትምህርት', '፫.፫ የበዓላት አከባበር ሥርዓት', 280, '2026-09-15 22:24:37'),
(281, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፫ ስለ ሥርዓተ የመጽሐፍ ቅዱስ ትምህርት', '፫.፬ የጾም ሥርዓት', 281, '2026-09-15 22:24:38'),
(282, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ሥርዓተ ቤተ ክርስቲያንን ለመጠበቅ ምን እናድርግ?', '፬.፩ ሥርዓተ ቤተ ክርስቲያንን ማወቅና መረዳት', 282, '2026-09-15 22:24:38'),
(283, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ሥርዓተ ቤተ ክርስቲያንን ለመጠበቅ ምን እናድርግ?', '፬.፪ ሥርዓትን በተግባር መኖር', 283, '2026-09-15 22:24:38'),
(284, 5, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፬ ሥርዓተ ቤተ ክርስቲያንን ለመጠበቅ ምን እናድርግ?', '፬.፫ ሥርዓተ ቤተ ክርስቲያንን ለትውልድ ማስተላለፍ', 284, '2026-09-15 22:24:38'),
(285, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ኦርቶዶክሳዊ መንፈሳዊ ሰው', '፩.፩ መንፈሳዊ ሰው ማን ነው?', 285, '2026-09-15 22:24:38'),
(286, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ኦርቶዶክሳዊ መንፈሳዊ ሰው', '፩.፪ የመንፈሳዊ ሰው ባሕርያት', 286, '2026-09-15 22:24:38'),
(287, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ኦርቶዶክሳዊ መንፈሳዊ ሰው', '፩.፫ በመንፈሳዊ ሕይወት ማደግ', 287, '2026-09-15 22:24:38'),
(288, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ሥጋዊ ሰው', '፪.፩ የሥጋዊ ሰው አስተሳሰብና አካሄድ', 288, '2026-09-15 22:24:38'),
(289, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ሥጋዊ ሰው', '፪.፪ ከሥጋዊ አስተሳሰብ መራቅ', 289, '2026-09-15 22:24:38'),
(290, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ሥጋዊ ሰው', '፪.፫ ሥጋዊ ፍላጎትን በመንፈሳዊ ኃይል መግዛት', 290, '2026-09-15 22:24:38'),
(291, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፪ ሥጋዊ ሰው', '፪.፬ የዓለም ፈተናዎችን ማሸነፍ', 291, '2026-09-15 22:24:38'),
(292, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ባለማወቅነት (ሥጋዊነት)', '፫.፩ ባለማወቅ የሚመጡ ስህተቶችና ጉዳቶች', 292, '2026-09-15 22:24:38'),
(293, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፫ ባለማወቅነት (ሥጋዊነት)', '፫.፪ እውነትንና እውቀትን መሻት', 293, '2026-09-15 22:24:39'),
(294, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጥበብ', '፬.፩ የእግዚአብሔር ጥበብ እና የዓለም ጥበብ', 294, '2026-09-15 22:24:39'),
(295, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጥበብ', '፬.፪ እግዚአብሔርን መፍራት የጥበብ መጀመሪያ', 295, '2026-09-15 22:24:39'),
(296, 5, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፬ ጥበብ', '፬.፫ መንፈሳዊ ጥበብን ገንዘብ ማድረግ', 296, '2026-09-15 22:24:39'),
(297, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የዘመነ ቀመር', '፩.፩ የዘመን አቆጣጠር መሠረቶች', 297, '2026-09-15 22:24:39'),
(298, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የዘመነ ቀመር', '፩.፪ አራቱ ወንጌላውያን ዘመናት (ማቴዎስ፣ ማርቆስ፣ ሉቃስ፣ ዮሐንስ)', 298, '2026-09-15 22:24:39'),
(299, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ የዘመነ ቀመር', '፩.፫ የዓመተ ምሕረትና ዓመተ ዓለም አቆጣጠር', 299, '2026-09-15 22:24:39'),
(300, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ዝክረ በዓላት ከመስከረም እስከ ኅዳር', '፪.፩ ወርኀ መስከረም (ርእሰ ዐውደ ዓመት፣ መስቀል)', 300, '2026-09-15 22:24:39'),
(301, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ዝክረ በዓላት ከመስከረም እስከ ኅዳር', '፪.፪ ወርኀ ጥቅምት (ቅዱስ ቂርቆስ፣ ጻድቁ አቡነ ገብረ መንፈስ ቅዱስ)', 301, '2026-09-15 22:24:39'),
(302, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፪ ዝክረ በዓላት ከመስከረም እስከ ኅዳር', '፪.፫ ወርኀ ኅዳር (ኅዳር ጽዮን፣ ቅዱስ ሚካኤል፣ ቅዱስ ጊዮርጊስ)', 302, '2026-09-15 22:24:39'),
(303, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ዝክረ በዓላት ከታኅሣሥ እስከ የካቲት', '፫.፩ ወርኀ ታኅሣሥ (በአታ ለማርያም፣ ሠለስቱ ደቂቅ፣ ልደት፣ ቅዱስ ገብርኤል)', 303, '2026-09-15 22:24:39'),
(304, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ዝክረ በዓላት ከታኅሣሥ እስከ የካቲት', '፫.፪ ወርኀ ጥር (ቅዱስ እስጢፋኖስ፣ ግዝረት፣ ጥምቀት፣ ቃና ዘገሊላ፣ ኪዳነ ምሕረት)', 304, '2026-09-15 22:24:39'),
(305, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፫ ዝክረ በዓላት ከታኅሣሥ እስከ የካቲት', '፫.፫ ወርኀ የካቲት (ጾመ ነነዌ፣ ቅዱስ ቂርቆስ)', 305, '2026-09-15 22:24:39'),
(306, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፬ ዝክረ በዓላት ከመጋቢት እስከ ግንቦት', '፬.፩ ወርኀ መጋቢት (ጌታችን ስቅለት እና የታዩ ተአምራት፣ ትንሣኤ)', 306, '2026-09-15 22:24:39'),
(307, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፬ ዝክረ በዓላት ከመጋቢት እስከ ግንቦት', '፬.፪ ወርኀ ሚያዝያ (ዕርገት፣ ጰራቅሊጦስ)', 307, '2026-09-15 22:24:40'),
(308, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፬ ዝክረ በዓላት ከመጋቢት እስከ ግንቦት', '፬.፫ ወርኀ ግንቦት (ልደታ ለማርያም፣ ቅዱስ ያሬድ፣ ቅዱስ ዮሐንስ አፈወርቅ)', 308, '2026-09-15 22:24:40'),
(309, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፭ ዝክረ በዓላት ከሰኔ እስከ ጳጉሜን', '፭.፩ ወርኀ ሰኔ (ሕንፀተ ቤተ ክርስቲያን)', 309, '2026-09-15 22:24:40'),
(310, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፭ ዝክረ በዓላት ከሰኔ እስከ ጳጉሜን', '፭.፪ ወርኀ ሐምሌ (ቅዱስ ቂርቆስ፣ ጴጥሮስ ወጳውሎስ፣ ቅድስት ሥላሴ)', 310, '2026-09-15 22:24:40'),
(311, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፭ ዝክረ በዓላት ከሰኔ እስከ ጳጉሜን', '፭.፫ ወርኀ ነሐሴ (በዓለ ቅዱስ ገብርኤል፣ ደብረ ታቦር፣ ትንሣኤ ወዕረገታ ለእግዝእትነ ማርያም፣ አቡነ ተክለ ሃይማኖት)', 311, '2026-09-15 22:24:40'),
(312, 5, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፭ ዝክረ በዓላት ከሰኔ እስከ ጳጉሜን', '፭.፬ ወርኀ ጳጉሜን (ቅዱስ ሩፋኤል)', 312, '2026-09-15 22:24:40'),
(313, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፩ የአካነ፡ ስልተ ወሠርዐተ ንባብ ዘልሳነ ግእዝ', 313, '2026-09-15 22:24:40'),
(314, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፪ ትምህርት ዘቃል (የቃል ትምህርት)', 314, '2026-09-15 22:24:40'),
(315, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፫ እምአዕኩኩ ገጽ እስከ እስኩተከ (በግእዝ ወውርደ ቃቁም ንባብ)', 315, '2026-09-15 22:24:40'),
(316, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፬ ጸሎተ ዘወትር', 316, '2026-09-15 22:24:40'),
(317, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፭ ኲሉክሙ', 317, '2026-09-15 22:24:40'),
(318, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፮ አቡነ ዘበሰማያት (በግእዝ ወውርደ ቃቁም ንባብ)', 318, '2026-09-15 22:24:40'),
(319, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፯ በሰላመ ቅዱስ ገብርኤል መልአክ', 319, '2026-09-15 22:24:40'),
(320, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፰ ጸሎተ ሃይማኖት', 320, '2026-09-15 22:24:40'),
(321, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፱ ቅዱስ ቅዱስ ቅዱስ', 321, '2026-09-15 22:24:40'),
(322, 5, 'ግእዝ', 'ምዕራፍ ፩ ጸሎት ዘዘወትር', '፩.፲ ስብሐት', 322, '2026-09-15 22:24:41'),
(323, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፩ ሰላም ለኪ (በግእዝ ወውርደ ቃቁም ንባብ ወበዜማ)', 323, '2026-09-15 22:24:41'),
(324, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፪ ጸሎተ እግዝእትነ ማርያም', 324, '2026-09-15 22:24:41'),
(325, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፫ የወዳሴ ማርያም ምንነት እና ምስጢር', 325, '2026-09-15 22:24:41'),
(326, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፬ ዜማ ማለት ምን ማለት ነው?', 326, '2026-09-15 22:24:41'),
(327, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፭ ውዳሴ ማርያም ዘሰኑይ በግእዝ ወውርደ ቃቁም ንባብ ትርጉም', 327, '2026-09-15 22:24:41'),
(328, 5, 'ግእዝ', 'ምዕራፍ ፪ እምሰላም ለኪ እስከ ውዳሴ ማርያም ዘሰኑይ', '፪.፮ የአነ ውዳሴ ማርያም ነጠላ ትርጉም', 328, '2026-09-15 22:24:41'),
(329, 5, 'ግእዝ', 'ምዕራፍ ፫ ውዳሴ ማርያም ዘሠሉስ', '፫.፩ ውዳሴ ማርያም ዘሠሉስ (በግእዝ ወውርደ ቃቁም ንባብ ወበዜማ)', 329, '2026-09-15 22:24:41'),
(330, 5, 'ግእዝ', 'ምዕራፍ ፫ ውዳሴ ማርያም ዘሠሉስ', '፫.፪ የማክሰኞ ውዳሴ ማርያም ነጠላ ትርጉም', 330, '2026-09-15 22:24:41'),
(331, 5, 'ግእዝ', 'ምዕራፍ ፬ ውዳሴ ማርያም ዘረቡዕ', '፬.፩ ውዳሴ ማርያም ዘረቡዕ (በግእዝ ወውርደ ቃቁም ንባብ ወበዜማ)', 331, '2026-09-15 22:24:41'),
(332, 5, 'ግእዝ', 'ምዕራፍ ፬ ውዳሴ ማርያም ዘረቡዕ', '፬.፪ የረቡዕ ውዳሴ ማርያም ነጠላ ትርጉም', 332, '2026-09-15 22:24:41'),
(333, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የነገረ ቅዱሳን መግቢያ', '፩.፩ የነገረ ቅዱሳን ምንነት', 333, '2026-09-15 22:24:41'),
(334, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የነገረ ቅዱሳን መግቢያ', '፩.፪ የቅድስና ዓይነቶች', 334, '2026-09-15 22:24:41'),
(335, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የነገረ ቅዱሳን መግቢያ', '፩.፫ የቅዱሳን ሰዎች አሰያየም', 335, '2026-09-15 22:24:41'),
(336, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፩ የነገረ ቅዱሳን መግቢያ', '፩.፬ የነገረ ቅዱሳን ትምህርት አስፈላጊነት', 336, '2026-09-15 22:24:42'),
(337, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ቅዱሳን እነማን ናቸው?', '፪.፩ ቅዱሳን ሰዎች', 337, '2026-09-15 22:24:42'),
(338, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ቅዱሳን እነማን ናቸው?', '፪.፪ ቅዱሳን መላእክት', 338, '2026-09-15 22:24:42'),
(339, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ቅዱሳን እነማን ናቸው?', '፪.፫ ቅዱሳት መጻሕፍት', 339, '2026-09-15 22:24:42'),
(340, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ቅዱሳን እነማን ናቸው?', '፪.፬ ቅዱሳት ሥዕላት', 340, '2026-09-15 22:24:42'),
(341, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፪ ቅዱሳን እነማን ናቸው?', '፪.፭ ንዋያተ ቅድሳት', 341, '2026-09-15 22:24:42'),
(342, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ የቅዱሳን ሰዎች መመረቂያ (ማዕረግ)', '፫.፩ የቅድስና መዓርግ ምንነት', 342, '2026-09-15 22:24:42'),
(343, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ የቅዱሳን ሰዎች መመረቂያ (ማዕረግ)', '፫.፪ ንጽሐ ሥጋ', 343, '2026-09-15 22:24:42'),
(344, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ የቅዱሳን ሰዎች መመረቂያ (ማዕረግ)', '፫.፫ ንጽሐ ነፍስ', 344, '2026-09-15 22:24:42'),
(345, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፫ የቅዱሳን ሰዎች መመረቂያ (ማዕረግ)', '፫.፬ ንጽሐ ልቡና', 345, '2026-09-15 22:24:42'),
(346, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ የቅዱሳን ምልጃ', '፬.፩ የምልጃ ምንነት', 346, '2026-09-15 22:24:42'),
(347, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ የቅዱሳን ምልጃ', '፬.፪ የቅዱሳን ምልጃ በአፀደ ሥጋ', 347, '2026-09-15 22:24:42'),
(348, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፬ የቅዱሳን ምልጃ', '፬.፫ የቅዱሳን አማላጅነት በዐጸደ ነፍስ (ከሞት በኋላ)', 348, '2026-09-15 22:24:43'),
(349, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፭ የቅዱሳን ሥልጣንና ክብር', '፭.፩ የቅዱሳን ሥልጣን', 349, '2026-09-15 22:24:43'),
(350, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፭ የቅዱሳን ሥልጣንና ክብር', '፭.፪ የቅዱሳን ክብር', 350, '2026-09-15 22:24:43'),
(351, 6, 'መሠረተ ሃይማኖት', 'ምዕራፍ ፭ የቅዱሳን ሥልጣንና ክብር', '፭.፫ የቅዱሳን ቃል ኪዳን', 351, '2026-09-15 22:24:43'),
(352, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የሐዲስ ኪዳን የወንጌል ፣ የታሪክ እና የራእይ መጻሕፍት', '፩.፩ ወንጌላት (አራቱ ወንጌላት)', 352, '2026-09-15 22:24:43'),
(353, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የሐዲስ ኪዳን የወንጌል ፣ የታሪክ እና የራእይ መጻሕፍት', '፩.፪ የሐዋርያት ሥራ', 353, '2026-09-15 22:24:43'),
(354, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፩ የሐዲስ ኪዳን የወንጌል ፣ የታሪክ እና የራእይ መጻሕፍት', '፩.፫ የዮሐንስ ራእይ', 354, '2026-09-15 22:24:43'),
(355, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፩ የሐዋርያው የቅዱስ ጳውሎስ መልእክታት', 355, '2026-09-15 22:24:43'),
(356, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፪ ዓለም አቀፍ መልእክታት', 356, '2026-09-15 22:24:43'),
(357, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፫ የሐዋርያው የቅዱስ ጴጥሮስ መልእክታት', 357, '2026-09-15 22:24:43'),
(358, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፬ የሐዋርያው የቅዱስ ዮሐንስ መልእክታት', 358, '2026-09-15 22:24:43'),
(359, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፭ የያዕቆብ መልእክት', 359, '2026-09-15 22:24:44'),
(360, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ፪ የሐዲስ ኪዳን የመልእክት መጻሕፍት', '፪.፮ የይሁዳ መልእክት', 360, '2026-09-15 22:24:44'),
(361, 6, 'የቅዱሳት መጻሕፍት ጥናት', 'ምዕራፍ ሦስት የሐዲስ ኪዳን የሥርዓት መጻሕፍት', '፫.፩ ሥርዓተ ቤተ ክርስቲያን በሐዲስ ኪዳን መጻሕፍት', 361, '2026-09-15 22:24:44'),
(362, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ንዋየ ቅድሳት', '፩.፩ የንዋየ ቅድሳት ምንነትና አገልግሎት', 362, '2026-09-15 22:24:44'),
(363, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፩ ንዋየ ቅድሳት', '፩.፪ ታቦት፣ መንበር፣ ጽዋ፣ ጻሕል፣ ዕርፈ መስቀል', 363, '2026-09-15 22:24:44'),
(364, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ ልብሰ ተክህኖ', '፪.፩ ልብሰ ተክህኖ ምንነት', 364, '2026-09-15 22:24:44'),
(365, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ፪ ልብሰ ተክህኖ', '፪.፪ የአልባሳት ዓይነቶችና መንፈሳዊ ምሥጢራቸው', 365, '2026-09-15 22:24:44'),
(366, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ሦስት ለመሥዋዕት የሚያገለግሉ ንዋያተ ቅድሳት', '፫.፩ ለመሥዋዕት እግዚአብሔር የሚያገለግሉ የመጠሪያ ዓይነቶች', 366, '2026-09-15 22:24:44'),
(367, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ሦስት ለመሥዋዕት የሚያገለግሉ ንዋያተ ቅድሳት', '፫.፪ ለመሥዋዕት የሚያገለግሉ ሥርዓቶች', 367, '2026-09-15 22:24:44'),
(368, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ አራት የመዘምራን መገልገያ ንዋያተ ቅዱሳት', '፬.፩ ጸናጽል፣ ከበሮ፣ መቋሚያ', 368, '2026-09-15 22:24:44'),
(369, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ አራት የመዘምራን መገልገያ ንዋያተ ቅዱሳት', '፬.፪ የመዘምራን አለባበስና ሥርዓት', 369, '2026-09-15 22:24:44'),
(370, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ አምስት ለሥርዓተ ማኅሌት የሚያገለግሉ ንዋያት', '፭.፩ ሥዕለ ሃይማኖት፣ መቋሚያ', 370, '2026-09-15 22:24:44'),
(371, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ አምስት ለሥርዓተ ማኅሌት የሚያገለግሉ ንዋያት', '፭.፪ ለዝማሬ የሚያገለግሉ ንዋያት', 371, '2026-09-15 22:24:44'),
(372, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ስድስት ንዋያተ ቅድሳትን እንዴት እንጠብቃቸው?', '፮.፩ የንዋያተ ቅድሳት አያያዝና ክብር', 372, '2026-09-15 22:24:44'),
(373, 6, 'ሥርዓተ ቤተ ክርስቲያን', 'ምዕራፍ ስድስት ንዋያተ ቅድሳትን እንዴት እንጠብቃቸው?', '፮.፪ የቤተ መቅደስ ንጽሕና አጠባበቅ', 373, '2026-09-15 22:24:44'),
(374, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ጾታ', '፩.፩ የጾታ ትርጉምና የእግዚአብሔር ስጦታ መሆኑ', 374, '2026-09-15 22:24:45'),
(375, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ፩ ጾታ', '፩.፪ የሴቶች ክብር በቤተክርስቲያን አስተምህሮ', 375, '2026-09-15 22:24:45'),
(376, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሁለት ክርስቲያናዊ የሕይወት መንገድ', '፪.፩ ድንግልና ምንነት እና ዓይነቶች', 376, '2026-09-15 22:24:45'),
(377, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሁለት ክርስቲያናዊ የሕይወት መንገድ', '፪.፪ ክርስቲያናዊ አኗኗር በድንግልና', 377, '2026-09-15 22:24:45'),
(378, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሁለት ክርስቲያናዊ የሕይወት መንገድ', '፪.፫ የድንግልና ሕይወት ጥቅምና ክብር', 378, '2026-09-15 22:24:45'),
(379, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሁለት ክርስቲያናዊ የሕይወት መንገድ', '፪.፬ ድንግልናን የሚያሳጡ ነገሮችና ጥንቃቄ', 379, '2026-09-15 22:24:45'),
(380, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሦስት የምንኵስና፣ ብሕትውና እና ገዳማዊ ሕይወት', '፫.፩ የምንኵስና ምንነት እና አጀማመር', 380, '2026-09-15 22:24:45'),
(381, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሦስት የምንኵስና፣ ብሕትውና እና ገዳማዊ ሕይወት', '፫.፪ የምንኵስና ዓላማ እና ገዳማዊ ሕይወት', 381, '2026-09-15 22:24:45'),
(382, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ ሦስት የምንኵስና፣ ብሕትውና እና ገዳማዊ ሕይወት', '፫.፫ የብሕትውና ሕይወት', 382, '2026-09-15 22:24:45'),
(383, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ አራት ክርስቲያናዊ አኗኗር በጋብቻ', '፬.፩ የመተጫጨት ትርጉምና ዓላማ', 383, '2026-09-15 22:24:45'),
(384, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ አራት ክርስቲያናዊ አኗኗር በጋብቻ', '፬.፪ የአጮኝነት ጊዜ ጥንቃቄ', 384, '2026-09-15 22:24:45'),
(385, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ አራት ክርስቲያናዊ አኗኗር በጋብቻ', '፬.፫ የጋብቻ ምንነትና ቅድስና', 385, '2026-09-15 22:24:45'),
(386, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ አራት ክርስቲያናዊ አኗኗር በጋብቻ', '፬.፬ የጋብቻ ዓላማዎች', 386, '2026-09-15 22:24:45'),
(387, 6, 'ክርስቲያናዊ ሥነ ምግባር', 'ምዕራፍ አራት ክርስቲያናዊ አኗኗር በጋብቻ', '፬.፭ የጋብቻ መስፈርቶችና ዝግጅት', 387, '2026-09-15 22:24:45'),
(388, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ቤተ ክርስቲያን', '፩.፩ የቤተ ክርስቲያን ዘመነ ሐዋርያት', 388, '2026-09-15 22:24:45'),
(389, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ፩ ቤተ ክርስቲያን', '፩.፪ የቤተ ክርስቲያን ባሕርያት (አንዲት፣ ቅድስት፣ ኵላዊት፣ ሐዋርያዊት)', 389, '2026-09-15 22:24:45'),
(390, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ሁለት የቤተ ክርስቲያን ስያሜ እና ዕድገት', '፪.፩ የቤተ ክርስቲያን ስያሜ በሀገረ ስብከት', 390, '2026-09-15 22:24:45'),
(391, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ሁለት የቤተ ክርስቲያን ስያሜ እና ዕድገት', '፪.፪ የቤተ ክርስቲያን ዕድገትና መስፋፋት', 391, '2026-09-15 22:24:45'),
(392, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ሦስት የቤተ ክርስቲያን ምሳሌዎች', '፫.፩ የቤተ ክርስቲያን ምሳሌዎች በብሉይ ኪዳን', 392, '2026-09-15 22:24:45'),
(393, 6, 'የቤተ ክርስቲያን ታሪክ', 'ምዕራፍ ሦስት የቤተ ክርስቲያን ምሳሌዎች', '፫.፪ የቤተ ክርስቲያን ምሳሌዎች በሐዲስ ኪዳን', 393, '2026-09-15 22:24:46'),
(394, 6, 'ግእዝ', 'ምዕራፍ አሐዱ ውዳሴ ማርያም ዘዕለተ ሐሙስ', '፩.፩ የሐሙስ ውዳሴ ማርያም ንባብ', 394, '2026-09-15 22:24:46'),
(395, 6, 'ግእዝ', 'ምዕራፍ አሐዱ ውዳሴ ማርያም ዘዕለተ ሐሙስ', '፩.፪ ውዳሴ ማርያም ዘሐሙስ', 395, '2026-09-15 22:24:46'),
(396, 6, 'ግእዝ', 'ምዕራፍ አሐዱ ውዳሴ ማርያም ዘዕለተ ሐሙስ', '፩.፫ የሐሙስ ውዳሴ ማርያም ማስተዛዘል', 396, '2026-09-15 22:24:46'),
(397, 6, 'ግእዝ', 'ምዕራፍ አሐዱ ውዳሴ ማርያም ዘዕለተ ሐሙስ', '፩.፬ የሐሙስ ውዳሴ ማርያም ነጠላ ትርጉም', 397, '2026-09-15 22:24:47'),
(398, 6, 'ግእዝ', 'ምዕራፍ ክልኤቱ ውዳሴ ማርያም ዘዕለተ ዓርብ', '፪.፩ ዘዓርብ ውዳሴ ማርያም ንባብ', 398, '2026-09-15 22:24:47'),
(399, 6, 'ግእዝ', 'ምዕራፍ ክልኤቱ ውዳሴ ማርያም ዘዕለተ ዓርብ', '፪.፪ የዓርብ ውዳሴ ማርያም ነጠላ ትርጉም', 399, '2026-09-15 22:24:47'),
(400, 6, 'ግእዝ', 'ምዕራፍ ሠለስቱ ውዳሴ ማርያም ዘዕለተ ቀዳሚት ሰንበት', '፫.፩ ዘቀዳሚት ሰንበት ውዳሴ ማርያም ንባብ ወጥናት', 400, '2026-09-15 22:24:47'),
(401, 6, 'ግእዝ', 'ምዕራፍ ሠለስቱ ውዳሴ ማርያም ዘዕለተ ቀዳሚት ሰንበት', '፫.፪ ዘቀዳሚት ሰንበት ውዳሴ ማርያም ማስተዛዘል', 401, '2026-09-15 22:24:47'),
(402, 6, 'ግእዝ', 'ምዕራፍ ሠለስቱ ውዳሴ ማርያም ዘዕለተ ቀዳሚት ሰንበት', '፫.፫ የቀዳሚት ሰንበት ውዳሴ ማርያም ነጠላ ትርጉም', 402, '2026-09-15 22:24:47'),
(403, 6, 'ግእዝ', 'ምዕራፍ አርባዕቱ ውዳሴ ማርያም ዘዕለተ ሰንበተ ክርስቲያን ቅድስት', '፬.፩ የሰንበተ ክርስቲያን ውዳሴ ማርያም ንባብ', 403, '2026-09-15 22:24:47'),
(404, 6, 'ግእዝ', 'ምዕራፍ አርባዕቱ ውዳሴ ማርያም ዘዕለተ ሰንበተ ክርስቲያን ቅድስት', '፬.፪ የሰንበተ ክርስቲያን ውዳሴ ማርያም ማስተዛዘል', 404, '2026-09-15 22:24:47'),
(405, 6, 'ግእዝ', 'ምዕራፍ አርባዕቱ ውዳሴ ማርያም ዘዕለተ ሰንበተ ክርስቲያን ቅድስት', '፬.፫ የሰንበተ ክርስቲያን ውዳሴ ማርያም ነጠላ ትርጉም', 405, '2026-09-15 22:24:47'),
(406, 6, 'ግእዝ', 'ምዕራፍ ኃምስቱ አንቀጸ ብርሃን', '፭.፩ አንቀጸ ብርሃን ንባብ እና የቃል ጥናት', 406, '2026-09-15 22:24:47'),
(407, 6, 'ግእዝ', 'ምዕራፍ ኃምስቱ አንቀጸ ብርሃን', '፭.፪ አንቀጸ ብርሃን ማስተዛዘል', 407, '2026-09-15 22:24:47'),
(408, 6, 'ግእዝ', 'ምዕራፍ ኃምስቱ አንቀጸ ብርሃን', '፭.፫ የአንቀጸ ብርሃን ነጠላ ትርጉም', 408, '2026-09-15 22:24:47');

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL COMMENT 'CHILDREN or YOUTH - stable machine key',
  `name_am` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `code`, `name_am`, `name_en`, `sort_order`, `created_at`) VALUES
(1, 'CHILDREN', 'ህፃናት ክፍል', 'Children Division', 1, '2026-09-09 17:56:51'),
(2, 'YOUTH', 'ወጣቶች ክፍል', 'Youth Division', 2, '2026-09-09 17:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `level_number` tinyint(4) NOT NULL COMMENT '1-12',
  `name_am` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `division_id`, `level_number`, `name_am`, `name_en`, `created_at`) VALUES
(1, 1, 6, '6ኛ ክፍል', 'Grade 6', '2026-09-09 17:56:51'),
(2, 1, 5, '5ኛ ክፍል', 'Grade 5', '2026-09-09 17:56:51'),
(3, 1, 4, '4ኛ ክፍል', 'Grade 4', '2026-09-09 17:56:51'),
(4, 1, 3, '3ኛ ክፍል', 'Grade 3', '2026-09-09 17:56:51'),
(5, 1, 2, '2ኛ ክፍል', 'Grade 2', '2026-09-09 17:56:51'),
(6, 1, 1, '1ኛ ክፍል', 'Grade 1', '2026-09-09 17:56:51'),
(7, 2, 12, '12ኛ ክፍል', 'Grade 12', '2026-09-09 17:56:51'),
(8, 2, 11, '11ኛ ክፍል', 'Grade 11', '2026-09-09 17:56:51'),
(9, 2, 10, '10ኛ ክፍል', 'Grade 10', '2026-09-09 17:56:51'),
(10, 2, 9, '9ኛ ክፍል', 'Grade 9', '2026-09-09 17:56:51'),
(11, 2, 8, '8ኛ ክፍል', 'Grade 8', '2026-09-09 17:56:51'),
(12, 2, 7, '7ኛ ክፍል', 'Grade 7', '2026-09-09 17:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plans`
--

CREATE TABLE `lesson_plans` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `ethiopian_year` int(11) NOT NULL,
  `ethiopian_month` tinyint(4) NOT NULL,
  `week_number` varchar(50) DEFAULT NULL,
  `chapter` varchar(255) DEFAULT NULL,
  `sub_topic` varchar(255) DEFAULT NULL,
  `ethiopian_day` tinyint(4) NOT NULL DEFAULT 1,
  `lesson_number` varchar(20) DEFAULT NULL,
  `topic` varchar(255) DEFAULT '',
  `duration_minutes` int(11) DEFAULT NULL,
  `objective` text DEFAULT NULL,
  `teaching_method` text DEFAULT NULL,
  `materials` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `homework` text DEFAULT NULL,
  `evaluation` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `paper_photo_path` varchar(255) DEFAULT NULL COMMENT 'Optional photo of the original paper plan, for reference alongside the digital entry - no automatic text extraction (OCR) is performed; the teacher types the fields themselves.',
  `status` enum('draft','submitted','reviewed','approved','needs_correction') NOT NULL DEFAULT 'draft',
  `admin_feedback` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_plans`
--

INSERT INTO `lesson_plans` (`id`, `teacher_id`, `class_id`, `semester_id`, `subject`, `ethiopian_year`, `ethiopian_month`, `week_number`, `chapter`, `sub_topic`, `ethiopian_day`, `lesson_number`, `topic`, `duration_minutes`, `objective`, `teaching_method`, `materials`, `activities`, `homework`, `evaluation`, `notes`, `paper_photo_path`, `status`, `admin_feedback`, `version`, `created_at`, `updated_at`, `is_deleted`) VALUES
(1, 15, 7, 1, 'ግብረገብ', 2018, 1, '1ኛ ሳምንት', 'ምዕራፍ 1', 'እግዚአብሔር ዓለምን መፍጠሩ', 1, NULL, 'ምዕራፍ 1 - እግዚአብሔር ዓለምን መፍጠሩ', NULL, 'ተማሪዎች እግዚአብሔር ፈጣሪ መሆኑን ይረዳሉ፤ ፍጥረታትን በስም ይለያሉ።', NULL, 'የሥዕል መጽሐፍ፣ ፍላሽ ካርድ', NULL, NULL, 'በቃል ጥያቄና መልስ መጠየቅ', NULL, NULL, 'approved', 'good', 1, '2026-09-11 19:13:30', '2026-09-12 09:37:55', 0),
(2, 15, 7, 1, 'የቤተክርስቲያን ታሪክ', 2018, 1, '2ኛ ሳምንት', 'ምዕራፍ 1', 'አዳምና ሔዋን በገነት', 1, NULL, 'ምዕራፍ 1 - አዳምና ሔዋን በገነት', NULL, 'አዳምና ሔዋን የመጀመሪያዎቹ ሰዎች መሆናቸውን ይገነዘባሉ።', NULL, 'ሥዕላዊ ገለጻ፣ ቪዲዮ', NULL, NULL, 'የክለሳ ጥያቄዎች', NULL, NULL, 'approved', 'በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', 1, '2026-09-11 19:13:31', '2026-09-11 19:23:00', 0),
(3, 2, 1, 1, 'ነገረ ሃይማኖት', 2018, 1, '1ኛ ሳምንት', 'ምዕራፍ 1', 'ምሥጢረ ሥላሴ አጠቃላይ ትምህርት', 1, NULL, 'ምዕራፍ 1 - ምሥጢረ ሥላሴ አጠቃላይ ትምህርት', NULL, 'ተማሪዎች የአንድነትና የሦስትነትን ምሥጢር ከመጽሐፍ ቅዱስ ማስረጃ ጋር ይረዳሉ።', NULL, 'መጽሐፍ ቅዱስ፣ የማስተማሪያ ቻርት', NULL, NULL, 'የቡድን ውይይትና የቤት ሥራ', NULL, NULL, 'submitted', NULL, 2, '2026-09-11 19:13:31', '2026-09-12 09:03:24', 0),
(4, 4, 1, 1, 'ስርዓተ ቤተክርስቲያን', 2018, 1, '2ኛ ሳምንት', 'ምዕራፍ 2', 'የቅዳሴ ሥርዓትና ክፍሎቹ', 1, NULL, 'ምዕራፍ 2 - የቅዳሴ ሥርዓት', NULL, 'ተማሪዎች የቅዳሴን ክፍሎችና ምሥጢሩን ጠንቅቀው ያውቃሉ።', NULL, 'መጽሐፈ ቅዳሴ፣ ሥዕላት', NULL, NULL, 'የጽሑፍ ምዘና', NULL, NULL, 'needs_correction', 'እባክዎ የመርጃ መሳሪያውን ዝርዝር ጨምሩበት', 1, '2026-09-11 19:13:31', '2026-09-11 19:13:31', 0),
(5, 1, 7, 1, 'መሠረተ እምነት', 2019, 1, '1ኛ ሳምንት', 'ምዕራፍ ፩ እግዚአብሔር ፈጣሪ ነው', '፩.፩ ዓለምን የፈጠረ እግዚአብሔር ነው', 1, NULL, 'ምዕራፍ ፩ እግዚአብሔር ፈጣሪ ነው - ፩.፩ ዓለምን የፈጠረ እግዚአብሔር ነው', NULL, 'ተማሪዎች እግዚአብሔር ዓለምን እንደፈጠረ እንዲረዱ', NULL, 'መጽሐፍ ቅዱስ', NULL, NULL, 'የቃል ጥያቄ', NULL, NULL, 'submitted', NULL, 1, '2026-09-13 20:48:13', '2026-09-13 20:48:13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plan_versions`
--

CREATE TABLE `lesson_plan_versions` (
  `id` int(11) NOT NULL,
  `lesson_plan_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `snapshot_json` longtext NOT NULL COMMENT 'Full field snapshot before this edit, as JSON',
  `changed_by` int(11) NOT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_plan_versions`
--

INSERT INTO `lesson_plan_versions` (`id`, `lesson_plan_id`, `version`, `snapshot_json`, `changed_by`, `change_reason`, `created_at`) VALUES
(1, 3, 1, '{\"id\":3,\"teacher_id\":2,\"class_id\":1,\"semester_id\":1,\"subject\":\"ነገረ ሃይማኖት\",\"ethiopian_year\":2018,\"ethiopian_month\":1,\"week_number\":\"1ኛ ሳምንት\",\"chapter\":\"ምዕራፍ 1\",\"sub_topic\":\"ምሥጢረ ሥላሴ አጠቃላይ ትምህርት\",\"ethiopian_day\":1,\"lesson_number\":null,\"topic\":\"ምዕራፍ 1 - ምሥጢረ ሥላሴ\",\"duration_minutes\":null,\"objective\":\"ተማሪዎች የአንድነትና የሦስትነትን ምሥጢር ከመጽሐፍ ቅዱስ ማስረጃ ጋር ይረዳሉ።\",\"teaching_method\":null,\"materials\":\"መጽሐፍ ቅዱስ፣ የማስተማሪያ ቻርት\",\"activities\":null,\"homework\":null,\"evaluation\":\"የቡድን ውይይትና የቤት ሥራ\",\"notes\":null,\"paper_photo_path\":null,\"status\":\"submitted\",\"admin_feedback\":null,\"version\":1,\"created_at\":\"2026-09-11 22:13:31\",\"updated_at\":\"2026-09-11 22:13:31\",\"is_deleted\":0}', 2, 'teacher_edit', '2026-09-12 09:03:24');

-- --------------------------------------------------------

--
-- Table structure for table `marking_schemes`
--

CREATE TABLE `marking_schemes` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `component1_name` varchar(50) DEFAULT 'Assignment',
  `component1_percentage` decimal(5,2) DEFAULT 20.00,
  `component2_name` varchar(50) DEFAULT 'Participation',
  `component2_percentage` decimal(5,2) DEFAULT 20.00,
  `component3_name` varchar(50) DEFAULT 'Attendance',
  `component3_percentage` decimal(5,2) DEFAULT 10.00,
  `component4_name` varchar(50) DEFAULT 'Mid Exam',
  `component4_percentage` decimal(5,2) DEFAULT 25.00,
  `component5_name` varchar(50) DEFAULT 'Final Exam',
  `component5_percentage` decimal(5,2) DEFAULT 25.00,
  `total_percentage` decimal(5,2) GENERATED ALWAYS AS (`component1_percentage` + `component2_percentage` + `component3_percentage` + `component4_percentage` + `component5_percentage`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marking_schemes`
--

INSERT INTO `marking_schemes` (`id`, `teacher_id`, `class_id`, `semester_id`, `component1_name`, `component1_percentage`, `component2_name`, `component2_percentage`, `component3_name`, `component3_percentage`, `component4_name`, `component4_percentage`, `component5_name`, `component5_percentage`, `created_at`, `updated_at`) VALUES
(8, 15, 7, 1, 'የቤት ሥራ', 20.00, 'የክፍል ተሳትፎ', 30.00, 'የክፍል ክትትል', 50.00, 'የአጋማሽ ፈተና', 0.00, 'የማጠቃለያ ፈተና', 0.00, '2026-09-17 21:20:19', '2026-09-17 21:30:55');

-- --------------------------------------------------------

--
-- Table structure for table `marks`
--

CREATE TABLE `marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `assignment` decimal(5,2) DEFAULT 0.00,
  `participation` decimal(5,2) DEFAULT 0.00,
  `attendance` decimal(5,2) DEFAULT 0.00,
  `mid` decimal(5,2) DEFAULT 0.00,
  `final` decimal(5,2) DEFAULT 0.00,
  `total` decimal(5,2) DEFAULT 0.00,
  `entered_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `local_uuid` varchar(36) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marks`
--

INSERT INTO `marks` (`id`, `student_id`, `class_id`, `teacher_id`, `semester_id`, `assignment`, `participation`, `attendance`, `mid`, `final`, `total`, `entered_date`, `last_updated`, `local_uuid`, `is_deleted`) VALUES
(1, 31, 1, 3, 1, 0.00, 10.00, 0.00, 0.00, 0.00, 10.00, '2026-05-10 16:03:04', '2026-05-10 16:03:04', NULL, 0),
(2, 89, 7, 15, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-09-12 12:02:54', '2026-09-12 12:03:04', NULL, 0),
(5, 31, 1, 2, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-09-12 12:24:55', '2026-09-12 12:24:56', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `marks_backup`
--

CREATE TABLE `marks_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `assignment` decimal(5,2) DEFAULT 0.00,
  `participation` decimal(5,2) DEFAULT 0.00,
  `attendance` decimal(5,2) DEFAULT 0.00,
  `mid` decimal(5,2) DEFAULT 0.00,
  `final` decimal(5,2) DEFAULT 0.00,
  `total` decimal(5,2) DEFAULT 0.00,
  `entered_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pass','fail') DEFAULT 'fail',
  `grade_level` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_event_id` int(11) DEFAULT NULL,
  `related_page` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `created_by` int(11) DEFAULT NULL COMMENT 'NULL = system-generated (e.g. exam reminder)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `related_event_id`, `related_page`, `priority`, `created_by`, `created_at`) VALUES
(1, '👁️ የትምህርት ዕቅድ ታይቷል', 'ለዕቅድ \'አዳምና ሔዋን በገነት\' አስተዳዳሪ ግምገማ ሰጥቷል። አስተያየት: በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', NULL, 'lesson_plan_editor.php', 'normal', 1, '2026-09-11 19:21:26'),
(2, '✅ የትምህርት ዕቅድ ጸድቋል', 'ለዕቅድ \'አዳምና ሔዋን በገነት\' አስተዳዳሪ ግምገማ ሰጥቷል። አስተያየት: በጣም ጥሩ ዝግጅት ነው፣ በርቱ!', NULL, 'lesson_plan_editor.php', 'normal', 1, '2026-09-11 19:23:00'),
(3, '⚠️ የትምህርት ዕቅድ ማስተካከያ ተጠይቋል', 'ለዕቅድ \'እግዚአብሔር ዓለምን መፍጠሩ\' አስተዳዳሪ ግምገማ ሰጥቷል። አስተያየት: good', NULL, 'lesson_plan_editor.php', 'normal', 1, '2026-09-12 09:36:15'),
(4, '✅ የትምህርት ዕቅድ ጸድቋል', 'ለዕቅድ \'እግዚአብሔር ዓለምን መፍጠሩ\' አስተዳዳሪ ግምገማ ሰጥቷል። አስተያየት: good', NULL, 'lesson_plan_editor.php', 'normal', 1, '2026-09-12 09:37:55'),
(5, '📅 የካላንደር ማሳሰቢያ ለአስተዳዳሪ', 'በኦፊሴላዊው ካላንደር መሠረት የ1ኛ መንፈቀ ዓመት ማጠቃለያ ቀን (የካቲት 14/2019) ደርሷል። አስፈላጊ ሆኖ ሲገኝ ክፍሎችን እርስዎ እራስዎ መቆለፍ ይችላሉ።', NULL, 'class_locks.php', 'high', 1, '2026-09-12 13:21:18'),
(6, '📋 የመረጃ ለውጥ ጥያቄ ቀርቧል', 'መምህር Test የመረጃ ለውጥ ጥያቄ አቅርበዋል። እባክዎ በመምህራን አስተዳደር ገጽ ይገምግሙ።', NULL, 'manage_teachers.php#requests', 'normal', 15, '2026-09-16 20:00:30'),
(7, '⚠️ የመረጃ ለውጥ ጥያቄዎ ውድቅ ተደርጓል', 'ያቀረቡት የመረጃ ለውጥ ጥያቄ ተቀባይነት አላገኘም። ማብራሪያ: no we need your before', NULL, 'teacher_profile.php', 'normal', 1, '2026-09-16 20:01:44');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `notification_id`, `user_id`, `read_at`) VALUES
(1, 5, 1, '2026-09-12 13:28:31'),
(2, 7, 15, '2026-09-16 20:02:17'),
(3, 6, 1, '2026-09-16 20:34:23');

-- --------------------------------------------------------

--
-- Table structure for table `notification_targets`
--

CREATE TABLE `notification_targets` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_targets`
--

INSERT INTO `notification_targets` (`id`, `notification_id`, `division_id`, `grade_id`, `class_id`, `user_id`) VALUES
(1, 1, NULL, NULL, NULL, NULL),
(2, 2, NULL, NULL, NULL, NULL),
(3, 3, NULL, NULL, NULL, NULL),
(4, 4, NULL, NULL, NULL, NULL),
(5, 5, NULL, NULL, NULL, 1),
(6, 5, NULL, NULL, NULL, 14),
(7, 6, NULL, NULL, NULL, 1),
(8, 6, NULL, NULL, NULL, 14),
(9, 7, NULL, NULL, NULL, 15);

-- --------------------------------------------------------

--
-- Table structure for table `profile_change_requests`
--

CREATE TABLE `profile_change_requests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `requested_name` varchar(100) DEFAULT NULL,
  `requested_phone` varchar(50) DEFAULT NULL,
  `requested_photo` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profile_change_requests`
--

INSERT INTO `profile_change_requests` (`id`, `teacher_id`, `requested_name`, `requested_phone`, `requested_photo`, `reason`, `status`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(2, 15, 'Test', '000000000000', NULL, '', 'rejected', 'no we need your before', 1, '2026-09-16 23:01:44', '2026-09-16 20:00:30');

-- --------------------------------------------------------

--
-- Table structure for table `promotion_history`
--

CREATE TABLE `promotion_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `from_class_id` int(11) NOT NULL,
  `to_class_id` int(11) NOT NULL,
  `from_academic_year` int(11) DEFAULT NULL,
  `to_academic_year` int(11) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `status` enum('promoted','repeated') NOT NULL,
  `promoted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of endpoint for deduplication',
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `push_subscriptions`
--

INSERT INTO `push_subscriptions` (`id`, `user_id`, `endpoint`, `endpoint_hash`, `p256dh`, `auth`, `device_name`, `created_at`, `updated_at`, `last_used_at`) VALUES
(3, 15, 'https://fcm.googleapis.com/fcm/send/fNJaEzQdbF8:APA91bHTsZwG8IUsK2XIym8a3LLg64ypNoYhWN984s11tTL35n1Jr-_uuOflPdVQdi4tk7eUM4b5G1gm9iQabtXPY4dHjDljxOVjdoMhaRwl01Mb0ZHXdBS5qP9cVrDORoof_0AE8n4v', '7b50e66f6c621a540459d9276d062f24c1c6d05134f421270c0d8164e04e395d', 'BJb1vP83RDyGE2V39/bG7xUWxmfUOncQAlOf0q0lFhAa4aSO+m9q36Wy1HhBzBXUzBmS5SpJNEkcayKlLFR2QTE=', 'y1H4T302b79qauVPj4snwg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-11 17:29:22', '2026-09-16 20:01:46', '2026-09-16 20:01:46'),
(5, 15, 'https://fcm.googleapis.com/fcm/send/eAqXbOKEnkg:APA91bFqhfpRcSizZ0SLzIo2c8-t_gJY5-Jb4bNSZ6rdH4JCuqlOwBDt5pcKUyGRkegv5FcXeCU5Tnwtpbc8gpK5iV4_nwPqWFRegIYPtEH0FpQMtz8_Gdr2pIPUf0RVpW1tOf94ecMr', '8d13309199cad36852387871918118cf5fb9a525ecdbb6cf3003a55b098ed9f4', 'BGDJMj4nMaXJ2cVN0nWtg4JSnzEdTZgA2fCigp+Mo5C0ptyCMNCdb65asyZi3bNp8L5mHqXF9m4H1EnHT3lPpwU=', 'NMSyUQSrflKyqwRevNmd5w==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-12 12:18:48', '2026-09-16 20:01:51', '2026-09-16 20:01:51'),
(6, 1, 'https://fcm.googleapis.com/fcm/send/eOaNAPhcU0Q:APA91bHC6yMxVRr2KX2Nq3sgtvFg_pu46c3jIoTAbyZAHdsqw_Yt-VAQD65z0zRlantY_Lbd5mZilh75DBKTf17U3tAxsaQEbgf6eDpxQOpawufH8XbBvaxL29Z_gAiJ0h_MO8Tm1SZ5', '7567dc44137b4bdcdbcaee1bc67d82a66efe5e902ce54aa9b6766e9129442b0c', 'BLGtBxBFrOX7FlUL2gVhll/E99fGwQN0f9c1Ep1bOVeVO8FHncZxKefxrUpUyX+zPA+jYtF0GYQmkvUNS6AvzjQ=', 'CkzxypA3FhrbRTRGy4uL3g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-13 09:19:15', '2026-09-16 20:00:35', '2026-09-16 20:00:35'),
(7, 1, 'https://fcm.googleapis.com/fcm/send/dl-53eamePI:APA91bGiXoQnojp8xVVdA3LoyTEGDr6nlaoNtT_foStmsFrOPAjdOHTmbNZxwTtpsy3-Fn70NN86K5ubhJPuNyWu3CGMqZiSj27DMEoiXWAdAB-T_SGuZRnQ4yF17CryoEqHZUZoS7UZ', '80df16bb7bb82f17dffd23c7d185345c7a49c84cc56d77aa8d2ad81477d756e5', 'BPz66jLiebdawXm3MQPM2ENVUxOl802/RYbURQRktBhyftFqUz+yUz/74NQ0lAnCgokUUL/jVQVkO7KconzHxaU=', '5DeFC1plHz19EQN6ajAOvA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-13 09:20:10', '2026-09-16 20:00:37', '2026-09-16 20:00:37'),
(8, 1, 'https://fcm.googleapis.com/fcm/send/e8d1nJ4gYO8:APA91bEhSGBhv7k37pYvSrnhYw4Yx6ul8MjjJInQrYYXdLwIIWzpIZMuorGmpSw08pu7kE_RjTgc4BkpR5fTqxULQt8ICgB9Ac6H9ZwNUvAUaTJoQmcOe_p5v7d5mjwO5CoMGxFJdD8_', '4c4d352490ec7a8ecd894399fb8ec2b27363702405bced8f3ba61a83d59c3c3a', 'BNaTxUIhlZ7Kngy6+nZ6cS0wPOV4AqU7ASU3TZMLxVTSaKVUvAjVqlV3X8OS5/t03O29KCiGZAXM3+0L59kRz/k=', 'wSqD7q2Ztyo1UU0SbDvPwA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 21:20:39', '2026-09-16 21:20:39', NULL),
(9, 1, 'https://fcm.googleapis.com/fcm/send/djeVt0AtvOw:APA91bHckc9axKcA5byW_yvBmL5LK13ytfwm0dnWonlyu6qqFVHxOJk_uc9hzM-N3Sy55KhDW5ZPiXAMfSQJGbGMrQpWTW3s7Whei167ewmzvXy3m6skryw19bV4c2v_hFnF_5cmB5lv', 'cb4c5ecf075c8b24523693305d9c571edbab80ae2d52d928612945e5fcbf5e37', 'BEG8TKoMozTxiOFF+dG1jkls1SSVKYlh+uqPqr92PCXv3DJqhVhTbQGRAlbuZd/pHB2eLMQnQOLyT3vi1dhUAr0=', 'aOY8MuFn81oaCVY8QZ5zFA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 21:29:23', '2026-09-16 21:29:23', NULL),
(10, 15, 'https://fcm.googleapis.com/fcm/send/eXYkMcKprr8:APA91bG-M46J3knpVcwuybASUD60vHQiVQqpw2dz11fpEgsEHyc9-3gqLB2yX2OfvcwY52qK00quB_BZ8T6ad2VgRVtpSsrcA94Smv-NJyLHwJL3ZStv0ha8RK79NzKOgIDa-SWYm5tb', '95d431d52ec6b18e9e1e7f03622f00aa04e402b360fee3b421218ac3fef90dc5', 'BBx+q2q/rY+hpzcvz760vGc+WiUbvf7vHAvoqhcM1B4bbKeZQLd3g8gqWItPZAKB1GFJ6h59sP8iWdKlCYnAt9A=', 'jhR5jSK+dSwQNWCV2Yg96Q==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-16 22:24:10', '2026-09-16 22:24:10', NULL),
(11, 15, 'https://fcm.googleapis.com/fcm/send/c1SjIGXrzSI:APA91bHNNw_i46AnP_5vNXpwrcXx8s_2MePYZjEVbtMl455_6MGTXkNLwMdA0f2OiOTkMti6Q2BBbJtwsucStdset71LsCHfRCkqvDHT-WiPtYM-caScyc_InLt-QVkgQixjrClyI7z2', 'd92b5d04f84ce720738a5d7850ef8611cb5ad616cba83b380be8b40d7350f914', 'BDDYc+HPRH0NPmmy8KH5W2gNSKbxApdoL93JAlx6aOZ2imRVFbagFNOmNjFU+L4rnASmVzVAr0LwnrK3znYUTKc=', 'WpND6kDlNfYPs/iPE9ij/Q==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-16 22:24:13', '2026-09-16 22:24:13', NULL),
(12, 15, 'https://fcm.googleapis.com/fcm/send/elozG0HvUAI:APA91bHp3QjGZYJU1rtXk3GYPFAhoJ5z25iruglcw_AwnxK5L--vUTGtAjsIJ8RlRLxj_GosA7pBPzvzHCx9x1PToywQkFTiEH4HgazpAKO9EdB6fSMdUOjxaaGWQBEzqj2t4z2DCbXe', 'f75bb07f087498f031d26f6d31837cfa5bafd4ac06502d56465a6fa4ae360ceb', 'BMQIhXPomITHxP32h49wghjyzfxZKSKB8x526iBNQS/MIWmmQALuIY++ARQwBL49Ko+31CAJscRuDONXmZEebcs=', 'eBfCRMS4cTrDlkWZRxn+nQ==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-16 22:25:15', '2026-09-16 22:25:15', NULL),
(13, 15, 'https://fcm.googleapis.com/fcm/send/eWeN1WgVLy4:APA91bG1T6N9ca0af2BtmLlKxugZyEz5EgJN9o87a0gPZdRLt7LFmHO8UgNhucGtZhYzAE5SH47YHZEQFYbSlHFLyb2Dsh5-sJVnrQpTivJts-XG8H5QA57bTJQwn6Avfdady9AGH1Eh', 'a1a96da7bf1353245c93daa12b5b10ae900800538463d3a8a5ff4eb7b7fa5495', 'BDUQ1yhu1jfNQR0s4OfxgmYQJccf4FOeFquSMzmgsihsMwseGC80rs6FAArL/7EN2h2MTSJBISs9ZG02V41zCkk=', 'BWK2AUwSPa9ppYdcrq2F5g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 22:31:00', '2026-09-16 22:31:00', NULL),
(14, 15, 'https://fcm.googleapis.com/fcm/send/cBgBPTvVmss:APA91bHwe8BV_6H1VW707Gly2MUFiMj4OHee3cukGhzj20Ztel0AfRVg6Rk0O9biXgb4MzdbSY4C2PZ2l_4qOwlmmsBkWAh8pHdcAkRTvq6FyYjvBK4pJd-O7jsp0L3Shhm6CW0x7GPz', '543282afa2569e0ddbd5afec07f4f5200aae03fe0b99563f2c59e7ef0b73e838', 'BCtHLzspVJDmFEWY9s10uNtf+H9JUny4HyMJ5bmFJL1PtSXQ2Wmv2QkK46yw63xQKXC3TeZRvkYoWByv3pSDDN0=', 'weWMZIve+1Uol2YWEhO0SQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 22:31:18', '2026-09-16 22:31:18', NULL),
(15, 15, 'https://fcm.googleapis.com/fcm/send/daX9wETanZI:APA91bGu2F0T5z-qP1ZLDXqpf2umdDjL-3K3n02fP4Lv9x6EH8hmYe7nqOG_rXhcOnDh7Hprg13_TKtMRB0a5fgYcB-Rh380M_O6V9iy72Q6TBPKIRyibT9ncEo_bTMFFVqZO5ByFnfa', '2aa2164686493d68d7d9f6a3971e79f338eb21349e069baed29d31af36c0e7ba', 'BLPTw//6Ye+w/0wmajYx/CXki5cF9OIFpoam7JIY0tFbqoMiMhuCbSqtvyNMj5SnGB+eL9BwZnHuSniydYU3gto=', 'LLmZfxP3LUCVkXnEYsfHbQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 22:53:21', '2026-09-16 22:53:21', NULL),
(16, 15, 'https://fcm.googleapis.com/fcm/send/dpV4oDbf_Bo:APA91bExbcu5te7y1Td3xRHNynUjVp6I_mL5ZW7QbnxS3z3be2kxn35qEpRkop3xYRTPP-jXqUwJ2Nmm7LQyRbvAn7vK4lCP1CnyUkM1Zb4Q6LM_jiwu3wG-qZSRzk-dxc8OEDxoOp0P', 'e255006ab350018caa5cbdf1f8ef6d5c3b72207fe4732491764807a536cfeaf5', 'BOUI1QQrDSbubntG3t1iHPhuKhUhZTkiz8xEvkDYeAQ3XsxxU0BWAjUwv4gUtilv+ltA2RkygnM2j/h50ffFq+g=', 'rhlMwktC/Qx8E0axxydrRA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:17:14', '2026-09-16 23:17:14', NULL),
(17, 15, 'https://fcm.googleapis.com/fcm/send/famFKYT6cGA:APA91bFQhiyohe6FgZir7444FJ7ooCvF5m1NXNTJIRzisVv_gojhlfpMdXkf1m4dxnRu4m6bd3H2ndv0c5KawCJUtz39Hif52ajptWcvPRx50hb1hQnyAPuhEbKUvfR4mzSXTq1RlGLP', '5b9a343b433dcaded9513f6c55bea3707b931c51e638750d2b789a88333625ea', 'BEGV60IOsD9t67fHuV3Kq1cm1FMlxdQ1tPBcEfWEITQXxQS0gFZ3sYPQvy/SlIQ7rUTDu2HICMAFhQRObnL6ezk=', 'RJyWTJjkg0GbtWBG7r0+pg==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-16 23:18:03', '2026-09-16 23:18:03', NULL),
(18, 15, 'https://fcm.googleapis.com/fcm/send/cZxICQr8EDg:APA91bGhOo9W7jSEnY5ZjgAZqMi-k1yN2c04h17aQfHYZPqUrWiHSo_Qc37c3ZtMh0pZROJc7RgGZ6VWBGU2Cf7JQW0Ys0tRFriMeclKOOomQSoUqtkdbzKUdPNgScPqZGxIYoWtBxxG', '1909d0a1a75288152f1201fbe10f9a0730e070092ee65da6a16179ad5990690b', 'BLmE/iX7ICyO0xUjGoNsp/pBoRkxt9L3Cz9QKuw6DZ69rH8Er5ejIKke8w0mUz/1lZrBTK9jn3k/ezLMfCiDJU4=', 'bet86ExlkC3eiYlbCZ5i+g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:21:02', '2026-09-16 23:21:02', NULL),
(19, 15, 'https://fcm.googleapis.com/fcm/send/dxLOF_nUdHY:APA91bFCRaCzcYwvUTCd-dW6P8YQbs1yY5Weti5pg9kz0vb1PqN6HlcGE7xtSp0Glu8A6PGfibAlhwn3BAI2YXS10sZGyY8BuyLj1kIEjU3PbwgQOvoIEVHpSCZwOripOqrfyiwsXVJO', '69fa704ea7b2d27dcdd58d32b4a107caeaf85980938f8390f4982594b95b25e9', 'BEksxNH/asFl5cR3B9ACs6rpLGP8/RVKrM4PUql47zgt91l9iXoSTfxuWic67g09DMCuGIEaN09shBIpxt34D04=', 'EL1/h9IA59rfIOAP8BUZEA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:21:04', '2026-09-16 23:21:04', NULL),
(20, 15, 'https://fcm.googleapis.com/fcm/send/dqOdzs5BC1o:APA91bFIxb4lOtub5YQz37Gx0gJwijuc-qNTfVaD7QROvyNsS3ZSEnahYFNG8-sJKms6XH53TwoaudmlsJsYXfGj4cibKL8oY7epYl7EL1Bab_aZSmpupF75mSxy7VqzAnxwFut7KC2H', 'f598dbfd9c083a517a96d76cccad820104e3bd6e4404efe9d3f8b4ad0d739d08', 'BB7EgZgrX2I1whjZhGfd0A/MN58PibwainHYj/MjTnKy1FBQ8PXCRIczSHvauUMLIASoS0mjb0b8uf405d5mTm8=', 'oNMi1LgLPDXxxAjhIYrZBg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:30:51', '2026-09-16 23:30:51', NULL),
(21, 15, 'https://fcm.googleapis.com/fcm/send/dxurGxO2KeY:APA91bGH9Dm_U_2-D6OKhSpBKzWwRi7cU4L1T49gleWAhehJUcVJpGayENsu--4DIi3VqDFLcGDmec8d_rzcMjuuD6Zh06n3pcwD4YFhDyw7oW1i11t4yUWdVYnCsx9H4tISyXmKLWJf', '8192c9636c181612b0064dfd3776616bfaed65dd9974f7f3a6ef14a2b7c75d99', 'BEuXnGpg2JmFHwEiD+lFPv7FYcSmxnzuyHVO2cjKqOcDpdPOaX+i3bSvHLZGW3PodZTZ+uqOd+/Tnf0fZC6rzJc=', 'tkweJCPnFancyp+IkbPL3A==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:30:51', '2026-09-16 23:30:51', NULL),
(22, 15, 'https://fcm.googleapis.com/fcm/send/cArct62OoJ8:APA91bEpgKXqbJX7KfMXmEBVycDCGYdMJYCq9VqNFViayO21CNQROnPN-zr2joIZw8nf2BCdYQBe2SM6H7BFooCbF9ohxoc8HT_6rIP_8C-wlIH7AeUdrOJNV7N5enCSgOewbfzfBy3O', '8d657a6b211a45f7a68f21cd63118cd27c472709a36aaff660f7ac6678ba7dc7', 'BMw3sSKzmWokfD+ruIMgA1TK8v0szw40Ma+QJ2itw8MhDMeSmBw2k9e2i48r+u46ctl5paZ4bQAxAs6s68Sfmn0=', '9A9+miuVkF1r+K3G/oVEHQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-16 23:32:59', '2026-09-16 23:32:59', NULL),
(23, 15, 'https://fcm.googleapis.com/fcm/send/fXBeRnkGEC4:APA91bFYKWTJtaIT1ty6Z0aZq0T4xdeoCaBJyBVB5Vh62emHsaJPrR89a-XSDMOiP0f8XJqZTabHOklkw6EdTLKBIWA6csrISYDP0iKIGyVEudsrJ0K3nPLhB7MobC1hsqIxgRJgeC_D', '4a09f2ae7e6633b5d7d5c426f09b6ee749f39e8bb94a8f1ff600a89880760343', 'BEZrouVM4++qdRJm0WSIQRRKuVmGqHD6cu9Y04zPn67Zqtxvzeigtwijej3kTfWJzDTDWjt2pc0v/dkOfgsNBOc=', 'o/hLd0eHMnkZtxf/30DTRA==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-16 23:37:34', '2026-09-16 23:37:34', NULL),
(24, 15, 'https://fcm.googleapis.com/fcm/send/d5OU9mDMeRs:APA91bFJw5dVSCsw_b9BuJWpRuOhYMKeJHJqJ7eyaAKyRHxdb5pJnxbav6Z-YdQR3uLsxyxAmWb_d6ntK7UTd9i371zySzMM8Oqc6W9hWZQMup9VOj-61W3Z4Pmu7blLVOaZAPmHGLOV', '9a460ce3f41d7b1654dba82f8d80175f3a33e9f7bec019fc907f5ef399eea810', 'BHSFcFiiun32vqDzqwwLBxnfOUbYWKgAtzuI9xO/O1plDjDjOdEn4KF9aTf3KPkUzv0oXi16y7kRddx9lobS/xM=', 'Z3lfIA7OTf0X0KnQuCxj+Q==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-17 00:07:04', '2026-09-17 00:07:04', NULL),
(25, 15, 'https://fcm.googleapis.com/fcm/send/fa6UQzrvm6o:APA91bGd8C0xSVsUS-HSEl-L_nbPLr_0R5vCQJBkPkaxWbpLomPzNSrixvlgDUb419PLqZ6lAvvXXvM26UuM2IzqpU-Cv2j_IHUQJxfSZjZHW0hNZNiGNlN28XUoA1VoHrNwtQaC0ccL', '38c8b84f10eddaba645be00bc67539dc87ef1d24748bcad9cd2a7f1fe8647c05', 'BNSf3lAUwwA0aYeHtnCR9lZbTmxsJVhoQObg1qjMUCePgsjQiXAoBOFsQ7JyniZIzTtGl1tGNCAWa6rEejCWbt8=', 'ArLWsLu45gFrb9NYu4ZAJg==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-17 00:07:04', '2026-09-17 00:07:04', NULL),
(26, 15, 'https://fcm.googleapis.com/fcm/send/d3u4UwfGXuY:APA91bGF5juL0DpWKzitubPjwVFWibcvAzikG2PbpEBqbwiys9AB5eMgcKZcavw_45eZvTIe3QY77B1DSbZ0xOOC2okEGm-m3fHtKSKzaNiEe0ysAU5S1CybBQxh4uN7agbnWEVVwuxr', 'eb91f95f523ab1fa51005e18bdedfad0f3e18981266dbfd8c505f1ab4be725a3', 'BF3uNQiF4qhVq5n1ZUm2jPujICIc75Vw4ExLQPUM//a+B01Ii3DpnkeHLHvPyahYnTsEsismUO+NpMz6UUVwVZ0=', 'pVO7B68XuQw2QvuavQ0plg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:07:29', '2026-09-17 00:07:29', NULL),
(27, 15, 'https://fcm.googleapis.com/fcm/send/eR2EjrR3eck:APA91bHdDmQ9bed1BvNYIlrXmgPSS0SFPOZifbGMgJijJOxzpa6oshU-GBH1Eb9clAPabiFwgFFFXbNDErHUojJRnEAJfqOna0KjspzGlP0kiMVfyZjTxc3-Ti0vIA_WseL9HNIlHOYC', 'ff23fd5e47153b32bf141053fca9cf81c7a2d5cb5fa3169ce9ab21d75bb14003', 'BKiIf9W8+yQibreZ4v9Qv302dLwt9yy2JwIpfFZ33Bf2YGoLtlCBT/KUDJ7OuJwKcDLKdxc+ghqJ6WA5NEPZq8U=', '1aC7U3hfH7G8NyaSFknV2Q==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:07:30', '2026-09-17 00:07:30', NULL),
(28, 15, 'https://fcm.googleapis.com/fcm/send/fs7H8SqqNgs:APA91bHL_fdwe_bQTkAZBI0JGGVb4nG8m-vHhO0gRJYZTCQiKvXIukPSk1Z-jmkTqv0XUNco3SQnBRHZtIfkxBZCkTHYlWTGM5AGR4zIypm3yx5vWPfFPNoa5-lYMWmUTrDdFg04-rMg', '3b53c490c6adf29d2cb6ffc69d4223d9c0529e3e4f1f93092dddc30b9e3c67b9', 'BI7kYY6zy2BIpCTLJzzHDfxHaNYltqEioRH2LT5F7J6IVpTAI0BRqnm/92VLmVHLY+Exldr/x5bmc2Q0GJyyLtM=', 'uhSD/FElOBUdgXQEUYEbhA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:07:30', '2026-09-17 00:07:30', NULL),
(29, 15, 'https://fcm.googleapis.com/fcm/send/d1CAjfR5Vzk:APA91bFD3rRE_4pmK1MAGrCIAoXrpBv1ugR2RCGwLD79J-jJJ7RL2EVF3dDs_tq2ayD6qVFqXAFJNL4vIdUUxMOfOPnR1_EavPPeb4bWF8M5xotzsRcmHA_jzaFqEO_zjzzglcVzbcrx', '130d5a29743c14597a3e7b1ae84a77e8e60596b43e2d00e2b8cfa1cc4b3468e8', 'BLy+ZyB5+cj81ubsd/lcgZAXXNX7OiOPLaI6RGMwJskjA2kg5auetfM4uAxGcP6bFY8Gd7hEssB0qVlbQ1roW14=', '8nCvTXfoR9GLG9ZzR6x7cg==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.18 Mobile Safari/537.36', '2026-09-17 00:13:04', '2026-09-17 00:13:04', NULL),
(30, 1, 'https://fcm.googleapis.com/fcm/send/eApAJesW5yw:APA91bE0_3BFvzbsu0QDzBIVJyPijJkr6xvRLdza7m0DobBiI9GuU6UNXRraBalxTitwMw2ki3U2uEP2FpDHsrUcAeytkRq4njoMqkrbN7R6PK78-8AAa5cL1zQEOReSbW1xwqom_aoq', 'f029e99b89928ea8210e7ecda9fe1b424c587fc7b6e968469ce072c58608fed0', 'BKo6pHpswGshsT0JdiuYDnxrOLYCRlwS0zGQw8IXz0fnFAZUxCbwpUXcQrj1rFcUbp2BsRpjtMqP5f/+2rPDEG0=', 'XnsLtUo7EDdhyNXRk7Htvw==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:29:41', '2026-09-17 00:29:41', NULL),
(31, 1, 'https://fcm.googleapis.com/fcm/send/cZ_6MT1cHFU:APA91bE_ioECGkZGu-s1x6KfYKiacj37NtCB3sevic03muc_OpDzUu3i_RjBZUni_EsGXYVf9D9RFg-2rsSB-dBPr92MT0QP0hoJ8-S30r0WYhhPd26Im9yu0PqbZdYZ61bMl43mBGmE', '1087d7d508549eaa1e316fda77cde7afcde6d3498ac5fe4ce73cf465af1aec97', 'BIDNbOUioKh+DCL+u8yR0woVU5WcztfR6lJomfPupNarg8MFcfU23cuhpIJcVkxeWblpumi0I7vM4YkETxC1DGE=', 'cyumn5SsIrGodEZHFuofBQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:44:37', '2026-09-17 00:44:37', NULL),
(32, 1, 'https://fcm.googleapis.com/fcm/send/cG_WbLwrdYo:APA91bEjL9n5uGNCgTNxf9ww9d4HhIeWoZQ-WXfDCAfv27LaF_G2AwjuYP6-DolKpsKpBbfOegYysed0r9rVa2JEPxiGyYXLtofdEYUYh0dIuRDjBtjQUGOzYoO6-U980S8Aw5HpUo7F', '3e087cca6cf3baa6049ebe686010fe3cbb4f317e33b145c5d9804490d058118e', 'BIss5b+GdHq0qpGcdULtSKC5QCej/jTCLyf9M220NFMG9IP9kyvT0jM34Pje2FFMQH7pauuUx7TlGKR4foO+GJc=', 'pHFtVAB7mymPKt8ePcngcA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 00:45:58', '2026-09-17 00:45:58', NULL),
(33, 1, 'https://fcm.googleapis.com/fcm/send/fbbFTcAJdJI:APA91bEa5lw5fbckNPARVg3EeW5UkUt0Blb2w2PQ9eoarEE7n_OpltJmUf9dv9RkMleNFf-weWYLf7LR6XbzLc0HQHdxMHIVqe4EXoekLDBx1PY8COO_pm7RVB1ofGaucHY-Yu5tRNMA', '740e5e9d2e37ca9d2b06cad9088d8dcd639168454893e38ab5bc8e8dfb9354fc', 'BA/UNoNO6wvCCtT2SOs5Yy2ZrdYCyUJs5kLz8ZDapnZeMoglTcdM4Gy+9GfazUqQw1mR7enwzqqrxC4ThrBr//0=', 'gJU590CMGGQsEot/iF3XCQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-17 01:09:42', '2026-09-17 01:09:42', NULL),
(34, 1, 'https://fcm.googleapis.com/fcm/send/fF-I-0JK9Hs:APA91bHKwYdMZky6gdiqcAFG575WWCC_vEsFr6YFfV4dVYd4DEdOkDLwYpViGCJDcbESkl3vUKQKzj-hj_OCbon_iR6RR_dM_2psVNus-wFALxmCv6i-xDq-fOkQpMfX4QN_r2TiublY', 'b23bb1410f24f2de4c6fea6f0890f4420b0602da97af29a9e60753fa40f0fac5', 'BBVH5zpEeOW39hcoj+Us2jEFDjej5Cq/QVbSfFs63v6eK3Cr4iua057FHisQS5nh6qXTzOIvjcaq0+KO4yeL5oU=', 'alP+aYM2GcYORLh+rj9nCA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 09:57:55', '2026-09-17 09:57:55', NULL),
(35, 1, 'https://fcm.googleapis.com/fcm/send/foSvBZL7UC0:APA91bFz9WrtIIVKNKgEjhqcj9M59a9busaiyLm4imYgDXn_WgFhPAFR5sLwMQbtPzTLBO-G0A4fF9wpTHzelpO4HBi86OkYkuPu7yX-fAGWES6EQ1qzn7IUvx2E55Y4P5mu7hlvjhb8', 'e0b411745925f724f0139d11f670f9542298eb1724f6e32244f9b8c0e1c9dfcc', 'BBzZxo6Pmc3AlcdyCDpeZe28BX3sSvoAHGsAACo2Sxzi0KHE8Pir9AlzaSTVaHJBySDc0kOa4YkLjPBGdJFVtno=', 'dTKQZgbixxnB9yqU2OF4ww==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 10:03:22', '2026-09-17 10:03:22', NULL),
(36, 1, 'https://fcm.googleapis.com/fcm/send/fO8zSGF_A10:APA91bFak3F_nyvL0ojMZt2GnhME3SlUG7I2ThepQ0d59XaD945GADoNcKvymY1I5TAT9_Ba1pkITYOmxFzi4nlZmpKL8yzzaZhYujK8vAUEODmiLoesAf7H03Rx1a8-7iu-PEwfc-cb', '9cc7b44f9839ea723728798ecd40de1e3c25f4a4b965dc4a80bb723ed2551624', 'BOylFXjjnOaHCL6wnwYxvQbkG84Wc7UlllkY3NKye6XaWtpTtAFpoPdpu4NwX7F/+ltCnvsYHq9rFDlEtMsTkhM=', '78NEFY6apCXSzpvYp1kF6g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 12:26:11', '2026-09-17 12:26:11', NULL),
(37, 1, 'https://fcm.googleapis.com/fcm/send/f15KN1QzH_4:APA91bG9IRhnUrZs2iChCHKkdqk2eegq_eo4Btjgj0fxCxtw-omfwt6BywVZYwcn__vh9pFUzRcT2geyGA6capiYfq-aIpqNMD1EKRa-vV-JWi8NU73YOyw5ogFdAJr6YFUvN1tMkzMw', '757c6f5bcf8d7e0d79470b21c17f242e4e4692ceb1ead770795659cd4915a821', 'BOpQ3OK8uw4QFGh8bW4FBdbQvosfgqe0yCRpeGxgB1g4Y7iZFjpqko1l1gwlWcI8NIBMxny9JxxmscydIaGTG+8=', 'gJ9P5Wc4uKiwP9qrumP3+A==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 13:18:03', '2026-09-17 13:18:03', NULL),
(38, 1, 'https://fcm.googleapis.com/fcm/send/fncaRIbQ1oI:APA91bGJMBFp_zt8n70LX-C1fM9H0ro_rRnusq-zVqChu15aaVrQ5KOtovVs_qO4cN0Z4Z2o9j6lf8eJx074HtcXxzVNDfPO-EolvuCWSQwL7Cpn4MZg-vtCmLtoBBNFXZ1-PfleaiRj', '0c08ab92519ab5be980c8024a5e3273e7b3aef75d01034e3560bc18f91478fe4', 'BA0HjfVNZdo0RSLacFMT0E0VF4oaRNrFb1kyyYaOI1GQGetj5t9ZCen5XsPohBLD7+i/rB+95i/IwaGUuVtdvoM=', 'mM1zY0K6K653cVrB0OLsEA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 13:18:03', '2026-09-17 13:18:03', NULL),
(39, 1, 'https://fcm.googleapis.com/fcm/send/d7UWl36yiqg:APA91bGhMmJRQ--PNJSX66Et_03jpniTgZzuMtH4hOZJHoAmWOtjEUU_UJzfW2u_bL2A8jYaeED2f1cYsAY6WMcp8He0a8JqZPG29u5e0-12d4_F5y5u3QSycJZDdL8DDSqSsBwIX05c', '9b73bb07b5415ea34db9c2b2cb4de526c3b51f25b6e59427696b99deb5310cef', 'BJj89S+Pqpx8jQ+qW+rq7+VWCzXuLn1UViAAoBReO1UehOx7Y0SWn4JxogU6xdbSFlLFdgH+QVWPdjcOBhgZctw=', 'CGBW5tVG9DcuxLT9F4+0UA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 13:46:59', '2026-09-17 13:46:59', NULL),
(40, 1, 'https://fcm.googleapis.com/fcm/send/dXevW0rDVzg:APA91bGmSmzlQYEYV--TxPIVd2aqOudP1frxsG_c3waGyS5uj14ewsbZIXHJoKtwJQahNnAeezk0_ox1LV1ZFQvge19lm0VB_Expl3Ca0-nMAg-p1cc4B5mrZEeHkgZQHtdSep8-QigE', '7d9f6b94379ff7fca48266670d00d02d533801f61a9b41adae38b4a381853026', 'BEjQXn5BxG+ulN/Yf1zb+uR+vfirOKnfpN2Dn1D0ihW5qmNJk5zpRD9Uh6qXwY7Kd011Rh+hQxi/HSAvJjJszl0=', '4eHiZ7FkUwvKv+h1bl4LPw==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 13:47:55', '2026-09-17 13:47:55', NULL),
(41, 1, 'https://fcm.googleapis.com/fcm/send/cRypCnUrv9s:APA91bG9wkQ2LvSrS044A6xfl5BHfop5d3CCorW-5v6ZKm-4W-OgOxT7s2bicd0g92ZXWOAbaxlo7jVgGOImh2_1ygSpQYlrek0dl_A5AuoqWN8trDvRh9n292hPc2q6_LrXwMXyLPWv', '8b7bf5c8bfe2d4ecfe22c6faa25df10df37c355832d6e1547a53cc8fbe33811b', 'BGD/OLrDnbMvueHyqG+8egpn8TSqfI77s+8+KZaSXD/QOaG1iVVeajiRjaPTlkqApI4ItNOsMRJ6R5BPV5ky/O0=', 'syIS4Vc9fnqhauI6+6l/Wg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 13:49:59', '2026-09-17 13:49:59', NULL),
(42, 15, 'https://fcm.googleapis.com/fcm/send/flmpGPL2uhY:APA91bE4dEgyoXKrC22OgbBj-8peD7tiiG-jkU9sDZTKt4Nq_MSSDK8KLCPjAbs7GC_XUqtrVKj3nUEXbpnt-vdboICwjh2jho7SLKLSfvC-shXuGMwqyWpoVyTiD01FelkHMDIIQiYl', '5e8a37e9b3972cfefd83217a4f4acab6cee788c09d790303830abb13d2dd98e9', 'BJkPSBFiUUdmggI+1/hRKMGqN+l66I79TH0WnmQRnNiIVEKkC7Oav6zQlkIOo/5PWhDt2aOysb1oDH/JroA5/0g=', 'JCWlFXXxLPWW9n/o7HmldA==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.49 Mobile Safari/537.36', '2026-09-17 14:01:37', '2026-09-17 14:01:37', NULL),
(43, 15, 'https://fcm.googleapis.com/fcm/send/cljSb0UrQQY:APA91bGrHo6KZv5MwxhzOVcPsw9CfYj5WjPpsKOKzMr1qjaI3cQ6VFB1_MJmiGgGaRuhu9yG7NTMADLcZnYYVWq_lSAEwkg42T_qAWWNzXkFl06M98UrnzBXOjFUBrv9H6CohEixjOuS', '3c027c98ce1a0c62d14216f9e0427fc35167ad2d5735443abfa0faa85a0caa40', 'BKjP81pC33Jeaz7kcNNjtNso971Vs7dLV7SFtNd9MQjMwxvUztABge7/RereTSpbKAbWr83z1sJvSZPK7iWkfC0=', 'Khmf9MuWQ2LVEnvCHs6EQg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 14:01:55', '2026-09-17 14:01:55', NULL),
(45, 15, 'https://fcm.googleapis.com/fcm/send/fTJXdMuGQFA:APA91bGqXdTuH_qQxeDSNzVuci5SIkCntD4QNNgKoO8kaKvL3RJCBRnsPTt2aBaBJFiGBXA98RtXjuG9VR0LimtiltEOEotZpbOCxuRhIrQAHzuU0ww2xlPWUPIPEyQCvG8syfOiKNeX', 'a78e57e4df3b43250c3eb83081f071d0b9ddbdbbebd65cb4ed91b6ca9e804ef0', 'BOenDa3I2WZi5lK237HlvdTMbayxccu5gz6tolOPVyUULWw/rKgTRyG+a994ByGXTmkDhKRosTl9N2XKPdGMKog=', 'OS3fNLsGVnu/519SIq1cYQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 14:30:52', '2026-09-17 14:30:52', NULL),
(46, 1, 'https://fcm.googleapis.com/fcm/send/cRpVRRNfvYc:APA91bGO0OCtupGeHgQM2FoVw-_roiHtiA9m1cqAh-6GFpfpP-MUYocNinK527CqeNs8KpRwuB_345tpRtOvIcyuXKd2rMdhXijBjuFdYXYgcHKXmGx5lQwKcaxnHn7j--r3DBY5Ci1z', 'bac2f915d2db161af2d6060178e2ce9eed5d1696467fb9e8324f8ec40be91d4e', 'BKb3NrDYw1xLhY+FtqmpAxyS7zaZAaecpfKpKnLa24Or4hq7MmiCzPK17o9+HFuN2kviJcbUFiBakiG1hfiVio4=', '8iE8aXuZ00ytniEQE2i/4A==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 15:01:56', '2026-09-17 15:01:56', NULL),
(47, 15, 'https://fcm.googleapis.com/fcm/send/fRWDVMp5Y00:APA91bHXg9w2bBdK9NSLx_vG0Ps0BBzGe68R0IsCYGstEBggBJ-ELSNNKlTc0ENYR_0Aw9luEE4W9w6fZAukWJZ6gFUc9IR3y-1hlROTACxNZ-byoSlvtRi0c0IU9170f67z1uOB68ak', 'd86e7aa8547260c7cdd6a145eed1980fe56e7aa2f931158c844e291c3d7a3fa6', 'BBGHSY/4r7YPIzlR6y2ujgEPbZ9FKBAgLjXCE9EVW4gaFqsnzNoQB2FsiFRzI0FlbA3d1SAsiW0IgXtSnJ1Zd90=', 'QyoEscHN/MRecDpQU0Y86g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 15:58:43', '2026-09-17 15:58:43', NULL),
(48, 15, 'https://fcm.googleapis.com/fcm/send/dWl39lQxFs4:APA91bEnDKzXmktii3yoEb5M7M8Alr_Sz-HDJdULRQJ_mcd8CHXWX01M7YPvAjrRBQ-1o1MUpghAWhzvoZt3p3mpbaNQwxEb6SSCmuThhh9wmNxTHyYdC-cRhEX1AGCJwp2r14kvSMYx', '7d3f7baa5081b52b5c54bfc3292b3d1e67b7eb98b5f67bf5fbc47c6091e2fd7b', 'BC1xmGiEeuPTLQlhocRfFfLcTqcb80JG20cdh8hbvWGaSBR3+R/XE0AjKYKdfr8aWss1cXksDGo0Btcf66dX7v8=', 'cbYuwJmsnv5PX9Sb5aek4g==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 16:00:27', '2026-09-17 16:00:27', NULL),
(50, 15, 'https://fcm.googleapis.com/fcm/send/fCFVi_YTCy0:APA91bHT6hs-NKO5UmLrJyo5s0-7_WwLE5gfWEt-Cu_7VIGCFgNDZ-Bvv_v02SH1MYOR2xlvtasi0T1_jUlHmQoAht2dOJs6RFtqNExbYNfM4EM_gVaFqFNzSHl-py9VYKeGyo0wkDHS', '6d99402a28623cf2105fa4e5a3b514cbe9d5dc374dbdee9a267e0db5b6d41fa1', 'BIlUqlQ44BnBU+Ksyldo+3oLZLoBiW0S0NFUt1SuAIR8qn68N1QumKU6wnvd+M/o0SS0LbkG+Xe2d5tfSh8pEY0=', 'vqA+Y5pKbFr3FHmImrFEIA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 16:01:05', '2026-09-17 16:01:05', NULL),
(51, 15, 'https://fcm.googleapis.com/fcm/send/e1Lex02qnF4:APA91bF0A8h9WrrHF5q9iHiQj08YH2hTdS_qKnFkT27B6_NUOBiK8KkWAi8ysY1OysT5G2NCtpwh2ztp92omOfGGALOj62YYmM56_vJsMGaA3NfU0pL5NMPlmUUJX1kn4WbYqW41aTVV', '1013f2775028ffe4fa1358b9913999d467cbc130aa8f7801cd2b747b8db97aec', 'BGsaM+ZurIuMePKHW1GQ2CSXXK/i9Q/fVgg53XA6rSo9tV52X+H279HB7cucUmjeor5CEutVwl3Q00MV/YgyTOw=', 'q/GUPLySrTB5lrDf7Dq74Q==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 16:01:05', '2026-09-17 16:01:05', NULL),
(52, 15, 'https://fcm.googleapis.com/fcm/send/fzJ-RHIW5U4:APA91bF0Fz6oKwnp9YZ2ezbHDIIugDde00RGJAHgWHAH68wezAMUWmuRbnV8actbMRgl3J-q8MEoXknbqf8x6v6Zcw0EwrufsDFO8s6sUnoVVmfyIU2E0n1SUkwjYvlPnL3pBB7ktC5D', 'b7a63c4abfb72cb05930d1e6b45b4542aa2f041e1a04fc00e3ba560c65a1ae59', 'BHtwL8tA1GV+v+Kl6ZYF9vWiOnqHbPLZJrZO2iW3JyYjgkQPfdwNEGi3uvzd8ATSSByh/47Q6jkWa5Vocti5ZIA=', '+rOahOn90V8TxOYpiJuUOg==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 16:14:08', '2026-09-17 16:14:08', NULL),
(53, 15, 'https://fcm.googleapis.com/fcm/send/eZsdD0pKaOo:APA91bEyuJyBuJHSOJFjn70fKcHNCaC2386hqByrVdH0pv_mRynaUhD_cdldiSBUr_xePXu3dCW2NmtFrpNpKen3slzousTIyYwDb939MnCtUs_3qe8tXDFdAa-8bdoJgh5ems_5rWXG', '460526da482b038438157e44500ded6cdfde629061595a1670acf994857cfae5', 'BMUdO4Na7gTMY5qAlfDyB83XvxYu77rLPDhCSBqgh1gKCx6+qCkbj9nZqbAmP2hWjUinnMAjyHZSSphsdF650Dw=', 'dMUKRsBJZPcfh7SGu/gXsA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 16:14:30', '2026-09-17 16:14:30', NULL),
(54, 1, 'https://fcm.googleapis.com/fcm/send/e_yaKP-Czo0:APA91bGXSzMrQZ_2hgmZ9dIcqRf57eIa3T2iUqV7W1vsD3rdBrZ4DkATuU48qmnNf43WSTARU77dmMsjW_4RIyZWuXODwYqjC6NUKiMhwgxyjYU_Ovr8U1uY9VX8u_hrKZ7RccaxozYl', '4f5ec3f041c74a7011414521012b2178ee9762642ee053e730018fec16ba567a', 'BMg4h5Ff/E6BLAmv/xJ3zr75kYZ9EKHp4zL6MTfMsGSUUiVl4iwoW6vnUvoYe1HJ62YEwI9z6QvzAneaz4DAzN0=', 'tWh1cbWQ6tMjjWaYXnDXFQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 20:14:36', '2026-09-17 20:14:36', NULL),
(55, 1, 'https://fcm.googleapis.com/fcm/send/fXJIYXeGULU:APA91bHLwNAtbaJCEElwb_Ke_4V9h4FQ4VwK8C3fDe5UkH816hGpKcwHZVD8Z_Da094TgbwxCHrTvqUGL-BDBrcWM9JxNTtMPULjuTamex-XnLJgm43MAHhxXnCQrOoP1C-zw9dS7458', '37f3926c7473fd366607c468cc5a43806ac1c0decad2c900b717f7ef88e6d60f', 'BF5A3uSgn8ZeC+JihnG9oOPa2TiNnBundHAX6XMbRTmyyQ8qlsCXD8vS9eTpCN8gHf+G2auIgAM1mC477Kho+ho=', 'W/Q3czpziK8sdAP+lShCvQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 20:14:37', '2026-09-17 20:14:37', NULL),
(56, 15, 'https://fcm.googleapis.com/fcm/send/er6fPrYw0Qc:APA91bES2VfjH9FD-DmnymcY4NJHt-kdHoAFa-mEUGoG-Pj1F1Wt2JG3KTKd_DSGwSnhnPuY7ZAHHHRMSQYX99o9nqq7RCjvv2amXcefESHN-yKes5YG6SL4m32K7V7gNkUCS-Vr8wPI', '50cb9e1587f4b0922910917c29d256fed6419087681dc2f7eb12cc7141f983d6', 'BLHNsEH35r88gKmbyNJi0sJekPPyO/VCN9Bb8WcjmA9BxXFaEttzg5/htwwMc0Qz0oFLixx7Ra9B0wKtRkCIAPk=', 'XSngchnnMEuNYAHz2ezFYQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 20:24:22', '2026-09-17 20:24:22', NULL),
(57, 15, 'https://fcm.googleapis.com/fcm/send/fRUawNuErYI:APA91bHrwPlWzQVUryPs9r_uri_UxvA8cudesjg8i_yViPkb5-FW4hQ_w5tuqsjqn3hAYoY21ZAkVHt1ICIPVJK7PD2wiYBN-v-cIceQA75pRpKTygQp2JpDabenzw7VF563_vTX0qR0', '2968aa13e02ecdd541c068d244477ed1e119bee33e186a377992bfa1725fdf73', 'BIVCuwpmLwDxhlmoeC939KOQKKFiO4v0tQqGBB/YAv+UfACKcb8NWYEdbzSgnKTuUP8mCdFRB5iYCQrlmrLv7z0=', 'msmUD4OFda7J2aoWrs4Z4g==', 'Mozilla/5.0 (Linux; Android 17; SM-N986B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.49 Mobile Safari/537.36', '2026-09-17 20:25:49', '2026-09-17 20:25:49', NULL),
(58, 15, 'https://fcm.googleapis.com/fcm/send/fyUtwEgKWJU:APA91bE6kG3x6VJoDkJZvNPSZPYM8uFehvy6Mt3yUYL_E56EZY2elpDrAqeLuUzdFsVqop8hVxqTLVr6568IH5-ZXg78-VDa6AhSnXX9IiZzeJGuUXYmliv7BbqmTGQ8Br9hr9CUSrUB', '3f492e35d6de6ddd47c35e0590aa565f65227fd0360f30f084b28e6e332c90e3', 'BFNDPcadSWJa2p+Km/TDYrIkQo6Cl87ej7VEdNwokgdGqIj3R4+PLZpCH94dOalX5MmVOYB8NYauHuQzY4pXkbw=', 'ndpCIWjzRQyxpHQf7y/oxw==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 20:26:03', '2026-09-17 20:26:03', NULL),
(59, 15, 'https://fcm.googleapis.com/fcm/send/fhGWF1rkNV0:APA91bEoFgniwj-21h22o3xavHKN02v0XBYVwg2eBewEcv2STPRQ-g-22YeOmjRldpJregO7q6QGE-4k9TBehOw-khJYU23ROGT6BBp3gDROfCeRxfjRkiPSYtitddka5HgfWinDDfj4', 'bc21166a326230a246b6baf3f2f9e9a796d5c97339daebdc2e8c2cb62da84cd8', 'BFTFa3L2VvBa7/VbzrmC1i1w+F5Dg1NuIWrnVOME+fuWXP25TYNMpUZUhjl3HIFybponnMsi93AJLPp6A81UqYM=', 'muhuSIJsAw13mbvryPEmyw==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 20:28:01', '2026-09-17 20:28:01', NULL),
(60, 1, 'https://fcm.googleapis.com/fcm/send/f-Hyr65FAaE:APA91bEVVzMGVy9CQYMtwJiqNNshBQHYck_kOLt8QdJ4AN3LcpHCUnbzoR7CAS_n2gc7w6Ym1-Qers9ipqeDPsuQ2wUcKO6Kd3mOq8eIwRyNvgUYSqRgSdbc4QLVilno-bFUFjz8XP99', 'ead9abb25fdb90cd75bb6ccdd38fd68e061256a2cc2a4639ba23414d6010ed2a', 'BND+cGSjdrA7SbkLuLtIbqAKZI8Yj55rBIHIS/3BcqWFogTjDvykD7kG0Hd7vRHq6bmANBQUPiWepCjbMEeCJbU=', 'Q40Jqzu4w1pK8HtnNDU3Ew==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 21:13:25', '2026-09-17 21:13:25', NULL),
(61, 15, 'https://fcm.googleapis.com/fcm/send/exr_0e3AEQw:APA91bF3RvPIUFA3VlzFZooOrDc5phV1lVFArEWRKo5tzl0MflGsTMvVGKDyXThNv9iYKubqkDwzornOM_xyJ7WMd52aV_sOR9jbmZoHqeoR1lApzOfL12q8Hab7dImtT04z7OsylYps', '5affd0ed4fc95835e5a7e42c24de5c413d825135594b588703f84d145b31f065', 'BGmFFZEaXEVK+ttV3faNp8MR+7iBUG8PVmdPuM3TeIwyprOq/WbPDNsBvqKnhq9Jh4Jbm8805yus7biEOG9G7d4=', 'HLkV+AAJRoCNThPLqHk1TA==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 21:19:57', '2026-09-17 21:19:57', NULL),
(62, 15, 'https://fcm.googleapis.com/fcm/send/dL1UfaAp95k:APA91bG5IY0Fxnw_xXaguK3QjlA0911IrhOaeTyOffmLsPFJSfcYQ4TILJMDdvauUK3JrCnhfCQLq7gcpTK8lTI49AO0bXAT7EaLtZDU4632ORwbp8sLSZxjkOQAh1cjXuSJZC5BnIre', '57fddc1dbfc442b75620f0342849373c5a60c82fbfd56cc9fd9bf87f26fd64dc', 'BMdcpQH46bM73qMb+45M0nf0WgW2RDKwntlZ4KC9uipJN0ddjA09qY26YvLFLeY2Js8dtCj1C5vlnf0UgNd0B90=', 'SeCdvlpe56kJG4j2C6xBNQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 21:20:00', '2026-09-17 21:20:00', NULL),
(64, 15, 'https://fcm.googleapis.com/fcm/send/ecgl-GJDr38:APA91bEOewhRtW-001i1XmtAiL5F-YA1S_pN0b4eaX8VihC5uRpnbQ-qP1VPhd0SV308qfaSLjOeZQ80S-wC9qcPbWh-cmnGf2ZLTnuMGfQufyjoEzuvsfppDN8jOEfbpLLKdRs4R3nS', '7227aa3dbcdac58d46ea19d6b9c78103562b3c0f9f37c87ae67c3650714022a9', 'BD7J3fgrbUe68hXCqlTsNWD1E3sA6wYH0MgziVKleL7Lh9stm+cgtPVhL+KAe2Qh9oQNPvYElypjHE+yjeqRXb4=', 'js4iAL9sXZYV1KhRp21CtQ==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 21:20:30', '2026-09-17 21:20:30', NULL),
(65, 1, 'https://fcm.googleapis.com/fcm/send/cd_V0OtW0T8:APA91bHz4ZJcZj_zSjqFHZka3y7JgxMi2-OdxVfvSFWRVWFO0-nNuT_JHHHyKSVW2Wy61ms_g2ddvwYxT_G9onCVmPKuCa0iCgiuWl6bfYZmQmQjzXTIqjsn6VqPccgglcZs6L-rxUET', '2f9e1dba99c82e37a96511337603e8f023a736a338b22d53ce9c552b7997dc97', 'BLDXju1gqFk6fi6adozYrgKVPLwCLPFkBagS8WsA6sP4j3l//hNp+oXn6+Z7zcV9OKrrN6dij9VGwQa8N3MaXWI=', '9bZoD8nVkZ5lDt3fAAlQ0A==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 21:54:00', '2026-09-17 21:54:00', NULL),
(66, 15, 'https://fcm.googleapis.com/fcm/send/eN5nvTR77Ug:APA91bHTLF9jmU__ILzF4hu-lSRmByhfqcpNU_nUC8olgoUn5nhJRF0doXpsN1WOCq5XTqK8XYlAvFYb-SWKVGYzyjnuyvgTaore-5IUpuEO3e77qOKPHnlaFEoEohn9Y7AYNr6Tah2l', '4780e7b749c808e78905c21026091157843c4968b05ba6fab0be57bbfe9f6fcc', 'BMdjNpJ+Ra2ZE95sEXhxVk7aWNwC7E97beUDg8BwSXYIgP6Cuz6LzOeW5yDRkVtT7basmbbLLzWHNUIeh+kQrlM=', '1556vDcP3jTJioUMJeYv8A==', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 22:37:10', '2026-09-17 22:37:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ethiopian_year` int(11) DEFAULT NULL,
  `semester_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `name`, `status`, `start_date`, `end_date`, `created_at`, `ethiopian_year`, `semester_number`) VALUES
(1, '2018 ዓ.ም ሁለተኛ ሴሚስተር', 'active', '2026-02-16', NULL, '2026-05-08 22:30:45', 2018, 2);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'admin_name_1', 'ተስፋሁን ባይህ', '2026-09-17 00:29:38'),
(2, 'admin_phone_1', '0943854325', '2026-09-17 00:29:38'),
(3, 'admin_name_2', 'አቤኔዘር መባ', '2026-09-17 00:29:39'),
(4, 'admin_phone_2', '0985739965', '2026-09-17 00:29:39'),
(5, 'admin_title', 'የአጸደ ትጉሃን ትምህርት ክፍል ኃላፊ', '2026-05-08 22:30:45'),
(21, 'vapid_public_key', 'BA4gIyAmzQvG_8pNcITvybi9h1AGicohPofXWtShAm3uAwFLigPRHtn9c26idCM-mmTNoQvbxlpTNnVIeHiOEwk', '2026-09-09 20:11:48'),
(22, 'vapid_private_key', 'DE8bLbSto2_cFFkBgYH8Vztk3AQ8Nis3nUtYENRwyTM', '2026-09-09 20:11:48'),
(23, 'vapid_subject', 'mailto:admin@atsedesundayschool.org', '2026-09-09 20:11:48'),
(24, 'youth_can_create_plans', '0', '2026-09-17 00:30:54'),
(25, 'youth_can_write_attendance', '0', '2026-09-12 10:14:08');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `class_id` int(11) NOT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `enrollment_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `current_grade` int(11) DEFAULT NULL,
  `academic_year` int(11) DEFAULT NULL,
  `promotion_status` enum('promoted','repeated','new') DEFAULT 'new',
  `needs_pin_reset` tinyint(1) DEFAULT 0,
  `student_portal_enabled` tinyint(1) DEFAULT 1,
  `local_uuid` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `class_id`, `parent_phone`, `enrollment_date`, `created_at`, `current_grade`, `academic_year`, `promotion_status`, `needs_pin_reset`, `student_portal_enabled`, `local_uuid`, `updated_at`, `is_deleted`) VALUES
(1, 'ዲ/ን ናትናኤል መክብብ', 1, '0920055496', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(2, 'ዲ/ን በሱፈቃድ ደጀኔ', 1, '0914362110', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(3, 'ዲ/ን ኪዳነማርያም አስማማው', 1, '0911449065', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(4, 'ዲ/ን ሚኪያስ አንጋው', 1, '0988240631', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(5, 'ዲ/ን ዮሐንስ ወርቁ', 1, '0988128696', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(6, 'ዲ/ን አቤል አስረስ', 1, '0922862993', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(7, 'መሰረት ዲባባ', 1, '0992658749', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(8, 'ትንሳኤ ስንታየሁ', 1, '0911647271', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(9, 'አዲስ ዓለም ዘርፉ', 1, '0901744754', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(10, 'አርሴማ አስማማው', 1, '0911449065', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(11, 'ቢታንያ ታጠቅ', 1, '0910031943', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(12, 'አርሴማ ዮሴፍ', 1, '0910677684', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(13, 'ሙሴ ደረጄ', 1, '0923119204', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(14, 'ዮርዳኖስ በለጠ', 1, '0921310326', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(15, 'ሩሃማ ጌትነት', 1, '0913274115', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(16, 'ሩት ደጀኔ', 1, '0969145236', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(17, 'ሶፎንያስ ሚሊዮን', 1, '0911988826', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(18, 'ሚኪያስ ሚሊዮን', 1, '0911988826', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(19, 'ኑኃሚን ጌትነት', 1, '0913274115', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(20, 'አማኑኤል ዳዊት', 1, '0926807402', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(21, 'በጸሎት ታረቀኝ', 1, '0912166711', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(22, 'ክርስቲያን አበራ', 1, '0911710179', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(23, 'እስጢፋኖስ እግዳወርቅ', 1, '0913062894', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(24, 'በአብ ታመነ', 1, '0911709471', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(25, 'ዮናታን ኃይሉ', 1, '0945249533', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(26, 'ተካልኝ ታደሰ', 1, '0910001869', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(27, 'ሶልያና ደረጄ', 1, '0936564486', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(28, 'አዶናይ ፈቃዱ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(29, 'ረድኤት ኪዳኔ', 1, '0920498048', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(30, 'መክሊት አስናቀው', 1, '0913729005', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(31, 'ህሊና ዳኛቸው', 1, '0913713324', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:26:45', 0),
(32, 'ምሥጢረ ወንድምነው', 1, '0913322306', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(33, 'ኤደን ብርሀኔ', 1, '0912494467', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(34, 'ናትናኤል ወንድምሁነኝ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(35, 'ዲ/ን ሚኪያስ ርስቱ', 1, '0919196015', '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(36, 'አዶንያስ ጥጋቡ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(37, 'ኑኃሚን ሽመልስ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(38, 'ፍቅር ፈቃዱ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(39, 'አብርሀም ታደሰ', 1, NULL, '2025-09-11', '2026-05-08 22:30:45', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(40, 'ማንደፍሮ ሞላ', 2, '0922810409', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(41, 'ፋሲካ አዋይ', 2, '0970417843', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(42, 'ሀና መዝገብ', 2, '0973995261', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(43, 'ቤተልሔም ዘርፉ', 2, '0901744754', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(44, 'አልአዛር መስፍን', 2, '0910501967', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(45, 'ህሊና ደረጄ', 2, '0936564486', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(46, 'መባዊት ወርቁ', 2, '0931055729', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(47, 'የአብጸጋ ዓብይ', 2, '0936564486', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(48, 'ሀብታሙ ምስጋናው', 2, '0912743283', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(49, 'ማርሼት ጌታዬ', 2, '0988073656', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(50, 'ጫላ ማሞ ከበደ', 2, '0945249533', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(51, 'ቢኒያም ዓለማየሁ', 2, '0935192919', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(52, 'ሄርሜላ ሽመልስ', 2, '0919791293', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(53, 'የዓለምወርቅ ዋኘው', 2, '0970702814', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(54, 'እዮብ ገረመው', 2, '0963010376', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(55, 'ጥሩዕድል ዓለማየሁ', 2, '0997118626', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(56, 'የሮሰን ደረሰ', 2, '0941729318', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(57, 'አዶኒያስ ፀጋዬ', 2, '0913090290', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(58, 'ሚስጥረ ሸዋይርጋ', 2, '0940852286', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(59, 'ቃልኪዳን ታደሰ', 3, '0903255059', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(60, 'ፋናዬ ማሙዬ', 3, '0985130365', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(61, 'ዳሰሽ አደራ', 3, '0927584977', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(62, 'ማራማዊት ወንደሰን', 3, '0975864268', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(63, 'የአብስራ ዘላለም', 3, '0932152046', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(64, 'ይዲዲያ ዘውዴ', 3, '0961428403', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(65, 'ብርሀኔ ኬኔ', 3, '0937406397', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(66, 'መስከረም አየለ', 3, '0989083787', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(67, 'ሚኪያስ ሙሉቀን', 3, '0916283212', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(68, 'ብሩክታዊት ሲሳይ', 3, '0913586932', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(69, 'ዓለምዬ ምህረት', 4, '0962980443', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(70, 'አማን ይሁኔ', 4, '0946133236', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(71, 'ሜላት ተስፋዬ', 4, '0906171926', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(72, 'ፀሐይ ዘነበ', 4, '0942078245', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(73, 'ዳግማዊት አስረስ', 4, '0967942081', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(74, 'ተስፋሁን ባዬ', 4, '0943854325', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(75, 'አቤነዘር መባ', 4, '0985739965', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(76, 'ደስታው ባዬ', 4, '0918425987', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(77, 'ደሳለኝ ጌትነት', 4, '0989316448', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(78, 'ሜላት አባዲ', 4, '0908722397', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(79, 'ቃልኪዳን ታደሰ', 4, '0988458779', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(80, 'አፎሚያ አስራት', 4, '0955990224', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(81, 'ገሊላ ፀጋዬ', 4, '0984708896', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(82, 'ሀ/ጊዮርጊስ ሰማኸኝ', 4, '0987081669', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(83, 'ብሩክ አንተነህ', 4, '0988206474', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(84, 'ኤርሚያስ አሰፋ', 4, '0939655127', '2025-09-11', '2026-05-08 22:30:46', NULL, NULL, 'new', 0, 1, NULL, '2026-08-26 06:52:32', 0),
(85, 'ናትናኤል ሰለሞን', 7, '0911223341', '2026-09-11', '2026-09-11 14:57:58', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(86, 'ኤልሳቤጥ ተስፋዬ', 7, '0911223342', '2026-09-11', '2026-09-11 14:57:58', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(87, 'ዮናስ አሰፋ', 7, '0911223343', '2026-09-11', '2026-09-11 14:57:58', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(88, 'ሊዲያ በቀለ', 7, '0911223344', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(89, 'ዳዊት ታደሰ', 7, '0911223345', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(90, 'ሰላማዊት ግርማ', 7, '0911223346', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(91, 'በረከት አበበ', 7, '0911223347', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(92, 'ማክዳ ካሳሁን', 7, '0911223348', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(93, 'አማኑኤል ወርቁ', 7, '0911223349', '2026-09-11', '2026-09-11 14:57:59', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0),
(94, 'ሃና ደረጀ', 7, '0911223350', '2026-09-11', '2026-09-11 14:58:00', NULL, NULL, 'new', 0, 1, NULL, '2026-09-11 14:58:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_logins`
--

CREATE TABLE `student_logins` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `pin` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `dark_mode` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_logins`
--

INSERT INTO `student_logins` (`id`, `student_id`, `pin`, `first_login`, `dark_mode`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 1, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-09-12 12:17:39'),
(2, 2, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(3, 3, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(4, 4, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(5, 5, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(6, 6, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(7, 7, '$2y$10$Ivc/AONJoV28EYYvogfrzeCg1kOnqY4vBJpsqAE.K3j/jUc9DV5Iy', 1, 0, '2026-05-10 19:35:55', 0, NULL, '2026-05-08 22:52:18', '2026-05-10 19:36:19'),
(8, 8, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(9, 9, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(10, 10, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(11, 11, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(12, 12, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(13, 13, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(14, 14, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(15, 15, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(16, 16, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(17, 17, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(18, 18, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(19, 19, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(20, 20, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(21, 21, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(22, 22, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(23, 23, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(24, 24, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(25, 25, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(26, 26, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(27, 27, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(28, 28, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(29, 29, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(30, 30, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(31, 31, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(32, 32, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(33, 33, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(34, 34, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(35, 35, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(36, 36, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(37, 37, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(38, 38, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(39, 39, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(40, 40, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(41, 41, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(42, 42, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(43, 43, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(44, 44, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(45, 45, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(46, 46, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(47, 47, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(48, 48, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(49, 49, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(50, 50, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(51, 51, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(52, 52, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(53, 53, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(54, 54, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(55, 55, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(56, 56, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(57, 57, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(58, 58, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(59, 59, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(60, 60, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(61, 61, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(62, 62, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(63, 63, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(64, 64, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(65, 65, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(66, 66, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(67, 67, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(68, 68, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(69, 69, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(70, 70, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(71, 71, '$2y$10$w886JUzGn28lT/gfxHxSueuwuWD0i7ecYBEXMmw8kCh2lqWRutegq', 1, 0, '2026-05-08 23:14:12', 0, NULL, '2026-05-08 22:52:18', '2026-05-08 23:15:19'),
(72, 72, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(73, 73, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(74, 74, '$2y$10$ycD8I2flkQszj1N/PepmYu/gln5ARJTIbB/vKarMloGtBBox7Gue.', 1, 0, '2026-05-08 22:52:38', 2, NULL, '2026-05-08 22:52:18', '2026-05-10 19:21:27'),
(75, 75, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(76, 76, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(77, 77, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(78, 78, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(79, 79, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(80, 80, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(81, 81, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(82, 82, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, '2026-05-08 23:09:45', 0, NULL, '2026-05-08 22:52:18', '2026-05-08 23:09:45'),
(83, 83, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 1, NULL, '2026-05-08 22:52:18', '2026-05-08 23:13:46'),
(84, 84, '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, NULL, 0, NULL, '2026-05-08 22:52:18', '2026-05-08 22:52:18'),
(135, 85, '$2y$10$5yp1n4.avhe9621sZqCxcuIzjKdOQNQpcRmU8GjpE3xb.ZVEwipoy', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:58', '2026-09-11 14:57:58'),
(136, 86, '$2y$10$G2ScXqe/9kvU6INK/NtDl.z6WXKUVXi3TChyc60vpFLVJcqhSGs.i', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:58', '2026-09-11 14:57:58'),
(137, 87, '$2y$10$YdJreXP9b3XIPUoLMAqk5.rB8jsPwyM/sfSMnAHDMdbpmDdtytrmW', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(138, 88, '$2y$10$2oTVvMpHWCAj20PSt0Kg.euWr3aJ3eFx/fCChdsD9XfKN1L5C4zUu', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(139, 89, '$2y$10$p61Z.mVzHzm3On4FvbYBueA1Z26RAhpWed76tdea7xuGkdimYyoU6', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(140, 90, '$2y$10$S2elMAeBZBI/jHF.HZIseO3.wQFQv70..KZCl/2L8h4C6a2CSamve', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(141, 91, '$2y$10$Bs/AgE2QRLnktuzRQOGxHOSBPjKz0xseADPN/3EUmvDbXhcl4eELa', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(142, 92, '$2y$10$yMao82ntIscUa5usH8zHjuEIhxh3fWbNyalu3qDhD9RzWkQiskXpO', 1, 0, NULL, 0, NULL, '2026-09-11 14:57:59', '2026-09-11 14:57:59'),
(143, 93, '$2y$10$MVk2oc2ey89lRfwk/60oneJFz.FMoMWopyjgAc2fiPSzOW/vjEJdq', 1, 0, NULL, 0, NULL, '2026-09-11 14:58:00', '2026-09-11 14:58:00'),
(144, 94, '$2y$10$PbBGbg/7IbJWZQGEjqUT2e2/MRYuMhx9yDcOHhfhX2a2bk63JpP5.', 1, 0, NULL, 0, NULL, '2026-09-11 14:58:00', '2026-09-11 14:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `code`, `sort_order`, `created_at`) VALUES
(1, 'ክርስቲያናዊ ስነምግባር', 'ETHICS', 1, '2026-09-12 10:14:08'),
(2, 'የቤ/ን ታሪክ', 'CHURCH_HISTORY', 2, '2026-09-12 10:14:08'),
(3, 'ቅዱሳት መጻህፍት', 'HOLY_BOOKS', 3, '2026-09-12 10:14:08'),
(4, 'መሠረተ እምነት', 'FAITH_FOUNDATION', 4, '2026-09-12 10:14:08'),
(5, 'ስርዓተ ቤተክርስቲያን', 'CHURCH_RITES', 5, '2026-09-12 10:14:08'),
(6, 'ግእዝ', 'GEEZ', 6, '2026-09-13 20:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_class`
--

CREATE TABLE `teacher_class` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `semester_id` int(11) NOT NULL,
  `locked` tinyint(1) DEFAULT 0,
  `attendance_locked` tinyint(1) NOT NULL DEFAULT 0,
  `plan_locked` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_class`
--

INSERT INTO `teacher_class` (`id`, `teacher_id`, `class_id`, `subject_id`, `subject_name`, `semester_id`, `locked`, `attendance_locked`, `plan_locked`, `assigned_date`) VALUES
(12, 15, 7, 1, 'ክርስቲያናዊ ስነምግባር', 1, 0, 0, 0, '2026-09-16 18:58:12'),
(14, 8, 5, 3, 'ቅዱሳት መጻህፍት', 1, 0, 0, 0, '2026-09-16 22:46:56');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_documents`
--

CREATE TABLE `teacher_documents` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','attendance_submitter') NOT NULL DEFAULT 'teacher',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `password` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `dark_mode` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `can_edit_marks` tinyint(1) DEFAULT 1,
  `can_edit_attendance` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `phone`, `photo`, `role`, `status`, `password`, `first_login`, `dark_mode`, `created_at`, `can_edit_marks`, `can_edit_attendance`) VALUES
(1, 'ICT ', 'admin', '0943854325', NULL, 'admin', 'active', '$2y$10$pMqNRg3WooVpEkzNhbIURe.k5buShXVBeQxWymynuBWhVMWOMLrGi', 1, 1, '2026-02-16 11:15:53', 1, 0),
(2, 'ዲ/ን ክብረአብ ዘላለም', 'kibreab', '0939883508', NULL, 'teacher', 'active', '$2y$10$aAiwXFkmAJLoUZN/tkSiWeIdFiceZ9uTR29gXlt/OgZnvwlvfvTSC', 0, 1, '2026-05-08 22:30:45', 1, 0),
(3, 'ዲ/ን አየለ ሞገስ', 'ayele', '0920425061', NULL, 'teacher', 'active', '$2y$10$SI7SBnho3VUViAVG5J0YS.CsOUIv2F/64kr.gScVkprK6aKXcr/Ei', 1, 0, '2026-05-08 22:30:45', 1, 0),
(4, 'መ/ር ዳዊት ዓብይ', 'dawit', '0921664431', NULL, 'teacher', 'active', '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, '2026-05-08 22:30:45', 1, 0),
(5, 'መ/ር ተስፋለም', 'tesfalem', '0965223351', NULL, 'teacher', 'active', '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, '2026-05-08 22:30:45', 1, 0),
(6, 'መ/ር ዘካርያስ ፈቃዱ', 'zekarias', '0923781476', NULL, 'teacher', 'active', '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, '2026-05-08 22:30:45', 1, 0),
(7, 'መ/ር ሚኪያስ', 'mikiyas', NULL, NULL, 'teacher', 'active', '$2y$10$IMGnOzGUuNbnU7tmKHc.yOynteh/PrBHEo1iYzWnlFGmV0rt0XaY2', 0, 0, '2026-05-08 22:30:45', 1, 0),
(8, 'መ/ር ብሩክ', 'biruk', '0969063911', 'uploads/teachers/teacher_8_1789681334.jpg', 'teacher', 'active', '$2y$10$9y.S4sse7JvWJuN.F5sc5ODg0mL4GoiA97Q2iSuY1zsGdZ1DT8HUm', 0, 1, '2026-05-08 22:30:45', 1, 0),
(9, 'መ/ር መሳይ', 'mesay', '', NULL, 'teacher', 'active', '$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG', 1, 0, '2026-05-08 22:30:45', 1, 0),
(10, 'የሮሰን ደረሰ', 'yerosen', '0941129318', NULL, 'attendance_submitter', 'active', '$2y$10$M2yDl7BzTpwcGX.xOzXNluZc3MWeo1yIcCwlMmP3qOui1jyr7Tt2W', 0, 0, '2026-05-08 22:38:51', 0, 1),
(11, 'ቢኒያም ዓለማየሁ', 'biniyam', '0935192919', NULL, 'attendance_submitter', 'active', '$2y$10$ru7D.VIJFaXEgQrI2tT/6.ZQ6Vxl.Ft26f.lE7d55c.S7CTYCEHXS', 1, 0, '2026-05-08 22:39:36', 0, 1),
(12, 'ተስፋሁን ባይህ', 'tesfa', '0943854325', NULL, 'attendance_submitter', 'active', '$2y$10$Fiw5lAqP0s84XXEtFOlL5eywHYaELbRZMcSam8O2KhMqTcjl6g19y', 1, 0, '2026-05-08 22:41:13', 0, 1),
(13, 'ሜላት አባዲ', 'melat', '0908722397', NULL, 'attendance_submitter', 'active', '$2y$10$8k0RxGW8yJ1aHt7E3ib2RePi/e8onf8nicGoOmAWqeifdx87QIEqS', 1, 0, '2026-05-08 22:41:56', 0, 1),
(14, 'ትምህርት ክፍል', 'ትምህርት', '0939883508', NULL, 'admin', 'active', '$2y$10$ImQ6owhkaYB2cUKTOs9shOT3SeI60NIgsKFbLTYfM5Pkt8n8sVBwa', 1, 0, '2026-05-08 22:44:12', 1, 0),
(15, 'Test', 'Test teacher', '0943854325', NULL, 'teacher', 'active', '$2y$10$HI7TSXGElMqZC7Oks09.A.q0lG/3IRtFC7she20.Vc0UcQz3lVmF2', 0, 1, '2026-09-11 14:51:45', 1, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_assignments`
--
ALTER TABLE `attendance_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`submitter_id`,`class_id`,`semester_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Indexes for table `attendance_days`
--
ALTER TABLE `attendance_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date_gregorian`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`class_id`,`attendance_date`),
  ADD UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `marked_by` (`marked_by`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_attendance_status` (`status`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_unique` (`token`),
  ADD KEY `user_idx` (`user_id`);

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `calendar_events_ibfk_1` (`created_by`);

--
-- Indexes for table `calendar_event_targets`
--
ALTER TABLE `calendar_event_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `cet_division_fk` (`division_id`),
  ADD KEY `cet_grade_fk` (`grade_id`),
  ADD KEY `cet_class_fk` (`class_id`),
  ADD KEY `cet_teacher_fk` (`teacher_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Indexes for table `curriculum_topics`
--
ALTER TABLE `curriculum_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grade_subject` (`grade_level`,`subject_name`),
  ADD KEY `idx_chapter` (`chapter_name`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_unique` (`code`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `division_level_unique` (`division_id`,`level_number`);

--
-- Indexes for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `lp_semester_fk` (`semester_id`);

--
-- Indexes for table `lesson_plan_versions`
--
ALTER TABLE `lesson_plan_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan` (`lesson_plan_id`);

--
-- Indexes for table `marking_schemes`
--
ALTER TABLE `marking_schemes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_scheme` (`teacher_id`,`class_id`,`semester_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `idx_marking_scheme_teacher` (`teacher_id`),
  ADD KEY `idx_marking_scheme_class` (`class_id`);

--
-- Indexes for table `marks`
--
ALTER TABLE `marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_student_semester` (`teacher_id`,`student_id`,`semester_id`),
  ADD UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_event_id` (`related_event_id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notif_user_unique` (`notification_id`,`user_id`);

--
-- Indexes for table `notification_targets`
--
ALTER TABLE `notification_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `profile_change_requests`
--
ALTER TABLE `profile_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `promotion_history`
--
ALTER TABLE `promotion_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `from_class_id` (`from_class_id`),
  ADD KEY `to_class_id` (`to_class_id`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `endpoint_hash_unique` (`endpoint_hash`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `student_logins`
--
ALTER TABLE `student_logins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `teacher_class`
--
ALTER TABLE `teacher_class`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `teacher_class_ibfk_1` (`teacher_id`);

--
-- Indexes for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

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
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_assignments`
--
ALTER TABLE `attendance_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_days`
--
ALTER TABLE `attendance_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `calendar_event_targets`
--
ALTER TABLE `calendar_event_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `curriculum_topics`
--
ALTER TABLE `curriculum_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=410;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `lesson_plan_versions`
--
ALTER TABLE `lesson_plan_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `marking_schemes`
--
ALTER TABLE `marking_schemes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `marks`
--
ALTER TABLE `marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notification_targets`
--
ALTER TABLE `notification_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `profile_change_requests`
--
ALTER TABLE `profile_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `promotion_history`
--
ALTER TABLE `promotion_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `student_logins`
--
ALTER TABLE `student_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teacher_class`
--
ALTER TABLE `teacher_class`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_assignments`
--
ALTER TABLE `attendance_assignments`
  ADD CONSTRAINT `attendance_assignments_ibfk_1` FOREIGN KEY (`submitter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_assignments_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_days`
--
ALTER TABLE `attendance_days`
  ADD CONSTRAINT `attendance_days_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_records_ibfk_4` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `calendar_event_targets`
--
ALTER TABLE `calendar_event_targets`
  ADD CONSTRAINT `cet_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cet_division_fk` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cet_event_fk` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cet_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cet_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`);

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`);

--
-- Constraints for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD CONSTRAINT `lp_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lp_semester_fk` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lp_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_plan_versions`
--
ALTER TABLE `lesson_plan_versions`
  ADD CONSTRAINT `lpv_plan_fk` FOREIGN KEY (`lesson_plan_id`) REFERENCES `lesson_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marking_schemes`
--
ALTER TABLE `marking_schemes`
  ADD CONSTRAINT `marking_schemes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marking_schemes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marking_schemes_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marks`
--
ALTER TABLE `marks`
  ADD CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marks_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marks_ibfk_4` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`related_event_id`) REFERENCES `calendar_events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `nr_notif_fk` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_targets`
--
ALTER TABLE `notification_targets`
  ADD CONSTRAINT `nt_notif_fk` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promotion_history`
--
ALTER TABLE `promotion_history`
  ADD CONSTRAINT `promotion_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotion_history_ibfk_2` FOREIGN KEY (`from_class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `promotion_history_ibfk_3` FOREIGN KEY (`to_class_id`) REFERENCES `classes` (`id`);

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `ps_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_logins`
--
ALTER TABLE `student_logins`
  ADD CONSTRAINT `student_logins_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_class`
--
ALTER TABLE `teacher_class`
  ADD CONSTRAINT `teacher_class_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_class_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_class_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  ADD CONSTRAINT `teacher_documents_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
