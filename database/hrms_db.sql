-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2026 at 01:49 PM
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
-- Database: `hrms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_correction_logs`
--

CREATE TABLE `attendance_correction_logs` (
  `id` bigint(20) NOT NULL,
  `attendance_id` bigint(20) NOT NULL,
  `corrected_by` int(11) DEFAULT NULL,
  `old_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) NOT NULL,
  `old_remarks` varchar(255) DEFAULT NULL,
  `new_remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_correction_logs`
--

INSERT INTO `attendance_correction_logs` (`id`, `attendance_id`, `corrected_by`, `old_status`, `new_status`, `old_remarks`, `new_remarks`, `created_at`) VALUES
(1, 5, 1, '', 'present', 'Attendance requires manual punch correction.', 'Attendance requires manual punch correction.', '2026-09-15 11:38:20');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_punches`
--

CREATE TABLE `attendance_punches` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `punch_date` date NOT NULL,
  `punch_time` datetime NOT NULL,
  `punch_type` enum('in','out') NOT NULL,
  `source` enum('manual','device','import') NOT NULL DEFAULT 'manual',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_punches`
--

INSERT INTO `attendance_punches` (`id`, `employee_id`, `shift_id`, `punch_date`, `punch_time`, `punch_type`, `source`, `remarks`, `created_at`) VALUES
(1, 3, 3, '2026-09-15', '2026-09-15 20:38:00', 'in', 'manual', '', '2026-09-15 10:08:25'),
(2, 3, 3, '2026-09-16', '2026-09-16 06:15:00', 'out', 'manual', '', '2026-09-15 10:44:37'),
(7, 4, 1, '2026-09-15', '2026-09-15 20:47:00', 'in', 'manual', '', '2026-09-15 11:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `status` enum('present','absent','half_day','late','early_out','holiday','leave','weekly_off','manual_adjustment') DEFAULT 'present',
  `total_work_minutes` int(11) DEFAULT 0,
  `late_minutes` int(11) DEFAULT 0,
  `early_out_minutes` int(11) DEFAULT 0,
  `overtime_minutes` int(11) DEFAULT 0,
  `approved` tinyint(1) DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `employee_id`, `attendance_date`, `shift_id`, `status`, `total_work_minutes`, `late_minutes`, `early_out_minutes`, `overtime_minutes`, `approved`, `remarks`) VALUES
(1, 3, '2026-09-15', 3, 'late', 577, 23, 0, 0, 0, 'Calculated from punches. Late: 23 min; Early out: 0 min; Overtime: 0 min.'),
(5, 4, '2026-09-15', NULL, 'present', 0, 697, 0, 0, 0, 'Attendance requires manual punch correction.');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete','approve','reject','cancel') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `entity_type`, `entity_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'attendance', 4, 'delete', NULL, '::1', '2026-09-15 11:38:15'),
(2, 1, 'attendance', 5, 'update', '{\"employee_id\":4,\"attendance_date\":\"2026-09-15\",\"status\":\"present\"}', '::1', '2026-09-15 11:38:20'),
(3, 1, 'employee', 7, 'update', '{\"employee_code\":\"EMP006\",\"status\":\"inactive\",\"shift_id\":3}', '::1', '2026-09-15 11:40:26'),
(4, 1, 'employee', 8, 'create', '{\"employee_code\":\"EMP005\",\"status\":\"active\",\"shift_id\":3,\"login_username\":\"kani2026\",\"credential_year\":2026}', '::1', '2026-09-15 11:46:00'),
(5, 1, 'employee', 9, 'create', '{\"employee_code\":\"EMP006\",\"status\":\"active\",\"shift_id\":1,\"login_username\":\"emp02026\",\"credential_year\":2026}', '::1', '2026-09-15 11:46:50'),
(6, 1, 'employee', 9, 'update', '{\"employee_code\":\"EMP006\",\"status\":\"active\",\"shift_id\":1}', '::1', '2026-09-15 11:48:21');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`) VALUES
(1, 'Production', '2026-09-13 05:53:35'),
(2, 'Maintenance', '2026-09-13 05:53:35'),
(3, 'Quality', '2026-09-13 05:53:35'),
(4, 'HR', '2026-09-13 05:53:35'),
(5, 'Administration', '2026-09-13 05:53:35');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `login_username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `credential_year` smallint(6) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `shift_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `full_name`, `department_id`, `email`, `phone`, `login_username`, `password_hash`, `credential_year`, `status`, `shift_id`, `created_at`) VALUES
(1, 'EMP001', 'Ravi Kumar', 1, 'ravi@company.com', '9876543210', 'ravi2026', '$2y$10$9UzCeZniVJnj.ezgc/cDLuUU2Q3.qVLbOylNDKUI4fE7FgR/5mmWm', 2026, 'active', 1, '2026-09-13 05:53:35'),
(2, 'EMP002', 'Ananya Verma', 2, 'ananya@company.com', '9876543211', 'anan2026', '$2y$10$UqSaPe00JgV5ki53ZF6NJegYsETkzq8F9bSm5GQPFm.v0rPkrCg9u', 2026, 'active', 2, '2026-09-13 05:53:35'),
(3, 'EMP003', 'Suresh Nair', 3, 'suresh@company.com', '9876543212', 'sure2026', '$2y$10$pbLL7.zfDYEtX/UkRTCfautavMhLP8Sodozb3XcxIvuKMxTv8ag5m', 2026, 'active', 3, '2026-09-13 05:53:35'),
(4, 'EMP004', 'Meera Shah', 4, 'meera@company.com', '9876543213', 'meer2026', '$2y$10$sout/MTv3wwWYbs7kxD6fuHtnpPHubjaAW8pMNW2MjrxCBqiAfHBa', 2026, 'active', 1, '2026-09-13 05:53:35'),
(8, 'EMP005', 'Kanimozhi Murugesan', 2, 'padmavathykani678@gmail.com', '06383941226', 'kani2026', '$2y$10$ahqF2Y604NxSR8e9HZpafOsciTQys.zt/S08y4vbY6EBPshG2pDcC', 2026, 'active', 3, '2026-09-15 11:46:00'),
(9, 'EMP006', 'ABIM', 5, 'padmavathykani678@gmail.com', '06383941226', 'emp02026', '$2y$10$qXSmZt5xa8l1YX4Z/UdEyeFoFTV.vk6Y.ErbdZy6xWfhN8oyA4TYm', 2026, 'active', 1, '2026-09-15 11:46:50');

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_code` varchar(20) NOT NULL,
  `opening_balance` decimal(6,2) DEFAULT 0.00,
  `earned_balance` decimal(6,2) DEFAULT 0.00,
  `utilized_balance` decimal(6,2) DEFAULT 0.00,
  `closing_balance` decimal(6,2) DEFAULT 0.00,
  `carry_forward` decimal(6,2) DEFAULT 0.00,
  `month_year` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_leave_balances`
--

INSERT INTO `employee_leave_balances` (`id`, `employee_id`, `leave_type_code`, `opening_balance`, `earned_balance`, `utilized_balance`, `closing_balance`, `carry_forward`, `month_year`, `created_at`) VALUES
(95, 1, 'CL', 0.00, 1.00, 0.50, 0.50, 0.50, '2026-07', '2026-09-13 05:55:14'),
(96, 2, 'CL', 0.00, 1.00, 0.50, 0.50, 0.50, '2026-07', '2026-09-13 05:55:14'),
(97, 3, 'CL', 0.00, 1.00, 0.50, 0.50, 0.50, '2026-07', '2026-09-13 05:55:14'),
(98, 4, 'CL', 0.00, 1.00, 0.50, 0.50, 0.50, '2026-07', '2026-09-13 05:55:14'),
(99, 1, 'CL', 0.50, 1.00, 0.50, 1.00, 1.00, '2026-08', '2026-09-13 05:55:14'),
(100, 2, 'CL', 0.50, 1.00, 0.50, 1.00, 1.00, '2026-08', '2026-09-13 05:55:14'),
(101, 3, 'CL', 0.50, 1.00, 0.50, 1.00, 1.00, '2026-08', '2026-09-13 05:55:14'),
(102, 4, 'CL', 0.50, 1.00, 0.50, 1.00, 1.00, '2026-08', '2026-09-13 05:55:14'),
(103, 1, 'CL', 1.00, 1.00, 1.00, 1.00, 2.00, '2026-09', '2026-09-13 05:55:14'),
(104, 2, 'CL', 1.00, 1.00, 0.00, 2.00, 2.00, '2026-09', '2026-09-13 05:55:14'),
(105, 3, 'CL', 1.00, 1.00, 0.00, 2.00, 2.00, '2026-09', '2026-09-13 05:55:14'),
(106, 4, 'CL', 1.00, 1.00, 0.00, 2.00, 2.00, '2026-09', '2026-09-13 05:55:14'),
(107, 1, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(108, 2, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(109, 3, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(110, 4, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(111, 1, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(112, 2, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(113, 3, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(114, 4, 'CPL', 0.00, 0.50, 0.50, 0.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(115, 1, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(116, 2, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(117, 3, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(118, 4, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(119, 1, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(120, 2, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(121, 3, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(122, 4, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(123, 1, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(124, 2, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(125, 3, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(126, 4, 'EL', 0.00, 1.50, 0.50, 1.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(127, 1, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(128, 2, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(129, 3, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(130, 4, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-09', '2026-09-13 05:55:14'),
(131, 1, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-07', '2026-09-13 05:55:14'),
(132, 2, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-07', '2026-09-13 05:55:14'),
(133, 3, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-07', '2026-09-13 05:55:14'),
(134, 4, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-07', '2026-09-13 05:55:14'),
(135, 1, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-08', '2026-09-13 05:55:14'),
(136, 2, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-08', '2026-09-13 05:55:14'),
(137, 3, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-08', '2026-09-13 05:55:14'),
(138, 4, 'FL', 0.00, 1.00, 0.50, 0.50, 0.00, '2026-08', '2026-09-13 05:55:14'),
(139, 1, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(140, 2, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(141, 3, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(142, 4, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(143, 1, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(144, 2, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(145, 3, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(146, 4, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-07', '2026-09-13 05:55:14'),
(147, 1, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(148, 2, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(149, 3, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(150, 4, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-08', '2026-09-13 05:55:14'),
(151, 1, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(152, 2, 'WO', 0.00, 4.00, 1.00, 3.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(153, 3, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(154, 4, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-09', '2026-09-13 05:55:14'),
(158, 1, 'CL', 2.00, 1.00, 0.00, 3.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(159, 2, 'CL', 2.00, 1.00, 0.00, 3.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(160, 3, 'CL', 2.00, 1.00, 0.00, 3.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(161, 4, 'CL', 2.00, 1.00, 0.00, 3.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(162, 1, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(163, 2, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(164, 3, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(165, 4, 'CPL', 0.00, 0.50, 0.00, 0.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(166, 1, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(167, 2, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(168, 3, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(169, 4, 'EL', 0.00, 1.50, 0.00, 1.50, 0.00, '2026-10', '2026-09-13 05:55:14'),
(170, 1, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(171, 2, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(172, 3, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(173, 4, 'FL', 0.00, 1.00, 0.00, 1.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(174, 1, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(175, 2, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(176, 3, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-10', '2026-09-13 05:55:14'),
(177, 4, 'WO', 0.00, 4.00, 0.00, 4.00, 0.00, '2026-10', '2026-09-13 05:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_admins`
--

CREATE TABLE `hrms_admins` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','hr_manager') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_admins`
--

INSERT INTO `hrms_admins` (`id`, `full_name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'System Admin', 'admin@hrms.com', '$2y$10$Qz084zeODvO7ySTToCuzY.m6VsAbndJu2z.AAzizGyYfncq1iwfym', 'super_admin', '2026-09-13 05:53:35');

-- --------------------------------------------------------

--
-- Table structure for table `leave_applications`
--

CREATE TABLE `leave_applications` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_code` varchar(20) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `days_requested` decimal(4,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_applications`
--

INSERT INTO `leave_applications` (`id`, `employee_id`, `leave_type_code`, `from_date`, `to_date`, `days_requested`, `status`, `reason`, `approved_by`, `approved_at`, `created_at`) VALUES
(64, 1, 'CL', '2026-07-14', '2026-07-14', 0.50, 'approved', '[DUMMY-2026] 2026-07 CL sample leave', 1, '2026-07-14 04:30:00', '2026-07-13 03:30:00'),
(65, 1, 'CL', '2026-08-14', '2026-08-14', 0.50, 'approved', '[DUMMY-2026] 2026-08 CL sample leave', 1, '2026-08-14 04:30:00', '2026-08-13 03:30:00'),
(66, 2, 'CL', '2026-07-14', '2026-07-14', 0.50, 'approved', '[DUMMY-2026] 2026-07 CL sample leave', 1, '2026-07-14 04:30:00', '2026-07-13 03:30:00'),
(67, 2, 'CL', '2026-08-14', '2026-08-14', 0.50, 'approved', '[DUMMY-2026] 2026-08 CL sample leave', 1, '2026-08-14 04:30:00', '2026-08-13 03:30:00'),
(68, 3, 'CL', '2026-07-14', '2026-07-14', 0.50, 'approved', '[DUMMY-2026] 2026-07 CL sample leave', 1, '2026-07-14 04:30:00', '2026-07-13 03:30:00'),
(69, 3, 'CL', '2026-08-14', '2026-08-14', 0.50, 'approved', '[DUMMY-2026] 2026-08 CL sample leave', 1, '2026-08-14 04:30:00', '2026-08-13 03:30:00'),
(70, 4, 'CL', '2026-07-14', '2026-07-14', 0.50, 'approved', '[DUMMY-2026] 2026-07 CL sample leave', 1, '2026-07-14 04:30:00', '2026-07-13 03:30:00'),
(71, 4, 'CL', '2026-08-14', '2026-08-14', 0.50, 'approved', '[DUMMY-2026] 2026-08 CL sample leave', 1, '2026-08-14 04:30:00', '2026-08-13 03:30:00'),
(72, 1, 'CPL', '2026-07-11', '2026-07-11', 0.50, 'approved', '[DUMMY-2026] 2026-07 CPL sample leave', 1, '2026-07-11 04:30:00', '2026-07-10 03:30:00'),
(73, 1, 'CPL', '2026-08-11', '2026-08-11', 0.50, 'approved', '[DUMMY-2026] 2026-08 CPL sample leave', 1, '2026-08-11 04:30:00', '2026-08-10 03:30:00'),
(74, 2, 'CPL', '2026-07-11', '2026-07-11', 0.50, 'approved', '[DUMMY-2026] 2026-07 CPL sample leave', 1, '2026-07-11 04:30:00', '2026-07-10 03:30:00'),
(75, 2, 'CPL', '2026-08-11', '2026-08-11', 0.50, 'approved', '[DUMMY-2026] 2026-08 CPL sample leave', 1, '2026-08-11 04:30:00', '2026-08-10 03:30:00'),
(76, 3, 'CPL', '2026-07-11', '2026-07-11', 0.50, 'approved', '[DUMMY-2026] 2026-07 CPL sample leave', 1, '2026-07-11 04:30:00', '2026-07-10 03:30:00'),
(77, 3, 'CPL', '2026-08-11', '2026-08-11', 0.50, 'approved', '[DUMMY-2026] 2026-08 CPL sample leave', 1, '2026-08-11 04:30:00', '2026-08-10 03:30:00'),
(78, 4, 'CPL', '2026-07-11', '2026-07-11', 0.50, 'approved', '[DUMMY-2026] 2026-07 CPL sample leave', 1, '2026-07-11 04:30:00', '2026-07-10 03:30:00'),
(79, 4, 'CPL', '2026-08-11', '2026-08-11', 0.50, 'approved', '[DUMMY-2026] 2026-08 CPL sample leave', 1, '2026-08-11 04:30:00', '2026-08-10 03:30:00'),
(80, 1, 'EL', '2026-07-05', '2026-07-05', 0.50, 'approved', '[DUMMY-2026] 2026-07 EL sample leave', 1, '2026-07-05 04:30:00', '2026-07-04 03:30:00'),
(81, 1, 'EL', '2026-08-05', '2026-08-05', 0.50, 'approved', '[DUMMY-2026] 2026-08 EL sample leave', 1, '2026-08-05 04:30:00', '2026-08-04 03:30:00'),
(82, 2, 'EL', '2026-07-05', '2026-07-05', 0.50, 'approved', '[DUMMY-2026] 2026-07 EL sample leave', 1, '2026-07-05 04:30:00', '2026-07-04 03:30:00'),
(83, 2, 'EL', '2026-08-05', '2026-08-05', 0.50, 'approved', '[DUMMY-2026] 2026-08 EL sample leave', 1, '2026-08-05 04:30:00', '2026-08-04 03:30:00'),
(84, 3, 'EL', '2026-07-05', '2026-07-05', 0.50, 'approved', '[DUMMY-2026] 2026-07 EL sample leave', 1, '2026-07-05 04:30:00', '2026-07-04 03:30:00'),
(85, 3, 'EL', '2026-08-05', '2026-08-05', 0.50, 'approved', '[DUMMY-2026] 2026-08 EL sample leave', 1, '2026-08-05 04:30:00', '2026-08-04 03:30:00'),
(86, 4, 'EL', '2026-07-05', '2026-07-05', 0.50, 'approved', '[DUMMY-2026] 2026-07 EL sample leave', 1, '2026-07-05 04:30:00', '2026-07-04 03:30:00'),
(87, 4, 'EL', '2026-08-05', '2026-08-05', 0.50, 'approved', '[DUMMY-2026] 2026-08 EL sample leave', 1, '2026-08-05 04:30:00', '2026-08-04 03:30:00'),
(88, 1, 'FL', '2026-07-08', '2026-07-08', 0.50, 'approved', '[DUMMY-2026] 2026-07 FL sample leave', 1, '2026-07-08 04:30:00', '2026-07-07 03:30:00'),
(89, 1, 'FL', '2026-08-08', '2026-08-08', 0.50, 'approved', '[DUMMY-2026] 2026-08 FL sample leave', 1, '2026-08-08 04:30:00', '2026-08-07 03:30:00'),
(90, 2, 'FL', '2026-07-08', '2026-07-08', 0.50, 'approved', '[DUMMY-2026] 2026-07 FL sample leave', 1, '2026-07-08 04:30:00', '2026-07-07 03:30:00'),
(91, 2, 'FL', '2026-08-08', '2026-08-08', 0.50, 'approved', '[DUMMY-2026] 2026-08 FL sample leave', 1, '2026-08-08 04:30:00', '2026-08-07 03:30:00'),
(92, 3, 'FL', '2026-07-08', '2026-07-08', 0.50, 'approved', '[DUMMY-2026] 2026-07 FL sample leave', 1, '2026-07-08 04:30:00', '2026-07-07 03:30:00'),
(93, 3, 'FL', '2026-08-08', '2026-08-08', 0.50, 'approved', '[DUMMY-2026] 2026-08 FL sample leave', 1, '2026-08-08 04:30:00', '2026-08-07 03:30:00'),
(94, 4, 'FL', '2026-07-08', '2026-07-08', 0.50, 'approved', '[DUMMY-2026] 2026-07 FL sample leave', 1, '2026-07-08 04:30:00', '2026-07-07 03:30:00'),
(95, 4, 'FL', '2026-08-08', '2026-08-08', 0.50, 'approved', '[DUMMY-2026] 2026-08 FL sample leave', 1, '2026-08-08 04:30:00', '2026-08-07 03:30:00'),
(96, 1, 'WO', '2026-07-02', '2026-07-02', 1.00, 'approved', '[DUMMY-2026] 2026-07 WO sample leave', 1, '2026-07-02 04:30:00', '2026-07-01 03:30:00'),
(97, 1, 'WO', '2026-08-02', '2026-08-02', 1.00, 'approved', '[DUMMY-2026] 2026-08 WO sample leave', 1, '2026-08-02 04:30:00', '2026-08-01 03:30:00'),
(98, 2, 'WO', '2026-07-02', '2026-07-02', 1.00, 'approved', '[DUMMY-2026] 2026-07 WO sample leave', 1, '2026-07-02 04:30:00', '2026-07-01 03:30:00'),
(99, 2, 'WO', '2026-08-02', '2026-08-02', 1.00, 'approved', '[DUMMY-2026] 2026-08 WO sample leave', 1, '2026-08-02 04:30:00', '2026-08-01 03:30:00'),
(100, 3, 'WO', '2026-07-02', '2026-07-02', 1.00, 'approved', '[DUMMY-2026] 2026-07 WO sample leave', 1, '2026-07-02 04:30:00', '2026-07-01 03:30:00'),
(101, 3, 'WO', '2026-08-02', '2026-08-02', 1.00, 'approved', '[DUMMY-2026] 2026-08 WO sample leave', 1, '2026-08-02 04:30:00', '2026-08-01 03:30:00'),
(102, 4, 'WO', '2026-07-02', '2026-07-02', 1.00, 'approved', '[DUMMY-2026] 2026-07 WO sample leave', 1, '2026-07-02 04:30:00', '2026-07-01 03:30:00'),
(103, 4, 'WO', '2026-08-02', '2026-08-02', 1.00, 'approved', '[DUMMY-2026] 2026-08 WO sample leave', 1, '2026-08-02 04:30:00', '2026-08-01 03:30:00'),
(127, 1, 'CL', '2026-09-02', '2026-09-02', 1.00, 'approved', '', 1, '2026-09-13 05:58:15', '2026-09-13 05:57:00'),
(128, 2, 'WO', '2026-09-13', '2026-09-13', 1.00, 'approved', 'health', 1, '2026-09-13 06:21:16', '2026-09-13 06:20:59'),
(129, 3, 'WO', '2026-09-13', '2026-09-13', 1.00, 'pending', '', NULL, NULL, '2026-09-13 06:28:29'),
(130, 2, 'CL', '2026-09-15', '2026-09-15', 0.50, 'approved', 'rtet', NULL, NULL, '2026-09-15 07:47:44'),
(131, 2, 'WO', '2026-09-10', '2026-09-10', 0.50, 'rejected', '[Half day: Forenoon] ', NULL, NULL, '2026-09-15 07:56:08'),
(132, 2, 'WO', '2026-09-18', '2026-09-18', 0.50, 'approved', '[Half day: Forenoon] ', NULL, NULL, '2026-09-15 08:13:38'),
(133, 4, 'WO', '2026-09-15', '2026-09-16', 2.00, 'rejected', '', NULL, NULL, '2026-09-15 08:16:13'),
(134, 2, 'WO', '2026-09-15', '2026-09-15', 1.00, 'approved', '', NULL, NULL, '2026-09-15 08:24:47'),
(135, 1, 'WO', '2026-09-15', '2026-09-15', 1.00, 'rejected', '', NULL, NULL, '2026-09-15 11:13:08'),
(136, 2, 'WO', '2026-09-15', '2026-09-15', 1.00, 'pending', '', NULL, NULL, '2026-09-15 11:23:33'),
(137, 2, 'WO', '2026-09-15', '2026-09-15', 1.00, 'pending', '', NULL, NULL, '2026-09-15 11:23:52');

-- --------------------------------------------------------

--
-- Table structure for table `leave_transactions`
--

CREATE TABLE `leave_transactions` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_code` varchar(20) NOT NULL,
  `transaction_type` enum('credit','debit','carry_forward','monthly_adjustment') NOT NULL,
  `amount` decimal(6,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_transactions`
--

INSERT INTO `leave_transactions` (`id`, `employee_id`, `leave_type_code`, `transaction_type`, `amount`, `reference_type`, `reference_id`, `remarks`, `created_at`) VALUES
(79, 1, 'CL', 'debit', 0.50, 'dummy_2026', 64, '[DUMMY-2026] Approved CL usage', '2026-07-14 04:30:00'),
(80, 1, 'CL', 'debit', 0.50, 'dummy_2026', 65, '[DUMMY-2026] Approved CL usage', '2026-08-14 04:30:00'),
(81, 2, 'CL', 'debit', 0.50, 'dummy_2026', 66, '[DUMMY-2026] Approved CL usage', '2026-07-14 04:30:00'),
(82, 2, 'CL', 'debit', 0.50, 'dummy_2026', 67, '[DUMMY-2026] Approved CL usage', '2026-08-14 04:30:00'),
(83, 3, 'CL', 'debit', 0.50, 'dummy_2026', 68, '[DUMMY-2026] Approved CL usage', '2026-07-14 04:30:00'),
(84, 3, 'CL', 'debit', 0.50, 'dummy_2026', 69, '[DUMMY-2026] Approved CL usage', '2026-08-14 04:30:00'),
(85, 4, 'CL', 'debit', 0.50, 'dummy_2026', 70, '[DUMMY-2026] Approved CL usage', '2026-07-14 04:30:00'),
(86, 4, 'CL', 'debit', 0.50, 'dummy_2026', 71, '[DUMMY-2026] Approved CL usage', '2026-08-14 04:30:00'),
(87, 1, 'CPL', 'debit', 0.50, 'dummy_2026', 72, '[DUMMY-2026] Approved CPL usage', '2026-07-11 04:30:00'),
(88, 1, 'CPL', 'debit', 0.50, 'dummy_2026', 73, '[DUMMY-2026] Approved CPL usage', '2026-08-11 04:30:00'),
(89, 2, 'CPL', 'debit', 0.50, 'dummy_2026', 74, '[DUMMY-2026] Approved CPL usage', '2026-07-11 04:30:00'),
(90, 2, 'CPL', 'debit', 0.50, 'dummy_2026', 75, '[DUMMY-2026] Approved CPL usage', '2026-08-11 04:30:00'),
(91, 3, 'CPL', 'debit', 0.50, 'dummy_2026', 76, '[DUMMY-2026] Approved CPL usage', '2026-07-11 04:30:00'),
(92, 3, 'CPL', 'debit', 0.50, 'dummy_2026', 77, '[DUMMY-2026] Approved CPL usage', '2026-08-11 04:30:00'),
(93, 4, 'CPL', 'debit', 0.50, 'dummy_2026', 78, '[DUMMY-2026] Approved CPL usage', '2026-07-11 04:30:00'),
(94, 4, 'CPL', 'debit', 0.50, 'dummy_2026', 79, '[DUMMY-2026] Approved CPL usage', '2026-08-11 04:30:00'),
(95, 1, 'EL', 'debit', 0.50, 'dummy_2026', 80, '[DUMMY-2026] Approved EL usage', '2026-07-05 04:30:00'),
(96, 1, 'EL', 'debit', 0.50, 'dummy_2026', 81, '[DUMMY-2026] Approved EL usage', '2026-08-05 04:30:00'),
(97, 2, 'EL', 'debit', 0.50, 'dummy_2026', 82, '[DUMMY-2026] Approved EL usage', '2026-07-05 04:30:00'),
(98, 2, 'EL', 'debit', 0.50, 'dummy_2026', 83, '[DUMMY-2026] Approved EL usage', '2026-08-05 04:30:00'),
(99, 3, 'EL', 'debit', 0.50, 'dummy_2026', 84, '[DUMMY-2026] Approved EL usage', '2026-07-05 04:30:00'),
(100, 3, 'EL', 'debit', 0.50, 'dummy_2026', 85, '[DUMMY-2026] Approved EL usage', '2026-08-05 04:30:00'),
(101, 4, 'EL', 'debit', 0.50, 'dummy_2026', 86, '[DUMMY-2026] Approved EL usage', '2026-07-05 04:30:00'),
(102, 4, 'EL', 'debit', 0.50, 'dummy_2026', 87, '[DUMMY-2026] Approved EL usage', '2026-08-05 04:30:00'),
(103, 1, 'FL', 'debit', 0.50, 'dummy_2026', 88, '[DUMMY-2026] Approved FL usage', '2026-07-08 04:30:00'),
(104, 1, 'FL', 'debit', 0.50, 'dummy_2026', 89, '[DUMMY-2026] Approved FL usage', '2026-08-08 04:30:00'),
(105, 2, 'FL', 'debit', 0.50, 'dummy_2026', 90, '[DUMMY-2026] Approved FL usage', '2026-07-08 04:30:00'),
(106, 2, 'FL', 'debit', 0.50, 'dummy_2026', 91, '[DUMMY-2026] Approved FL usage', '2026-08-08 04:30:00'),
(107, 3, 'FL', 'debit', 0.50, 'dummy_2026', 92, '[DUMMY-2026] Approved FL usage', '2026-07-08 04:30:00'),
(108, 3, 'FL', 'debit', 0.50, 'dummy_2026', 93, '[DUMMY-2026] Approved FL usage', '2026-08-08 04:30:00'),
(109, 4, 'FL', 'debit', 0.50, 'dummy_2026', 94, '[DUMMY-2026] Approved FL usage', '2026-07-08 04:30:00'),
(110, 4, 'FL', 'debit', 0.50, 'dummy_2026', 95, '[DUMMY-2026] Approved FL usage', '2026-08-08 04:30:00'),
(111, 1, 'WO', 'debit', 1.00, 'dummy_2026', 96, '[DUMMY-2026] Approved WO usage', '2026-07-02 04:30:00'),
(112, 1, 'WO', 'debit', 1.00, 'dummy_2026', 97, '[DUMMY-2026] Approved WO usage', '2026-08-02 04:30:00'),
(113, 2, 'WO', 'debit', 1.00, 'dummy_2026', 98, '[DUMMY-2026] Approved WO usage', '2026-07-02 04:30:00'),
(114, 2, 'WO', 'debit', 1.00, 'dummy_2026', 99, '[DUMMY-2026] Approved WO usage', '2026-08-02 04:30:00'),
(115, 3, 'WO', 'debit', 1.00, 'dummy_2026', 100, '[DUMMY-2026] Approved WO usage', '2026-07-02 04:30:00'),
(116, 3, 'WO', 'debit', 1.00, 'dummy_2026', 101, '[DUMMY-2026] Approved WO usage', '2026-08-02 04:30:00'),
(117, 4, 'WO', 'debit', 1.00, 'dummy_2026', 102, '[DUMMY-2026] Approved WO usage', '2026-07-02 04:30:00'),
(118, 4, 'WO', 'debit', 1.00, 'dummy_2026', 103, '[DUMMY-2026] Approved WO usage', '2026-08-02 04:30:00'),
(142, 1, 'CL', 'carry_forward', 0.50, 'dummy_2026', 95, '[DUMMY-2026] CL carry-forward to next month', '2026-07-01 02:30:00'),
(143, 2, 'CL', 'carry_forward', 0.50, 'dummy_2026', 96, '[DUMMY-2026] CL carry-forward to next month', '2026-07-01 02:30:00'),
(144, 3, 'CL', 'carry_forward', 0.50, 'dummy_2026', 97, '[DUMMY-2026] CL carry-forward to next month', '2026-07-01 02:30:00'),
(145, 4, 'CL', 'carry_forward', 0.50, 'dummy_2026', 98, '[DUMMY-2026] CL carry-forward to next month', '2026-07-01 02:30:00'),
(146, 1, 'CL', 'carry_forward', 1.00, 'dummy_2026', 99, '[DUMMY-2026] CL carry-forward to next month', '2026-08-01 02:30:00'),
(147, 2, 'CL', 'carry_forward', 1.00, 'dummy_2026', 100, '[DUMMY-2026] CL carry-forward to next month', '2026-08-01 02:30:00'),
(148, 3, 'CL', 'carry_forward', 1.00, 'dummy_2026', 101, '[DUMMY-2026] CL carry-forward to next month', '2026-08-01 02:30:00'),
(149, 4, 'CL', 'carry_forward', 1.00, 'dummy_2026', 102, '[DUMMY-2026] CL carry-forward to next month', '2026-08-01 02:30:00'),
(150, 1, 'CL', 'carry_forward', 2.00, 'dummy_2026', 103, '[DUMMY-2026] CL carry-forward to next month', '2026-09-01 02:30:00'),
(151, 2, 'CL', 'carry_forward', 2.00, 'dummy_2026', 104, '[DUMMY-2026] CL carry-forward to next month', '2026-09-01 02:30:00'),
(152, 3, 'CL', 'carry_forward', 2.00, 'dummy_2026', 105, '[DUMMY-2026] CL carry-forward to next month', '2026-09-01 02:30:00'),
(153, 4, 'CL', 'carry_forward', 2.00, 'dummy_2026', 106, '[DUMMY-2026] CL carry-forward to next month', '2026-09-01 02:30:00'),
(157, 1, 'CL', 'debit', 1.00, 'leave_application', 127, 'Approved leave deduction', '2026-09-13 05:58:15'),
(158, 2, 'WO', 'debit', 1.00, 'leave_application', 128, 'Approved leave deduction', '2026-09-13 06:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `priority_order` int(11) NOT NULL,
  `is_paid` tinyint(1) DEFAULT 1,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `code`, `name`, `description`, `priority_order`, `is_paid`, `active`) VALUES
(1, 'WO', 'Weekly Off', 'Weekly off entitlement', 1, 1, 1),
(2, 'EL', 'Earned Leave', 'Earned leave', 2, 1, 1),
(3, 'FL', 'Flexible Leave', 'Flexible leave', 3, 1, 1),
(4, 'CPL', 'Compensatory Leave', 'Compensatory off leave', 4, 1, 1),
(5, 'CL', 'Casual Leave', 'Casual leave', 5, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_attendance_processing`
--

CREATE TABLE `monthly_attendance_processing` (
  `id` bigint(20) NOT NULL,
  `process_month` varchar(10) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `status` enum('pending','processing','completed','review_required') DEFAULT 'pending',
  `total_employees` int(11) DEFAULT 0,
  `total_attendance_records` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_attendance_processing`
--

INSERT INTO `monthly_attendance_processing` (`id`, `process_month`, `processed_by`, `status`, `total_employees`, `total_attendance_records`, `created_at`, `completed_at`) VALUES
(1, '2026-09', NULL, 'review_required', 4, 9, '2026-09-13 06:18:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) DEFAULT 10,
  `late_threshold_minutes` int(11) DEFAULT 15,
  `overtime_after_minutes` int(11) DEFAULT 60,
  `is_night_shift` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `shift_name`, `start_time`, `end_time`, `grace_minutes`, `late_threshold_minutes`, `overtime_after_minutes`, `is_night_shift`, `created_at`) VALUES
(1, 'General Shift', '09:00:00', '18:00:00', 10, 15, 60, 0, '2026-09-13 05:53:35'),
(2, 'Rotating Shift', '08:00:00', '17:00:00', 10, 20, 45, 0, '2026-09-13 05:53:35'),
(3, 'Night Shift', '20:00:00', '05:00:00', 15, 20, 90, 1, '2026-09-13 05:53:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_correction_logs`
--
ALTER TABLE `attendance_correction_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_correction_attendance` (`attendance_id`,`created_at`);

--
-- Indexes for table `attendance_punches`
--
ALTER TABLE `attendance_punches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_punch_employee_date` (`employee_id`,`punch_date`),
  ADD KEY `idx_punch_date_type` (`punch_date`,`punch_type`),
  ADD KEY `fk_punch_shift` (`shift_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_date` (`employee_id`,`attendance_date`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  ADD KEY `idx_audit_actor` (`actor_user_id`,`created_at`),
  ADD KEY `idx_audit_action` (`action`,`created_at`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `login_username` (`login_username`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_leave_month` (`employee_id`,`leave_type_code`,`month_year`);

--
-- Indexes for table `hrms_admins`
--
ALTER TABLE `hrms_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `leave_transactions`
--
ALTER TABLE `leave_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_trans` (`employee_id`,`leave_type_code`,`created_at`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `monthly_attendance_processing`
--
ALTER TABLE `monthly_attendance_processing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_processing_month` (`process_month`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_correction_logs`
--
ALTER TABLE `attendance_correction_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_punches`
--
ALTER TABLE `attendance_punches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `hrms_admins`
--
ALTER TABLE `hrms_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `leave_transactions`
--
ALTER TABLE `leave_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `monthly_attendance_processing`
--
ALTER TABLE `monthly_attendance_processing`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_correction_logs`
--
ALTER TABLE `attendance_correction_logs`
  ADD CONSTRAINT `fk_correction_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_punches`
--
ALTER TABLE `attendance_punches`
  ADD CONSTRAINT `fk_punch_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_punch_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD CONSTRAINT `employee_leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
