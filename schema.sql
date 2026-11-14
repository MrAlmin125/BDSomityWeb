-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql110.infinityfree.com
-- Generation Time: Sep 06, 2026 at 03:22 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40706792_shopbd`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_type` enum('admin','member') NOT NULL DEFAULT 'admin',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_type`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1727, 'member', 6, 'login', 'Member logged in', '37.124.124.199', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-04 13:25:46'),
(1728, 'admin', 1, 'login', 'Admin logged in', '37.124.124.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-04 20:02:49'),
(1729, 'admin', 1, 'logout', 'Admin logged out', '37.124.124.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-04 20:03:10'),
(1730, 'admin', 0, 'logout', 'Admin logged out', '37.124.124.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-04 20:03:10'),
(1731, 'member', 6, 'login', 'Member logged in', '37.124.124.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-04 20:04:30'),
(1732, 'member', 6, 'logout', 'Member logged out', '37.124.124.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-04 20:11:07'),
(1733, 'member', 6, 'login', 'Member logged in', '82.167.83.132', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/23G71 [FBAN/FBIOS;FBAV/567.0.0.27.106;FBBV/1001940950;FBDV/iPhone13,4;FBMD/iPhone;FBSN/iOS;FBSV/26.6;FBSS/3;FBCR/;FBID/phone;FBLC/en_SA@calendar=gregori', '2026-08-06 09:30:38'),
(1734, 'member', 6, 'logout', 'Member logged out', '82.167.75.128', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 09:37:27'),
(1735, 'admin', 1, 'login', 'Admin logged in', '82.167.75.128', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 09:37:39'),
(1736, 'member', 9, 'login', 'Member logged in', '121.200.221.148', 'Mozilla/5.0 (Linux; Android 11; vivo 1906 Build/RP1A.200720.012; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-08-06 14:29:21'),
(1737, 'member', 10, 'login', 'Member logged in', '27.147.191.100', 'Mozilla/5.0 (Linux; Android 15; 25078RA3EY Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-08-06 15:57:51'),
(1738, 'member', 7, 'login', 'Member logged in', '202.134.10.129', 'Mozilla/5.0 (Linux; Android 14; SM-M156B Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 16:48:30'),
(1739, 'admin', 0, 'login_failed', 'Failed login: mahbubalam3104@gmali.com', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 16:57:57'),
(1740, 'admin', 1, 'logout', 'Admin logged out', '37.124.124.199', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:00:58'),
(1741, 'member', 15, 'login', 'Member logged in', '37.124.124.199', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:01:21'),
(1742, 'member', 15, 'login', 'Member logged in', '176.16.252.145', 'Mozilla/5.0 (Linux; Android 15; V2248 Build/AP3A.240905.015.A2_CS; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 17:02:32'),
(1743, 'member', 15, 'logout', 'Member logged out', '82.167.99.138', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:17:27'),
(1744, 'admin', 1, 'login', 'Admin logged in', '82.167.99.138', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:17:40'),
(1745, 'admin', 1, 'logout', 'Admin logged out', '82.167.99.138', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:18:25'),
(1746, 'member', 11, 'login', 'Member logged in', '82.167.99.138', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-06 17:19:12'),
(1747, 'member', 11, 'login', 'Member logged in', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 17:21:24'),
(1748, 'member', 11, 'logout', 'Member logged out', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 17:31:24'),
(1749, 'member', 11, 'login', 'Member logged in', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/572.0.0.41.89;]', '2026-08-06 17:32:16'),
(1750, 'member', 10, 'login', 'Member logged in', '27.147.191.100', 'Mozilla/5.0 (Linux; Android 15; 25078RA3EY Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-08-07 10:18:05'),
(1751, 'admin', 1, 'login', 'Admin logged in', '82.167.96.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-07 17:32:45'),
(1752, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260808-19426 (৳2000) + ফাইন ৳500', '82.167.64.140', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-08 14:39:08'),
(1753, 'admin', 1, 'fine_deleted', 'Fine #83 deleted with linked deposits', '82.167.64.140', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-08 14:42:44'),
(1754, 'member', 11, 'login', 'Member logged in', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-08-08 16:05:43'),
(1755, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-09 19:55:58'),
(1756, 'admin', 1, 'user_add', 'নতুন ইউজার যোগ: আবিদ হাসান', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-09 19:58:58'),
(1757, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-10 09:45:30'),
(1758, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 06:44:06'),
(1759, 'member', 15, 'login', 'Member logged in', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 06:44:38'),
(1760, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-11 07:23:39'),
(1761, 'member', 15, 'logout', 'Member logged out', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:23:43'),
(1762, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:23:53'),
(1763, 'admin', 1, 'logout', 'Admin logged out', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:24:21'),
(1764, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 07:25:55'),
(1765, 'admin', 0, 'login_failed', 'Failed login: admingmail.com', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/23G71 [FBAN/FBIOS;FBAV/567.0.0.27.106;FBBV/1001940950;FBDV/iPhone13,4;FBMD/iPhone;FBSN/iOS;FBSV/26.6;FBSS/3;FBCR/;FBID/phone;FBLC/en_SA@calendar=gregori', '2026-08-11 07:30:14'),
(1766, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/23G71 [FBAN/FBIOS;FBAV/567.0.0.27.106;FBBV/1001940950;FBDV/iPhone13,4;FBMD/iPhone;FBSN/iOS;FBSV/26.6;FBSS/3;FBCR/;FBID/phone;FBLC/en_SA@calendar=gregori', '2026-08-11 07:30:42'),
(1767, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-11 07:31:44'),
(1768, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-11 07:32:51'),
(1769, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1', '2026-08-11 07:43:32'),
(1770, 'admin', 1, 'logout', 'Admin logged out', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1', '2026-08-11 08:02:29'),
(1771, 'admin', 1, 'logout', 'Admin logged out', '37.216.70.153', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-11 08:04:02'),
(1772, 'admin', 1, 'deposit_delete', 'সঞ্চয় ডিলিট: DEP-20260712-72360', '82.167.104.132', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-11 16:05:59'),
(1773, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260811-42944 (৳2000)', '82.167.104.132', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-11 16:06:44'),
(1774, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260811-46956 (৳5000)', '82.167.104.132', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-11 16:07:13'),
(1775, 'member', 11, 'login', 'Member logged in', '82.167.91.245', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.181 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/573.0.0.44.88;]', '2026-08-12 13:23:39'),
(1776, 'member', 10, 'login', 'Member logged in', '103.25.248.222', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-15 04:57:43'),
(1777, 'member', 10, 'logout', 'Member logged out', '103.25.248.222', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-15 04:59:38'),
(1778, 'admin', 1, 'login', 'Admin logged in', '37.216.70.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-16 08:16:58'),
(1779, 'admin', 1, 'login', 'Admin logged in', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:12:37'),
(1780, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260820-04877 (৳2000) + ফাইন ৳500', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:14:12'),
(1781, 'admin', 1, 'deposit_delete', 'সঞ্চয় ডিলিট: DEP-20260820-04877', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:15:15'),
(1782, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260820-83527 (৳2000)', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:15:45'),
(1783, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260820-66942 (৳2000) + ফাইন ৳500', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:18:48'),
(1784, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260820-98612 (৳2000) + ফাইন ৳500', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:20:34'),
(1785, 'admin', 1, 'deposit_add', 'নতুন সঞ্চয়: DEP-20260820-27547 (৳2000) + ফাইন ৳500', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:21:12'),
(1786, 'admin', 1, 'fine_deleted', 'Fine #85 deleted with linked deposits', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:23:54'),
(1787, 'admin', 1, 'fine_deleted', 'Fine #90 deleted with linked deposits', '82.167.105.103', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-20 06:24:27'),
(1788, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 06:19:52'),
(1789, 'admin', 1, 'login', 'Admin logged in', '37.216.50.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 06:28:10'),
(1790, 'admin', 1, 'logout', 'Admin logged out', '37.216.50.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 06:29:13'),
(1791, 'member', 6, 'login', 'Member logged in', '37.216.50.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 06:29:28'),
(1792, 'member', 6, 'logout', 'Member logged out', '37.216.50.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 06:32:53'),
(1793, 'member', 6, 'login', 'Member logged in', '37.216.50.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 06:33:15'),
(1794, 'member', 11, 'login', 'Member logged in', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/151.0.7922.169 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/575.0.0.48.90;]', '2026-08-22 06:51:38'),
(1795, 'member', 6, 'logout', 'Member logged out', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 08:57:32'),
(1796, 'admin', 1, 'login', 'Admin logged in', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 08:57:44'),
(1797, 'admin', 1, 'logout', 'Admin logged out', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 08:58:23'),
(1798, 'member', 10, 'login', 'Member logged in', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 08:58:54'),
(1799, 'member', 10, 'logout', 'Member logged out', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 09:01:28'),
(1800, 'member', 10, 'login', 'Member logged in', '82.167.81.99', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 09:02:30'),
(1801, 'member', 10, 'logout', 'Member logged out', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:18:30'),
(1802, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:18:40'),
(1803, 'member', 10, 'logout', 'Member logged out', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:19:33'),
(1804, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:20:02'),
(1805, 'member', 10, 'logout', 'Member logged out', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:20:36'),
(1806, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:21:05'),
(1807, 'member', 10, 'logout', 'Member logged out', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 09:21:17');
INSERT INTO `activity_logs` (`id`, `user_type`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1808, 'member', 10, 'logout', 'Member logged out', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:23:04'),
(1809, 'admin', 1, 'login', 'Admin logged in', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:23:29'),
(1810, 'admin', 1, 'logout', 'Admin logged out', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:25:11'),
(1811, 'member', 6, 'login', 'Member logged in', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:26:20'),
(1812, 'member', 6, 'logout', 'Member logged out', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:27:13'),
(1813, 'member', 0, 'logout', 'Member logged out', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:27:13'),
(1814, 'admin', 1, 'login', 'Admin logged in', '82.167.97.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-08-22 10:27:28'),
(1815, 'member', 10, 'login', 'Member logged in', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 10:30:26'),
(1816, 'member', 10, 'logout', 'Member logged out', '103.93.120.50', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-22 10:31:52'),
(1817, 'member', 15, 'login', 'Member logged in', '2.28.14.78', 'Mozilla/5.0 (Linux; Android 15; V2248 Build/AP3A.240905.015.A2_CS; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.183 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/574.0.0.45.86;]', '2026-08-22 10:32:07'),
(1818, 'member', 11, 'logout', 'Member logged out', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/151.0.7922.169 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/575.0.0.48.90;]', '2026-08-22 16:42:34'),
(1819, 'member', 11, 'login', 'Member logged in', '46.153.222.108', 'Mozilla/5.0 (Linux; Android 15; Infinix X6871 Build/AP3A.240905.015.A2; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/151.0.7922.169 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/575.0.0.48.90;]', '2026-08-22 16:42:59'),
(1820, 'member', 7, 'login', 'Member logged in', '103.87.250.186', 'Mozilla/5.0 (Linux; Android 14; SM-M156B Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/151.0.7922.169 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/575.0.0.48.90;]', '2026-08-22 16:57:20'),
(1821, 'admin', 1, 'login', 'Admin logged in', '109.83.91.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-09-05 19:46:16'),
(1822, 'admin', 1, 'logout', 'Admin logged out', '109.83.91.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-09-05 19:47:37'),
(1823, 'admin', 1, 'login', 'Admin logged in', '109.83.91.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_6_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/150.0.7871.51 Mobile/15E148 Safari/604.1', '2026-09-05 19:47:44');

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'download/email/auto',
  `filename` varchar(255) DEFAULT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `deposit_type` enum('regular','special','share','fd','expense','withdrawal','fine') NOT NULL DEFAULT 'regular',
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `transaction_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `member_id`, `amount`, `deposit_date`, `deposit_type`, `reference_id`, `reference_type`, `payment_method`, `transaction_no`, `receipt_no`, `note`, `collected_by`, `created_at`) VALUES
(40, 7, '2000.00', '2025-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-80385', '', 1, '2026-07-07 20:17:33'),
(41, 7, '2000.00', '2025-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-31462', '', 1, '2026-07-07 20:24:18'),
(42, 7, '5000.00', '2025-05-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-31750', '', 1, '2026-07-07 20:27:11'),
(43, 7, '2000.00', '2025-06-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-18515', '', 1, '2026-07-07 20:27:57'),
(44, 7, '2000.00', '2025-07-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-50034', '', 1, '2026-07-07 21:18:22'),
(45, 7, '2000.00', '2025-08-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-96206', '', 1, '2026-07-07 21:18:52'),
(46, 7, '2000.00', '2025-09-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-87070', '', 1, '2026-07-07 21:19:17'),
(47, 7, '2000.00', '2025-10-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-61816', '', 1, '2026-07-07 21:20:00'),
(48, 7, '2000.00', '2025-11-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-56913', '', 1, '2026-07-07 21:20:58'),
(49, 6, '2000.00', '2025-12-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-37692', '', 1, '2026-07-07 21:21:39'),
(50, 7, '2000.00', '2026-01-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-56990', '', 1, '2026-07-07 21:23:19'),
(51, 7, '2000.00', '2026-02-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-63056', '', 1, '2026-07-07 21:23:47'),
(52, 7, '2000.00', '2026-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-40800', '', 1, '2026-07-07 21:24:07'),
(53, 7, '2000.00', '2026-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-18562', '', 1, '2026-07-07 21:24:28'),
(54, 6, '2000.00', '2025-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-90893', '', 1, '2026-07-07 22:06:06'),
(55, 6, '2000.00', '2025-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-37034', '', 1, '2026-07-07 22:06:53'),
(56, 6, '5000.00', '2025-05-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-94525', '', 1, '2026-07-07 22:07:25'),
(57, 6, '2000.00', '2025-06-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-70266', '', 1, '2026-07-07 22:07:49'),
(58, 6, '2000.00', '2025-07-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-69861', '', 1, '2026-07-07 22:08:23'),
(59, 6, '2000.00', '2025-08-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-00855', '', 1, '2026-07-07 22:08:40'),
(60, 6, '2000.00', '2025-09-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-11329', '', 1, '2026-07-07 22:09:24'),
(61, 6, '2000.00', '2025-10-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-43174', '', 1, '2026-07-07 22:09:44'),
(62, 6, '2000.00', '2025-11-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-12709', '', 1, '2026-07-07 22:09:59'),
(63, 7, '2000.00', '2025-12-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-80084', '', 1, '2026-07-07 22:10:37'),
(64, 6, '2000.00', '2026-01-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-63746', '', 1, '2026-07-07 22:10:57'),
(65, 6, '2000.00', '2026-02-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-55918', '', 1, '2026-07-07 22:11:57'),
(66, 6, '2000.00', '2026-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-20043', '', 1, '2026-07-07 22:12:09'),
(67, 6, '2000.00', '2026-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-53368', '', 1, '2026-07-07 22:13:27'),
(68, 9, '2000.00', '2025-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-70101', '', 1, '2026-07-08 09:34:55'),
(69, 9, '2000.00', '2025-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-60596', '', 1, '2026-07-08 09:42:13'),
(70, 9, '5000.00', '2025-05-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-71770', '', 1, '2026-07-08 09:42:43'),
(71, 9, '2000.00', '2025-06-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-23226', '', 1, '2026-07-08 09:43:15'),
(72, 9, '2000.00', '2025-07-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-43072', '', 1, '2026-07-08 09:43:36'),
(73, 9, '2000.00', '2025-08-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-92926', '', 1, '2026-07-08 09:43:54'),
(74, 9, '2000.00', '2025-09-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-31391', '', 1, '2026-07-08 09:44:38'),
(75, 9, '2000.00', '2025-10-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-99856', '', 1, '2026-07-08 09:44:53'),
(76, 9, '2000.00', '2025-11-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-73045', '', 1, '2026-07-08 09:45:10'),
(77, 9, '2000.00', '2025-12-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-17293', '', 1, '2026-07-08 09:45:35'),
(78, 9, '2000.00', '2026-01-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-65595', '', 1, '2026-07-08 09:45:54'),
(79, 9, '2000.00', '2026-02-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-32724', '', 1, '2026-07-08 09:46:09'),
(80, 9, '2000.00', '2026-03-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-33719', '', 1, '2026-07-08 09:46:24'),
(81, 9, '2000.00', '2026-04-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260708-09120', '', 1, '2026-07-08 09:46:57'),
(82, 10, '2000.00', '2025-03-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-91276', '', 1, '2026-07-08 22:08:44'),
(83, 10, '2000.00', '2025-04-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-88511', '', 1, '2026-07-08 22:09:06'),
(84, 10, '5000.00', '2025-05-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-47286', '', 1, '2026-07-08 22:10:00'),
(85, 10, '2000.00', '2025-06-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-81926', '', 1, '2026-07-08 22:10:18'),
(86, 10, '2000.00', '2025-07-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-99747', '', 1, '2026-07-08 22:10:34'),
(87, 10, '2000.00', '2025-08-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-09097', '', 1, '2026-07-08 22:11:12'),
(88, 10, '2000.00', '2025-09-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-02052', '', 1, '2026-07-08 22:11:26'),
(90, 10, '2000.00', '2025-10-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-53204', '', 1, '2026-07-08 22:12:18'),
(91, 10, '2000.00', '2025-11-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-03127', '', 1, '2026-07-08 22:12:31'),
(92, 10, '2000.00', '2025-12-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-24277', '', 1, '2026-07-08 22:12:43'),
(93, 10, '2000.00', '2026-01-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-38877', '', 1, '2026-07-08 22:13:04'),
(94, 10, '2000.00', '2026-02-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-78721', '', 1, '2026-07-08 22:13:16'),
(95, 10, '2000.00', '2026-03-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-09464', '', 1, '2026-07-08 22:13:31'),
(96, 10, '2000.00', '2026-04-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260709-73750', '', 1, '2026-07-08 22:13:55'),
(97, 11, '2000.00', '2025-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-33224', '', 1, '2026-07-09 22:35:37'),
(98, 11, '2000.00', '2025-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-71300', '', 1, '2026-07-09 22:35:54'),
(99, 11, '5000.00', '2025-05-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-55960', '', 1, '2026-07-09 22:36:42'),
(100, 11, '2000.00', '2025-06-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-81310', '', 1, '2026-07-09 22:37:08'),
(101, 11, '2000.00', '2025-07-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-18646', '', 1, '2026-07-09 22:37:21'),
(102, 11, '2000.00', '2025-08-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-79928', '', 1, '2026-07-09 22:37:34'),
(103, 11, '2000.00', '2025-09-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-75854', '', 1, '2026-07-09 22:37:54'),
(104, 11, '2000.00', '2025-10-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-99875', '', 1, '2026-07-09 22:38:19'),
(105, 11, '2000.00', '2025-11-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-34179', '', 1, '2026-07-09 22:38:32'),
(106, 11, '2000.00', '2025-12-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-02593', '', 1, '2026-07-09 22:38:50'),
(108, 11, '2000.00', '2026-01-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-61233', '', 1, '2026-07-09 22:39:17'),
(109, 11, '2000.00', '2026-02-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-17516', '', 1, '2026-07-09 22:39:31'),
(110, 11, '2000.00', '2026-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-51774', '', 1, '2026-07-09 22:39:44'),
(111, 11, '2000.00', '2026-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-37215', '', 1, '2026-07-09 22:40:00'),
(112, 12, '2000.00', '2025-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-21174', '', 1, '2026-07-09 22:46:46'),
(113, 12, '2000.00', '2025-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-53668', '', 1, '2026-07-09 22:47:08'),
(114, 12, '5000.00', '2025-05-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-08386', '', 1, '2026-07-09 22:47:32'),
(115, 12, '2000.00', '2025-06-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-26361', '', 1, '2026-07-09 22:47:49'),
(116, 12, '2000.00', '2025-07-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-92155', '', 1, '2026-07-09 22:48:07'),
(117, 12, '2000.00', '2025-08-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-70573', '', 1, '2026-07-09 22:48:19'),
(118, 12, '2000.00', '2025-09-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-65860', '', 1, '2026-07-09 22:48:35'),
(120, 12, '2000.00', '2025-10-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-93137', '', 1, '2026-07-09 22:49:23'),
(121, 12, '2000.00', '2025-11-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-33153', '', 1, '2026-07-09 22:49:36'),
(122, 12, '2000.00', '2025-12-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-88575', '', 1, '2026-07-09 22:49:52'),
(123, 12, '2000.00', '2026-01-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-48615', '', 1, '2026-07-09 22:50:09'),
(124, 12, '2000.00', '2026-02-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-65145', '', 1, '2026-07-09 22:50:26'),
(125, 12, '2000.00', '2026-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-63871', '', 1, '2026-07-09 22:50:39'),
(126, 12, '2000.00', '2026-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-72029', '', 1, '2026-07-09 22:50:54'),
(127, 13, '2000.00', '2025-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-24892', '', 1, '2026-07-09 23:03:55'),
(128, 13, '2000.00', '2025-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-58874', '', 1, '2026-07-09 23:04:12'),
(129, 13, '5000.00', '2025-05-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-93769', '', 1, '2026-07-09 23:04:35'),
(130, 13, '2000.00', '2025-06-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-51851', '', 1, '2026-07-09 23:04:51'),
(131, 13, '2000.00', '2025-07-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-84539', '', 1, '2026-07-09 23:05:07'),
(132, 13, '2000.00', '2025-08-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-60753', '', 1, '2026-07-09 23:05:25'),
(134, 13, '2000.00', '2025-09-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-52197', '', 1, '2026-07-09 23:06:03'),
(135, 13, '2000.00', '2025-10-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-88810', '', 1, '2026-07-09 23:06:16'),
(136, 13, '2000.00', '2025-11-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-24454', '', 1, '2026-07-09 23:06:29'),
(137, 13, '2000.00', '2025-12-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-85845', '', 1, '2026-07-09 23:06:45'),
(138, 13, '2000.00', '2026-01-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-11063', '', 1, '2026-07-09 23:06:59'),
(139, 13, '2000.00', '2026-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-89835', '', 1, '2026-07-09 23:07:21'),
(140, 13, '2000.00', '2026-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-21482', '', 1, '2026-07-09 23:07:36'),
(141, 13, '2000.00', '2026-02-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-85262', '', 1, '2026-07-09 23:09:01'),
(142, 14, '2000.00', '2025-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-59607', '', 1, '2026-07-10 13:35:03'),
(143, 14, '2000.00', '2025-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-58093', '', 1, '2026-07-10 13:35:21'),
(144, 14, '5000.00', '2025-05-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-16500', '', 1, '2026-07-10 13:35:43'),
(145, 14, '2000.00', '2025-06-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-51282', '', 1, '2026-07-10 13:36:01'),
(146, 14, '2000.00', '2025-07-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-05584', '', 1, '2026-07-10 13:36:15'),
(147, 14, '2000.00', '2025-08-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-51396', '', 1, '2026-07-10 13:36:49'),
(149, 14, '2000.00', '2025-09-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-50387', '', 1, '2026-07-10 13:37:30'),
(150, 14, '2000.00', '2025-10-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-76221', '', 1, '2026-07-10 13:37:44'),
(151, 14, '2000.00', '2025-11-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-09757', '', 1, '2026-07-10 13:37:59'),
(152, 14, '2000.00', '2025-12-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-19553', '', 1, '2026-07-10 13:38:15'),
(153, 14, '2000.00', '2026-01-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-04276', '', 1, '2026-07-10 13:38:32'),
(154, 14, '2000.00', '2026-02-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-13557', '', 1, '2026-07-10 13:38:45'),
(155, 14, '2000.00', '2026-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-53908', '', 1, '2026-07-10 13:38:58'),
(156, 14, '2000.00', '2026-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-53775', '', 1, '2026-07-10 13:39:14'),
(157, 15, '2000.00', '2025-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-56182', '', 1, '2026-07-10 13:41:54'),
(158, 15, '2000.00', '2025-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-68203', '', 1, '2026-07-10 13:42:12'),
(160, 15, '5000.00', '2025-05-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-53259', '', 1, '2026-07-10 13:42:41'),
(161, 15, '2000.00', '2025-06-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-47345', '', 1, '2026-07-10 13:44:00'),
(162, 15, '2000.00', '2025-07-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-32523', '', 1, '2026-07-10 13:44:17'),
(163, 15, '2000.00', '2025-08-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-60936', '', 1, '2026-07-10 13:44:47'),
(164, 15, '2000.00', '2025-09-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-29629', '', 1, '2026-07-10 13:45:01'),
(165, 15, '2000.00', '2025-10-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-60092', '', 1, '2026-07-10 13:45:18'),
(167, 15, '2000.00', '2025-11-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-13793', '', 1, '2026-07-10 15:10:02'),
(168, 15, '2000.00', '2025-12-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-70794', '', 1, '2026-07-10 15:10:14'),
(169, 15, '2000.00', '2026-01-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-72734', '', 1, '2026-07-10 15:10:29'),
(170, 15, '2000.00', '2026-02-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-85421', '', 1, '2026-07-10 16:17:09'),
(171, 15, '2000.00', '2026-03-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-15604', '', 1, '2026-07-10 16:17:27'),
(172, 15, '2000.00', '2026-04-10', 'regular', NULL, NULL, 'cash', '', 'DEP-20260710-78878', '', 1, '2026-07-10 16:17:47'),
(174, 7, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-62240', '', 1, '2026-07-11 20:39:03'),
(175, 7, '5000.00', '2026-06-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-64909', '', 1, '2026-07-11 20:39:33'),
(176, 11, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-61500', '', 1, '2026-07-11 20:40:26'),
(177, 11, '5000.00', '2026-06-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-99860', '', 1, '2026-07-11 20:41:37'),
(178, 6, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-16036', '', 1, '2026-07-11 20:42:17'),
(179, 6, '5000.00', '2026-06-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-72798', '', 1, '2026-07-11 20:42:36'),
(180, 9, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-85247', '', 1, '2026-07-11 20:43:22'),
(181, 9, '5000.00', '2026-06-09', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-17351', '', 1, '2026-07-11 20:43:53'),
(182, 10, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-76595', '', 1, '2026-07-11 23:27:43'),
(184, 15, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-56168', '', 1, '2026-07-11 23:31:00'),
(185, 12, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-67964', '', 1, '2026-07-11 23:31:28'),
(186, 14, '2000.00', '2026-05-12', 'regular', NULL, NULL, 'cash', '', 'DEP-20260712-61432', '', 1, '2026-07-11 23:32:59'),
(207, 13, '5000.00', '2026-06-15', 'regular', NULL, NULL, 'cash', '', 'DEP-20260715-13310', '', 1, '2026-07-15 05:57:52'),
(208, 13, '2000.00', '2026-05-18', 'regular', NULL, NULL, 'cash', '', 'DEP-20260718-23979', '', 1, '2026-07-18 08:45:33'),
(213, NULL, '-5000.00', '2026-05-10', 'expense', NULL, NULL, 'cash', 'EXP-16', 'EXP-20260720-00016', 'খরচ: গরুর খাবার  খরচ - গরুর খাবারের জন্য  খরচ', 1, '2026-07-20 08:13:00'),
(218, NULL, '-5850.00', '2025-07-22', 'expense', NULL, NULL, 'cash', 'EXP-17', 'EXP-20260722-00017', 'খরচ: ২৫ সালের ফসল  চাষের খরচ - 25 সালের ফসল  চাষের  খরচ', 1, '2026-07-22 07:56:36'),
(221, NULL, '-23550.00', '2026-04-22', 'expense', NULL, NULL, 'cash', 'EXP-18', 'EXP-20260723-00018', 'খরচ: 26 সালের ফসল চাষের খরচ - ২০২৬ সালের  ফসল চাষের  খরচ', 1, '2026-07-23 17:51:38'),
(222, 12, '5000.00', '2026-06-24', 'regular', NULL, NULL, 'cash', '', 'DEP-20260724-28196', '', 1, '2026-07-23 19:55:57'),
(252, 15, '5000.00', '2026-06-01', 'regular', NULL, NULL, 'cash', '', 'DEP-20260801-39085', '', 1, '2026-07-31 19:30:03'),
(253, 15, '2000.00', '2026-07-01', 'regular', NULL, NULL, 'cash', '', 'DEP-20260801-00449', '', 1, '2026-07-31 19:30:51'),
(254, 15, '500.00', '2026-08-01', 'fine', 39, 'fine_payment', '0', '', '', 'Fine payment for fine ID: 71', 1, '2026-07-31 21:52:05'),
(255, 13, '200.00', '2026-08-01', 'fine', 40, 'fine_payment', '0', '', '', 'Fine payment for fine ID: 72', 1, '2026-07-31 21:56:53'),
(256, 15, '500.00', '2026-08-01', 'fine', 41, 'fine_payment', '0', '', '', 'Fine payment for fine ID: 82', 1, '2026-07-31 22:23:42'),
(257, 6, '2000.00', '2026-07-08', 'regular', NULL, NULL, 'cash', '', 'DEP-20260808-19426', 'ফাইন পরিশোধ: ৳500 (1 টি ফাইন)', 1, '2026-08-08 14:39:08'),
(258, 10, '2000.00', '2026-07-11', 'regular', NULL, NULL, 'cash', '', 'DEP-20260811-42944', '', 1, '2026-08-11 16:06:44'),
(259, 10, '5000.00', '2026-06-11', 'regular', NULL, NULL, 'cash', '', 'DEP-20260811-46956', '', 1, '2026-08-11 16:07:13'),
(261, 11, '2000.00', '2026-07-20', 'regular', NULL, NULL, 'cash', '', 'DEP-20260820-83527', '', 1, '2026-08-20 06:15:45'),
(262, 13, '2000.00', '2026-07-20', 'regular', NULL, NULL, 'cash', '', 'DEP-20260820-66942', 'ফাইন পরিশোধ: ৳500 (1 টি ফাইন)', 1, '2026-08-20 06:18:48'),
(263, 9, '2000.00', '2026-07-20', 'regular', NULL, NULL, 'cash', '', 'DEP-20260820-98612', 'ফাইন পরিশোধ: ৳500 (1 টি ফাইন)', 1, '2026-08-20 06:20:34'),
(264, 7, '2000.00', '2026-07-20', 'regular', NULL, NULL, 'cash', '', 'DEP-20260820-27547', 'ফাইন পরিশোধ: ৳500 (1 টি ফাইন)', 1, '2026-08-20 06:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` enum('office','salary','maintenance','event','other') NOT NULL DEFAULT 'other',
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `title`, `category`, `amount`, `expense_date`, `receipt_no`, `description`, `receipt_file`, `created_by`, `created_at`) VALUES
(16, 'গরুর খাবার  খরচ', 'maintenance', '5000.00', '2026-05-10', NULL, 'গরুর খাবারের জন্য  খরচ', NULL, 1, '2026-07-20 08:13:00'),
(17, '২৫ সালের ফসল  চাষের খরচ', 'maintenance', '5850.00', '2025-07-22', NULL, '25 সালের ফসল  চাষের  খরচ', NULL, 1, '2026-07-22 07:56:36'),
(18, '26 সালের ফসল চাষের খরচ', 'maintenance', '23550.00', '2026-04-22', NULL, '২০২৬ সালের  ফসল চাষের  খরচ', NULL, 1, '2026-07-22 08:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `fine_amount` decimal(12,2) NOT NULL DEFAULT 200.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `waived_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','partially_paid','paid','waived') DEFAULT 'pending',
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fines`
--

INSERT INTO `fines` (`id`, `member_id`, `month_year`, `fine_amount`, `paid_amount`, `waived_amount`, `status`, `due_date`, `paid_date`, `notes`, `created_at`, `updated_at`) VALUES
(45, 7, '2025-04-01', '100.00', '100.00', '0.00', 'paid', '2025-04-01', '2026-07-26', 'Manual fine created for April 2025', '2026-07-26 07:04:37', '2026-07-26 07:05:00'),
(47, 7, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:07:30', '2026-07-26 08:09:01'),
(48, 10, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:09:48', '2026-07-26 08:09:56'),
(49, 15, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:10:21', '2026-07-26 08:10:30'),
(50, 6, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:10:57', '2026-07-26 08:11:04'),
(51, 12, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:11:36', '2026-07-26 08:11:45'),
(52, 13, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:12:13', '2026-07-26 08:14:56'),
(53, 9, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:15:17', '2026-07-26 08:15:25'),
(54, 11, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:15:56', '2026-07-26 08:16:01'),
(55, 14, '2025-07-01', '300.00', '300.00', '0.00', 'paid', '2025-07-15', '2026-07-26', 'Manual fine created for July 2025', '2026-07-26 08:16:29', '2026-07-26 08:16:40'),
(56, 10, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:33:23', '2026-07-26 08:33:33'),
(57, 6, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:33:54', '2026-07-26 08:34:06'),
(58, 12, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:54:56', '2026-07-26 08:55:05'),
(59, 7, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:55:36', '2026-07-26 08:55:44'),
(60, 13, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:57:33', '2026-07-26 08:57:42'),
(61, 11, '2025-11-01', '500.00', '500.00', '0.00', 'paid', '2025-11-15', '2026-07-26', 'Manual fine created for November 2025', '2026-07-26 08:58:10', '2026-07-26 08:58:20'),
(62, 9, '2025-11-01', '500.00', '500.00', '0.00', 'paid', '2025-11-15', '2026-07-26', 'Manual fine created for November 2025', '2026-07-26 08:58:59', '2026-07-26 08:59:11'),
(63, 14, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-07-26', 'Manual fine created for December 2025', '2026-07-26 08:59:37', '2026-07-26 08:59:44'),
(64, 10, '2025-04-01', '100.00', '100.00', '0.00', 'paid', '2025-04-15', '2026-07-26', 'Manual fine created for April 2025', '2026-07-26 09:07:23', '2026-07-26 09:07:27'),
(65, 13, '2025-04-01', '100.00', '100.00', '0.00', 'paid', '2025-04-15', '2026-07-26', 'Manual fine created for April 2025', '2026-07-26 09:08:46', '2026-07-26 09:08:55'),
(66, 11, '2025-04-01', '100.00', '100.00', '0.00', 'paid', '2025-04-15', '2026-07-26', 'Manual fine created for April 2025', '2026-07-26 09:09:23', '2026-07-26 09:09:30'),
(67, 12, '2025-06-01', '200.00', '200.00', '0.00', 'paid', '2025-06-15', '2026-07-26', 'Manual fine created for June 2025', '2026-07-26 13:42:43', '2026-07-26 13:42:52'),
(68, 12, '2025-09-01', '200.00', '200.00', '0.00', 'paid', '2025-09-15', '2026-07-26', 'Manual fine created for September 2025', '2026-07-26 13:43:30', '2026-07-26 13:43:38'),
(69, 13, '2025-06-01', '200.00', '200.00', '0.00', 'paid', '2025-06-15', '2026-07-26', 'Manual fine created for June 2025', '2026-07-26 13:46:10', '2026-07-26 13:46:20'),
(70, 9, '2025-06-01', '200.00', '200.00', '0.00', 'paid', '2025-06-15', '2026-07-26', 'Manual fine created for June 2025', '2026-07-26 13:56:17', '2026-07-26 13:56:24'),
(71, 15, '2025-12-01', '500.00', '500.00', '0.00', 'paid', '2025-12-15', '2026-08-01', 'Manual fine created for December 2025', '2026-07-31 21:51:59', '2026-07-31 21:52:05'),
(72, 13, '2026-05-01', '200.00', '200.00', '0.00', 'paid', '2026-05-15', '2026-08-01', 'Manual fine created for May 2026', '2026-07-31 21:56:45', '2026-07-31 21:56:53'),
(82, 15, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-08-01', 'Manual fine created for July 2026', '2026-07-31 22:23:33', '2026-07-31 22:23:42'),
(84, 6, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-07-08', 'কোনো জমা নেই: July 2026', '2026-08-01 08:17:30', '2026-08-08 14:39:08'),
(86, 7, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-07-20', 'কোনো জমা নেই: July 2026', '2026-08-01 08:17:30', '2026-08-20 06:21:12'),
(87, 13, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-07-20', 'কোনো জমা নেই: July 2026', '2026-08-01 08:17:30', '2026-08-20 06:18:48'),
(88, 9, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-07-20', 'কোনো জমা নেই: July 2026', '2026-08-01 08:17:30', '2026-08-20 06:20:34'),
(89, 11, '2026-07-01', '500.00', '500.00', '0.00', 'paid', '2026-07-15', '2026-08-20', 'কোনো জমা নেই: July 2026', '2026-08-01 08:17:30', '2026-08-20 06:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `fine_payments`
--

CREATE TABLE `fine_payments` (
  `id` int(11) NOT NULL,
  `fine_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `transaction_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fine_payments`
--

INSERT INTO `fine_payments` (`id`, `fine_id`, `amount`, `payment_date`, `payment_method`, `transaction_no`, `receipt_no`, `notes`, `created_at`) VALUES
(13, 45, '100.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 07:05:00'),
(15, 47, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:09:01'),
(16, 48, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:09:56'),
(17, 49, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:10:30'),
(18, 50, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:11:04'),
(19, 51, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:11:45'),
(20, 52, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:14:56'),
(21, 53, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:15:25'),
(22, 54, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:16:01'),
(23, 55, '300.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:16:40'),
(24, 56, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:33:33'),
(25, 57, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:34:06'),
(26, 58, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:55:05'),
(27, 59, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:55:44'),
(28, 60, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:57:42'),
(29, 61, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:58:20'),
(30, 62, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:59:11'),
(31, 63, '500.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 08:59:44'),
(32, 64, '100.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 09:07:27'),
(33, 65, '100.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 09:08:55'),
(34, 66, '100.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 09:09:30'),
(35, 67, '200.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 13:42:52'),
(36, 68, '200.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 13:43:38'),
(37, 69, '200.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 13:46:20'),
(38, 70, '200.00', '2026-07-26', 'cash', '', '', '', '2026-07-26 13:56:24'),
(39, 71, '500.00', '2026-08-01', 'cash', '', '', '', '2026-07-31 21:52:05'),
(40, 72, '200.00', '2026-08-01', 'cash', '', '', '', '2026-07-31 21:56:53'),
(41, 82, '500.00', '2026-08-01', 'cash', '', '', '', '2026-07-31 22:23:42'),
(42, 84, '500.00', '2026-07-08', 'cash', '', 'FP-20260808-66069', 'সঞ্চয় জমার সাথে ফাইন পরিশোধ', '2026-08-08 14:39:08'),
(43, 89, '500.00', '2026-08-20', 'cash', '', 'FP-20260820-46902', 'সঞ্চয় জমার সাথে ফাইন পরিশোধ', '2026-08-20 06:14:12'),
(44, 87, '500.00', '2026-07-20', 'cash', '', 'FP-20260820-68751', 'সঞ্চয় জমার সাথে ফাইন পরিশোধ', '2026-08-20 06:18:48'),
(45, 88, '500.00', '2026-07-20', 'cash', '', 'FP-20260820-75603', 'সঞ্চয় জমার সাথে ফাইন পরিশোধ', '2026-08-20 06:20:34'),
(46, 86, '500.00', '2026-07-20', 'cash', '', 'FP-20260820-49383', 'সঞ্চয় জমার সাথে ফাইন পরিশোধ', '2026-08-20 06:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `fine_settings`
--

CREATE TABLE `fine_settings` (
  `id` int(11) NOT NULL,
  `fine_name` varchar(100) NOT NULL DEFAULT 'Monthly Late Fee',
  `fine_amount` decimal(12,2) NOT NULL DEFAULT 200.00,
  `due_day` int(2) NOT NULL DEFAULT 10 COMMENT 'Day of month when deposit is due',
  `grace_period_days` int(3) NOT NULL DEFAULT 0,
  `last_check_date` datetime DEFAULT NULL,
  `last_check_month` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fine_settings`
--

INSERT INTO `fine_settings` (`id`, `fine_name`, `fine_amount`, `due_day`, `grace_period_days`, `last_check_date`, `last_check_month`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Monthly Late Fee', '200.00', 20, 0, '2026-08-01 14:17:30', '2026-07-01', 1, '2026-07-07 13:27:44', '2026-08-20 06:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `investment_type` enum('business','land','fd','other') NOT NULL DEFAULT 'business',
  `amount` decimal(15,2) NOT NULL,
  `investment_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `expected_profit` decimal(15,2) DEFAULT NULL,
  `status` enum('active','completed','failed','partial') NOT NULL DEFAULT 'active',
  `documents` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `user_id`, `title`, `description`, `investment_type`, `amount`, `investment_date`, `expected_return_date`, `actual_return_date`, `expected_profit`, `status`, `documents`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 0, 'গরু কিনা', '', 'business', '70500.00', '2026-05-10', '0000-00-00', NULL, '0.00', 'active', NULL, 1, '2026-07-20 08:10:57', '2026-07-20 08:10:57'),
(6, 0, 'জমি ভাড়া', '5 কাটা জমি ভাড়া নেওয়া হয়েছে ।', 'land', '120000.00', '2026-02-07', '0000-00-00', NULL, '0.00', 'active', NULL, 1, '2026-07-20 08:17:55', '2026-07-20 08:17:55'),
(7, 0, 'জমি ভাড়া', 'জমি ভাড়া নেপয়া হয়েছে 3.5 কাটা', 'business', '100000.00', '2026-06-20', '0000-00-00', NULL, '0.00', 'active', NULL, 1, '2026-07-20 08:23:53', '2026-07-20 08:23:53');

-- --------------------------------------------------------

--
-- Table structure for table `investment_returns`
--

CREATE TABLE `investment_returns` (
  `id` int(11) NOT NULL,
  `investment_id` int(11) NOT NULL,
  `return_amount` decimal(15,2) NOT NULL,
  `return_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investment_returns`
--

INSERT INTO `investment_returns` (`id`, `investment_id`, `return_amount`, `return_date`, `note`, `created_by`, `created_at`) VALUES
(10, 6, '6850.00', '2025-07-22', 'ফসল চাষের  লাভ', 1, '2026-07-22 08:20:44'),
(11, 7, '1250.00', '2026-07-22', 'ফসল চাষের  লাভ', 1, '2026-07-22 08:22:21');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `loan_no` varchar(30) NOT NULL,
  `member_id` int(11) NOT NULL,
  `loan_type` enum('personal','business','emergency','education') NOT NULL DEFAULT 'personal',
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `tenure_months` int(11) NOT NULL,
  `monthly_installment` decimal(12,2) NOT NULL,
  `total_payable` decimal(12,2) NOT NULL,
  `loan_date` date NOT NULL,
  `first_installment_date` date NOT NULL,
  `total_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_balance` decimal(12,2) NOT NULL,
  `purpose` text DEFAULT NULL,
  `guarantor_name` varchar(100) DEFAULT NULL,
  `guarantor_phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','active','completed','defaulted','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `documents` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_date` date DEFAULT NULL,
  `late_fine` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','partial','overdue') NOT NULL DEFAULT 'pending',
  `receipt_no` varchar(50) DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `spouse_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nid` varchar(30) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `nid_photo` varchar(255) DEFAULT NULL,
  `join_date` date NOT NULL,
  `monthly_deposit` decimal(10,2) DEFAULT 500.00,
  `share_count` int(11) NOT NULL DEFAULT 1,
  `share_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nominee_name` varchar(100) DEFAULT NULL,
  `nominee_relation` varchar(50) DEFAULT NULL,
  `nominee_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended','deceased') NOT NULL DEFAULT 'active',
  `note` text DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `password_changed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_id`, `name`, `father_name`, `mother_name`, `spouse_name`, `phone`, `alt_phone`, `email`, `nid`, `date_of_birth`, `address`, `photo`, `nid_photo`, `join_date`, `monthly_deposit`, `share_count`, `share_value`, `nominee_name`, `nominee_relation`, `nominee_phone`, `status`, `note`, `password`, `last_login`, `created_by`, `created_at`, `updated_at`, `password_changed`) VALUES
(6, 'SM260001', 'আবিদ হাসান', '', '', '', '01951223106', '', '', '', '0000-00-00', '', '/uploads/members/member_6_1783461872.jpeg', '', '2026-07-06', '2000.00', 0, '0.00', '', '', '', 'active', '', '$2y$10$AV0v/tqjOGKz7tG7Jbm5lOr3jMLEEhWuYdt07dwUI8g5Iu2zAt70O', '2026-08-22 16:26:20', 1, '2026-07-05 20:32:31', '2026-08-22 10:26:20', 1),
(7, 'SM260002', 'জাহিদ হাসান', '', '', '', '01949195619', '', '', '', '0000-00-00', '', '/uploads/members/member_7_1783355675.jpg', '', '2026-07-06', '2000.00', 0, '0.00', '0', '', '', 'active', '', '$2y$10$N0oTTm4U7m/fTWVq7iMWsO8bA3f0uxKrJO7zUDTWQL849oTCDwh5O', '2026-08-22 22:57:20', 1, '2026-07-05 20:36:15', '2026-08-22 16:57:20', 0),
(9, 'SM260003', 'ফেরদৌস  আহামেদ', '', '', '', '01816469499', '', '', '', '0000-00-00', '', '/uploads/members/member_9_1786026919.jpg', '', '2026-07-08', '2000.00', 0, '0.00', '0', '', '', 'active', '', '$2y$10$TlwfOEsu5aBMXpE8ugv5uOskjV.IThQSLXHOSoyP.3JXnoPCKf08a', '2026-08-06 20:29:21', 1, '2026-07-08 09:34:11', '2026-08-06 14:35:19', 0),
(10, 'SM260004', 'আফজাল আহমেদ', '', '', '', '01646985026', '', '', '', '0000-00-00', '', '/uploads/members/member_10_1786033036.png', '', '2026-07-09', '2000.00', 0, '0.00', '', '', '', 'active', '', '$2y$10$T1HQx07r1K6PQpkWsxiXZOtkA7uvaFJOKs.rGx7ysIrSsL6xxZrPK', '2026-08-22 16:30:26', 1, '2026-07-08 22:08:20', '2026-08-22 10:30:26', 1),
(11, 'SM260005', 'মাহাবুব রহমান', '', '', '', '01539134548', '', '', '', '0000-00-00', '', '/uploads/members/member_11_1786038364.jpg', '', '2026-07-09', '2000.00', 0, '0.00', '', '', '', 'active', '', '$2y$10$xLMWuYcuEiZ4OEAghzUKruEtEEFdmtyBOQBX7ByHUxf7WEDczpjvW', '2026-08-22 22:42:59', 1, '2026-07-09 16:24:47', '2026-08-22 16:42:59', 1),
(12, 'SM260006', 'আল মামুন', '', '', '', '01341591257', '', '', '', '0000-00-00', '', '', '', '2026-07-10', '2000.00', 0, '0.00', '0', '', '', 'active', '', '$2y$10$fNvlqI08bmZeLohRWbNWn.B5AfXpzFtzMqeuTw6UD7oUG7whXjMRO', NULL, 1, '2026-07-09 22:46:27', '2026-07-31 19:21:37', 0),
(13, 'SM260007', 'ফারুক আহামেদ', '', '', '', '01832726266', '', '', '', '0000-00-00', '', '', '', '2026-07-10', '2000.00', 0, '0.00', '', '', '', 'active', '', '$2y$10$FuZKF42T0Ox.fTT1REowPuoZ7TQDvZmMNzC3mP7aBbVyPB3IX.mvS', '2026-07-18 14:34:55', 1, '2026-07-09 23:03:30', '2026-07-18 08:34:55', 0),
(14, 'SM260008', 'হাবিবুর রহমান', '', '', '', '01997094140', '', '', '', '0000-00-00', '', '', '', '2026-07-10', '2000.00', 0, '0.00', '0', '', '', 'active', '', '$2y$10$maP7XIGVA2N7u7YiAd2Qt.Gra9JPi1P9G7Cnuqb72PhbsjPsKsUVO', '2026-08-03 13:51:13', 1, '2026-07-10 13:34:47', '2026-08-03 07:51:13', 0),
(15, 'SM260009', 'আমিরুল ইসলাম', '', '', '', '01596485693', '', '', '', '0000-00-00', '', '', '', '2026-07-10', '2000.00', 0, '0.00', '0', '', '', 'active', '', '$2y$10$UsDhMeCSJV3FEdgZ15Ekfe1/2qLUvZGpw/wvj790jcpHUB6vZXKAS', '2026-08-22 16:32:07', 1, '2026-07-10 13:41:31', '2026-08-22 10:32:07', 0);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_dues`
--

CREATE TABLE `monthly_dues` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `due_year` int(4) NOT NULL,
  `due_month` tinyint(2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_date` date DEFAULT NULL,
  `status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `receipt_no` varchar(50) DEFAULT NULL,
  `fine_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `collected_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_dues`
--

INSERT INTO `monthly_dues` (`id`, `member_id`, `due_year`, `due_month`, `amount`, `paid_amount`, `paid_date`, `status`, `receipt_no`, `fine_amount`, `collected_by`, `note`, `created_at`, `updated_at`) VALUES
(24, 6, 2026, 6, '2000.00', '5000.00', '2026-06-09', 'paid', 'DEP-20260712-72798', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(25, 7, 2026, 6, '2000.00', '5000.00', '2026-06-09', 'paid', 'DEP-20260712-64909', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(26, 9, 2026, 6, '2000.00', '5000.00', '2026-06-09', 'paid', 'DEP-20260712-17351', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(27, 10, 2026, 6, '2000.00', '2000.00', '2026-06-12', 'paid', 'DEP-20260712-72360', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(28, 11, 2026, 6, '2000.00', '5000.00', '2026-06-09', 'paid', 'DEP-20260712-99860', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(29, 12, 2026, 6, '2000.00', '5000.00', '2026-06-24', 'paid', 'DEP-20260724-28196', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(30, 13, 2026, 6, '2000.00', '5000.00', '2026-06-15', 'paid', 'DEP-20260715-13310', '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-31 18:59:19'),
(31, 14, 2026, 6, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-11 09:07:36', '2026-07-11 09:07:36'),
(33, 6, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(34, 7, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(35, 9, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(36, 10, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(37, 11, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(38, 12, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(39, 13, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(40, 14, 2026, 7, '2000.00', '0.00', NULL, 'unpaid', NULL, '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-07-31 18:58:31'),
(41, 15, 2026, 7, '2000.00', '2000.00', '2026-07-01', 'paid', 'DEP-20260801-00449', '0.00', NULL, NULL, '2026-07-29 14:52:28', '2026-08-02 21:15:54'),
(51, 6, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-16036', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(52, 7, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-62240', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(53, 9, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-85247', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(54, 10, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-76595', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(55, 11, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-61500', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(56, 12, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-67964', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(57, 13, 2026, 5, '2000.00', '2000.00', '2026-05-18', 'paid', 'DEP-20260718-23979', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(58, 14, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-61432', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45'),
(59, 15, 2026, 5, '2000.00', '2000.00', '2026-05-12', 'paid', 'DEP-20260712-56168', '0.00', NULL, NULL, '2026-07-29 14:52:45', '2026-07-29 14:52:45');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('due_reminder','profit_distribution','loan_installment','announcement','withdrawal','general') NOT NULL DEFAULT 'general',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `expiry_date` datetime DEFAULT NULL,
  `send_email` tinyint(1) DEFAULT 0,
  `send_sms` tinyint(1) DEFAULT 0,
  `target` enum('all','member','admin') NOT NULL DEFAULT 'all',
  `member_id` int(11) DEFAULT NULL,
  `member_name` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profit_distributions`
--

CREATE TABLE `profit_distributions` (
  `id` int(11) NOT NULL,
  `profit_loss_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `fiscal_year` varchar(10) NOT NULL,
  `member_deposit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `distribution_ratio` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `profit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','adjusted') NOT NULL DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profit_loss`
--

CREATE TABLE `profit_loss` (
  `id` int(11) NOT NULL,
  `fiscal_year` varchar(10) NOT NULL,
  `total_deposits` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_dues_collected` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_loan_interest` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_investment_return` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_income` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_loan_given` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_profit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `distributable_profit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reserve_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reserve_percentage` decimal(5,2) NOT NULL DEFAULT 10.00,
  `status` enum('draft','final') NOT NULL DEFAULT 'draft',
  `finalized_by` int(11) DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `key_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `key_value`, `description`, `updated_at`) VALUES
(1, 'somity_name', 'আল বারাকা কো-অপাঃ সোসাইটি', '', '2026-07-07 16:22:29'),
(2, 'somity_address', 'Trishal', '', '2026-07-06 20:33:13'),
(3, 'somity_phone', '+966576312339', '', '2026-08-02 07:18:29'),
(4, 'monthly_due_amount', '2000', '', '2026-07-06 20:36:17'),
(5, 'profit_share_method', 'deposit_ratio', '', '2026-07-05 07:50:35'),
(6, 'loan_interest_rate', '0', '', '2026-07-06 20:36:17'),
(7, 'financial_year_start', '7', '', '2026-07-05 07:50:52'),
(8, 'reserve_percentage', '0', '', '2026-07-06 20:36:17'),
(9, 'currency_symbol', '৳', '', '2026-08-01 09:07:03'),
(20, 'logo_url', '/images/logo_1783370218.jpeg', 'logo', '2026-08-02 07:10:32'),
(22, 'favicon_url', '', 'favicon', '2026-08-02 07:10:40'),
(23, 'messenger_page_id', 'abid.hassan.205580', 'Messenger', '2026-08-02 15:21:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `profile_photo_updated_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `profile_photo`, `profile_photo_updated_at`, `password`, `role`, `is_active`, `last_login`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@mail.com', '01700000000', '/uploads/admin/admin_1_1783368109.jpeg', '2026-07-06 20:01:49', '$2y$10$GNxufyU8r/RNvBkJz3AN7e/XKSvENDnKIfpBISVtBasdqF9ho01jq', 'admin', 1, '2026-09-06 01:47:44', NULL, '2026-06-27 13:19:51', '2026-09-05 19:47:44'),
(7, 'আবিদ হাসান', 'abidhassan3106@gmail.com', '01951223106', NULL, NULL, '$2y$10$gwzvM7KpK8byLO8iNuXHbOWyD/J4c1L9qOimm8iWyQ0wKdjUxwbDi', '', 1, NULL, NULL, '2026-08-09 19:58:58', '2026-08-09 19:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `withdrawal_date` date NOT NULL,
  `withdrawal_type` enum('partial','full','profit','emergency') NOT NULL DEFAULT 'partial',
  `reason` text DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','completed','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_deposit_date` (`deposit_date`),
  ADD KEY `idx_receipt_no` (`receipt_no`),
  ADD KEY `idx_reference` (`reference_id`,`reference_type`),
  ADD KEY `idx_deposit_type` (`deposit_type`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fine` (`member_id`,`month_year`),
  ADD KEY `idx_member_month` (`member_id`,`month_year`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `fine_payments`
--
ALTER TABLE `fine_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fine` (`fine_id`);

--
-- Indexes for table `fine_settings`
--
ALTER TABLE `fine_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `investment_returns`
--
ALTER TABLE `investment_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_investment_id` (`investment_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loan_no` (`loan_no`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_id` (`loan_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_id` (`member_id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `monthly_dues`
--
ALTER TABLE `monthly_dues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member_month` (`member_id`,`due_year`,`due_month`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_year_month` (`due_year`,`due_month`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `profit_distributions`
--
ALTER TABLE `profit_distributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member_year` (`member_id`,`fiscal_year`),
  ADD KEY `idx_profit_loss_id` (`profit_loss_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `profit_loss`
--
ALTER TABLE `profit_loss`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fiscal_year` (`fiscal_year`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1824;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `fine_payments`
--
ALTER TABLE `fine_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `fine_settings`
--
ALTER TABLE `fine_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `investment_returns`
--
ALTER TABLE `investment_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `monthly_dues`
--
ALTER TABLE `monthly_dues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT for table `profit_distributions`
--
ALTER TABLE `profit_distributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profit_loss`
--
ALTER TABLE `profit_loss`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `fk_deposits_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `investment_returns`
--
ALTER TABLE `investment_returns`
  ADD CONSTRAINT `fk_returns_investment` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_loans_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `fk_payments_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_dues`
--
ALTER TABLE `monthly_dues`
  ADD CONSTRAINT `fk_dues_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profit_distributions`
--
ALTER TABLE `profit_distributions`
  ADD CONSTRAINT `fk_dist_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dist_pl` FOREIGN KEY (`profit_loss_id`) REFERENCES `profit_loss` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_withdrawals_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
