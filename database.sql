-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: atsede_sunday_school
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ethiopian_year` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `promotion_done` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (1,2018,'2025-09-11',NULL,'active',0,'2026-05-08 22:30:45');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_assignments`
--

DROP TABLE IF EXISTS `attendance_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submitter_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`submitter_id`,`class_id`,`semester_id`),
  KEY `class_id` (`class_id`),
  KEY `semester_id` (`semester_id`),
  CONSTRAINT `attendance_assignments_ibfk_1` FOREIGN KEY (`submitter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_assignments_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_assignments`
--

LOCK TABLES `attendance_assignments` WRITE;
/*!40000 ALTER TABLE `attendance_assignments` DISABLE KEYS */;
INSERT INTO `attendance_assignments` VALUES (1,11,2,1,'2026-05-08 22:39:46'),(2,10,2,1,'2026-05-08 22:39:54'),(3,12,4,1,'2026-05-08 22:42:04'),(4,13,4,1,'2026-05-08 22:42:11');
/*!40000 ALTER TABLE `attendance_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_days`
--

DROP TABLE IF EXISTS `attendance_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_days` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_gregorian` date NOT NULL,
  `ethiopian_year` int(11) NOT NULL,
  `ethiopian_month` int(11) NOT NULL,
  `ethiopian_day` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `is_school_day` tinyint(1) DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`date_gregorian`),
  KEY `created_by` (`created_by`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `attendance_days_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_days`
--

LOCK TABLES `attendance_days` WRITE;
/*!40000 ALTER TABLE `attendance_days` DISABLE KEYS */;
INSERT INTO `attendance_days` VALUES (1,'2026-06-07',2018,9,30,'Sunday',NULL,0,NULL,1,'2026-05-10 20:04:10'),(2,'2026-04-12',2018,8,4,'Sunday',NULL,0,NULL,1,'2026-05-10 20:04:26');
/*!40000 ALTER TABLE `attendance_days` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','permission','late','excused') NOT NULL DEFAULT 'absent',
  `marked_by` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `local_uuid` varchar(36) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`student_id`,`class_id`,`attendance_date`),
  UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `marked_by` (`marked_by`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  CONSTRAINT `attendance_records_ibfk_4` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
INSERT INTO `attendance_records` VALUES (1,48,2,6,'2026-05-10','permission',10,'2026-05-10 16:19:13','2026-05-10 19:18:24',NULL,0),(4,48,2,6,'2026-05-09','permission',10,'2026-05-10 16:19:14','2026-05-10 19:18:26',NULL,0),(34,48,2,6,'2026-05-03','absent',10,'2026-05-10 19:18:30','2026-05-10 19:18:31',NULL,0),(40,48,2,6,'2026-05-02','permission',10,'2026-05-10 19:18:33','2026-05-10 19:18:33',NULL,0),(43,48,2,6,'2026-04-26','permission',10,'2026-05-10 19:18:34','2026-05-10 19:18:34',NULL,0),(46,48,2,6,'2026-04-25','permission',10,'2026-05-10 19:18:35','2026-05-10 19:18:35',NULL,0),(49,48,2,6,'2026-04-19','permission',10,'2026-05-10 19:18:37','2026-05-10 19:18:37',NULL,0),(52,48,2,6,'2026-04-18','permission',10,'2026-05-10 19:18:38','2026-05-10 19:18:38',NULL,0),(55,48,2,6,'2026-04-12','permission',10,'2026-05-10 19:18:40','2026-05-10 19:18:40',NULL,0),(58,48,2,6,'2026-04-11','permission',10,'2026-05-10 19:18:41','2026-05-10 19:18:42',NULL,0),(61,48,2,6,'2026-04-05','permission',10,'2026-05-10 19:18:48','2026-05-10 19:19:04',NULL,0),(64,48,2,6,'2026-04-04','absent',10,'2026-05-10 19:18:49','2026-05-10 19:19:01',NULL,0),(67,48,2,6,'2026-03-29','absent',10,'2026-05-10 19:18:50','2026-05-10 19:19:00',NULL,0),(70,48,2,6,'2026-03-28','absent',10,'2026-05-10 19:18:50','2026-05-10 19:18:59',NULL,0),(73,48,2,6,'2026-03-22','permission',10,'2026-05-10 19:18:52','2026-05-10 19:18:52',NULL,0),(76,48,2,6,'2026-03-21','present',10,'2026-05-10 19:18:53','2026-05-10 19:18:58',NULL,0),(79,48,2,6,'2026-03-15','present',10,'2026-05-10 19:18:55','2026-05-10 19:18:55',NULL,0),(82,48,2,6,'2026-03-14','present',10,'2026-05-10 19:18:56','2026-05-10 19:18:57',NULL,0);
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_tokens`
--

DROP TABLE IF EXISTS `auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `role` enum('admin','teacher','attendance_submitter','student') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_unique` (`token`),
  KEY `user_idx` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_tokens`
--

LOCK TABLES `auth_tokens` WRITE;
/*!40000 ALTER TABLE `auth_tokens` DISABLE KEYS */;
INSERT INTO `auth_tokens` VALUES (1,1,'a4c74c204dab8ec427226618402287c411fd53469dff2892ae16d069b415c8cc','admin','2026-09-09 20:54:04','2026-10-09 19:54:04',0);
/*!40000 ALTER TABLE `auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_event_targets`
--

DROP TABLE IF EXISTS `calendar_event_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_event_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `cet_division_fk` (`division_id`),
  KEY `cet_grade_fk` (`grade_id`),
  KEY `cet_class_fk` (`class_id`),
  KEY `cet_teacher_fk` (`teacher_id`),
  CONSTRAINT `cet_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cet_division_fk` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cet_event_fk` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cet_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cet_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_event_targets`
--

LOCK TABLES `calendar_event_targets` WRITE;
/*!40000 ALTER TABLE `calendar_event_targets` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_event_targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_event_type` (`event_type`),
  KEY `calendar_events_ibfk_1` (`created_by`),
  CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `grade_id` (`grade_id`),
  CONSTRAINT `classes_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,12,'7ኛ ክፍል (Grade 7)','የ7ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(2,11,'8ኛ ክፍል (Grade 8)','የ8ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(3,10,'9ኛ ክፍል (Grade 9)','የ9ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(4,9,'10ኛ ክፍል (Grade 10)','የ10ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(5,8,'11ኛ ክፍል (Grade 11)','የ11ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(6,7,'12ኛ ክፍል (Grade 12)','የ12ኛ ክፍል ሰንበት ትምህርት','2026-05-08 22:30:45'),(7,6,'1ኛ ክፍል (Grade 1)','የ1ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23'),(8,5,'2ኛ ክፍል (Grade 2)','የ2ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23'),(9,4,'3ኛ ክፍል (Grade 3)','የ3ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23'),(10,3,'4ኛ ክፍል (Grade 4)','የ4ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23'),(11,2,'5ኛ ክፍል (Grade 5)','የ5ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23'),(12,1,'6ኛ ክፍል (Grade 6)','የ6ኛ ክፍል ሰንበት ትምህርት (ህፃናት)','2026-09-09 18:01:23');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `divisions`
--

DROP TABLE IF EXISTS `divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `divisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'CHILDREN or YOUTH - stable machine key',
  `name_am` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `divisions`
--

LOCK TABLES `divisions` WRITE;
/*!40000 ALTER TABLE `divisions` DISABLE KEYS */;
INSERT INTO `divisions` VALUES (1,'CHILDREN','ህፃናት ክፍል','Children Division',1,'2026-09-09 17:56:51'),(2,'YOUTH','ወጣቶች ክፍል','Youth Division',2,'2026-09-09 17:56:51');
/*!40000 ALTER TABLE `divisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division_id` int(11) NOT NULL,
  `level_number` tinyint(4) NOT NULL COMMENT '1-12',
  `name_am` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `division_level_unique` (`division_id`,`level_number`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
INSERT INTO `grades` VALUES (1,1,6,'6ኛ ክፍል','Grade 6','2026-09-09 17:56:51'),(2,1,5,'5ኛ ክፍል','Grade 5','2026-09-09 17:56:51'),(3,1,4,'4ኛ ክፍል','Grade 4','2026-09-09 17:56:51'),(4,1,3,'3ኛ ክፍል','Grade 3','2026-09-09 17:56:51'),(5,1,2,'2ኛ ክፍል','Grade 2','2026-09-09 17:56:51'),(6,1,1,'1ኛ ክፍል','Grade 1','2026-09-09 17:56:51'),(7,2,12,'12ኛ ክፍል','Grade 12','2026-09-09 17:56:51'),(8,2,11,'11ኛ ክፍል','Grade 11','2026-09-09 17:56:51'),(9,2,10,'10ኛ ክፍል','Grade 10','2026-09-09 17:56:51'),(10,2,9,'9ኛ ክፍል','Grade 9','2026-09-09 17:56:51'),(11,2,8,'8ኛ ክፍል','Grade 8','2026-09-09 17:56:51'),(12,2,7,'7ኛ ክፍል','Grade 7','2026-09-09 17:56:51');
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_plan_versions`
--

DROP TABLE IF EXISTS `lesson_plan_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_plan_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_plan_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `snapshot_json` longtext NOT NULL COMMENT 'Full field snapshot before this edit, as JSON',
  `changed_by` int(11) NOT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plan` (`lesson_plan_id`),
  CONSTRAINT `lpv_plan_fk` FOREIGN KEY (`lesson_plan_id`) REFERENCES `lesson_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_plan_versions`
--

LOCK TABLES `lesson_plan_versions` WRITE;
/*!40000 ALTER TABLE `lesson_plan_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `lesson_plan_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_plans`
--

DROP TABLE IF EXISTS `lesson_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_class` (`class_id`),
  KEY `idx_status` (`status`),
  KEY `lp_semester_fk` (`semester_id`),
  CONSTRAINT `lp_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lp_semester_fk` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lp_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_plans`
--

LOCK TABLES `lesson_plans` WRITE;
/*!40000 ALTER TABLE `lesson_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `lesson_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marking_schemes`
--

DROP TABLE IF EXISTS `marking_schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marking_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scheme` (`teacher_id`,`class_id`,`semester_id`),
  KEY `semester_id` (`semester_id`),
  KEY `idx_marking_scheme_teacher` (`teacher_id`),
  KEY `idx_marking_scheme_class` (`class_id`),
  CONSTRAINT `marking_schemes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marking_schemes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marking_schemes_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marking_schemes`
--

LOCK TABLES `marking_schemes` WRITE;
/*!40000 ALTER TABLE `marking_schemes` DISABLE KEYS */;
/*!40000 ALTER TABLE `marking_schemes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marks`
--

DROP TABLE IF EXISTS `marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_student_semester` (`teacher_id`,`student_id`,`semester_id`),
  UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  KEY `semester_id` (`semester_id`),
  CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_4` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marks`
--

LOCK TABLES `marks` WRITE;
/*!40000 ALTER TABLE `marks` DISABLE KEYS */;
INSERT INTO `marks` VALUES (1,31,1,3,1,0.00,10.00,0.00,0.00,0.00,10.00,'2026-05-10 16:03:04','2026-05-10 16:03:04',NULL,0);
/*!40000 ALTER TABLE `marks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marks_backup`
--

DROP TABLE IF EXISTS `marks_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marks_backup`
--

LOCK TABLES `marks_backup` WRITE;
/*!40000 ALTER TABLE `marks_backup` DISABLE KEYS */;
/*!40000 ALTER TABLE `marks_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_reads`
--

DROP TABLE IF EXISTS `notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notif_user_unique` (`notification_id`,`user_id`),
  CONSTRAINT `nr_notif_fk` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_reads`
--

LOCK TABLES `notification_reads` WRITE;
/*!40000 ALTER TABLE `notification_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_targets`
--

DROP TABLE IF EXISTS `notification_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_id` (`notification_id`),
  CONSTRAINT `nt_notif_fk` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_targets`
--

LOCK TABLES `notification_targets` WRITE;
/*!40000 ALTER TABLE `notification_targets` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_event_id` int(11) DEFAULT NULL,
  `related_page` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `created_by` int(11) DEFAULT NULL COMMENT 'NULL = system-generated (e.g. exam reminder)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `related_event_id` (`related_event_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`related_event_id`) REFERENCES `calendar_events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_history`
--

DROP TABLE IF EXISTS `promotion_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `from_class_id` int(11) NOT NULL,
  `to_class_id` int(11) NOT NULL,
  `from_academic_year` int(11) DEFAULT NULL,
  `to_academic_year` int(11) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `status` enum('promoted','repeated') NOT NULL,
  `promoted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `from_class_id` (`from_class_id`),
  KEY `to_class_id` (`to_class_id`),
  CONSTRAINT `promotion_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_history_ibfk_2` FOREIGN KEY (`from_class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `promotion_history_ibfk_3` FOREIGN KEY (`to_class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_history`
--

LOCK TABLES `promotion_history` WRITE;
/*!40000 ALTER TABLE `promotion_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of endpoint for deduplication',
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash_unique` (`endpoint_hash`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `ps_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_subscriptions`
--

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `semesters`
--

DROP TABLE IF EXISTS `semesters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `semesters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ethiopian_year` int(11) DEFAULT NULL,
  `semester_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `semesters`
--

LOCK TABLES `semesters` WRITE;
/*!40000 ALTER TABLE `semesters` DISABLE KEYS */;
INSERT INTO `semesters` VALUES (1,'2018 ዓ.ም ሁለተኛ ሴሚስተር','active','2026-02-16',NULL,'2026-05-08 22:30:45',2018,2);
/*!40000 ALTER TABLE `semesters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'admin_name_1','ዲ/ን ክብረአብ ዘለለም ','2026-05-10 20:25:46'),(2,'admin_phone_1','0939883508','2026-05-08 22:30:45'),(3,'admin_name_2','ተስፋሁን ባይህ ','2026-05-10 20:25:46'),(4,'admin_phone_2','0943854325','2026-05-08 22:30:45'),(5,'admin_title','የአጸደ ትጉሃን ትምህርት ክፍል ኃላፊ','2026-05-08 22:30:45'),(21,'vapid_public_key','BA4gIyAmzQvG_8pNcITvybi9h1AGicohPofXWtShAm3uAwFLigPRHtn9c26idCM-mmTNoQvbxlpTNnVIeHiOEwk','2026-09-09 20:11:48'),(22,'vapid_private_key','DE8bLbSto2_cFFkBgYH8Vztk3AQ8Nis3nUtYENRwyTM','2026-09-09 20:11:48'),(23,'vapid_subject','mailto:admin@atsedesundayschool.org','2026-09-09 20:11:48');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_logins`
--

DROP TABLE IF EXISTS `student_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `pin` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  CONSTRAINT `student_logins_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_logins`
--

LOCK TABLES `student_logins` WRITE;
/*!40000 ALTER TABLE `student_logins` DISABLE KEYS */;
INSERT INTO `student_logins` VALUES (1,1,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(2,2,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(3,3,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(4,4,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(5,5,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(6,6,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(7,7,'$2y$10$Ivc/AONJoV28EYYvogfrzeCg1kOnqY4vBJpsqAE.K3j/jUc9DV5Iy',1,'2026-05-10 19:35:55',0,NULL,'2026-05-08 22:52:18','2026-05-10 19:36:19'),(8,8,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(9,9,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(10,10,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(11,11,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(12,12,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(13,13,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(14,14,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(15,15,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(16,16,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(17,17,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(18,18,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(19,19,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(20,20,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(21,21,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(22,22,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(23,23,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(24,24,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(25,25,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(26,26,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(27,27,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(28,28,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(29,29,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(30,30,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(31,31,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(32,32,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(33,33,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(34,34,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(35,35,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(36,36,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(37,37,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(38,38,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(39,39,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(40,40,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(41,41,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(42,42,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(43,43,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(44,44,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(45,45,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(46,46,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(47,47,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(48,48,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(49,49,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(50,50,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(51,51,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(52,52,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(53,53,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(54,54,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(55,55,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(56,56,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(57,57,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(58,58,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(59,59,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(60,60,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(61,61,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(62,62,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(63,63,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(64,64,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(65,65,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(66,66,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(67,67,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(68,68,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(69,69,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(70,70,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(71,71,'$2y$10$w886JUzGn28lT/gfxHxSueuwuWD0i7ecYBEXMmw8kCh2lqWRutegq',1,'2026-05-08 23:14:12',0,NULL,'2026-05-08 22:52:18','2026-05-08 23:15:19'),(72,72,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(73,73,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(74,74,'$2y$10$ycD8I2flkQszj1N/PepmYu/gln5ARJTIbB/vKarMloGtBBox7Gue.',1,'2026-05-08 22:52:38',2,NULL,'2026-05-08 22:52:18','2026-05-10 19:21:27'),(75,75,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(76,76,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(77,77,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(78,78,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(79,79,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(80,80,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(81,81,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18'),(82,82,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 23:09:45',0,NULL,'2026-05-08 22:52:18','2026-05-08 23:09:45'),(83,83,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,1,NULL,'2026-05-08 22:52:18','2026-05-08 23:13:46'),(84,84,'$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,NULL,0,NULL,'2026-05-08 22:52:18','2026-05-08 22:52:18');
/*!40000 ALTER TABLE `student_logins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `local_uuid_unique` (`local_uuid`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'ዲ/ን ናትናኤል መክብብ',1,'0920055496','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(2,'ዲ/ን በሱፈቃድ ደጀኔ',1,'0914362110','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(3,'ዲ/ን ኪዳነማርያም አስማማው',1,'0911449065','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(4,'ዲ/ን ሚኪያስ አንጋው',1,'0988240631','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(5,'ዲ/ን ዮሐንስ ወርቁ',1,'0988128696','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(6,'ዲ/ን አቤል አስረስ',1,'0922862993','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(7,'መሰረት ዲባባ',1,'0992658749','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(8,'ትንሳኤ ስንታየሁ',1,'0911647271','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(9,'አዲስ ዓለም ዘርፉ',1,'0901744754','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(10,'አርሴማ አስማማው',1,'0911449065','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(11,'ቢታንያ ታጠቅ',1,'0910031943','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(12,'አርሴማ ዮሴፍ',1,'0910677684','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(13,'ሙሴ ደረጄ',1,'0923119204','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(14,'ዮርዳኖስ በለጠ',1,'0921310326','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(15,'ሩሃማ ጌትነት',1,'0913274115','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(16,'ሩት ደጀኔ',1,'0969145236','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(17,'ሶፎንያስ ሚሊዮን',1,'0911988826','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(18,'ሚኪያስ ሚሊዮን',1,'0911988826','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(19,'ኑኃሚን ጌትነት',1,'0913274115','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(20,'አማኑኤል ዳዊት',1,'0926807402','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(21,'በጸሎት ታረቀኝ',1,'0912166711','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(22,'ክርስቲያን አበራ',1,'0911710179','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(23,'እስጢፋኖስ እግዳወርቅ',1,'0913062894','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(24,'በአብ ታመነ',1,'0911709471','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(25,'ዮናታን ኃይሉ',1,'0945249533','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(26,'ተካልኝ ታደሰ',1,'0910001869','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(27,'ሶልያና ደረጄ',1,'0936564486','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(28,'አዶናይ ፈቃዱ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(29,'ረድኤት ኪዳኔ',1,'0920498048','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(30,'መክሊት አስናቀው',1,'0913729005','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(31,'ህሊና ዳኛቸው',1,'0913713324','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(32,'ምሥጢረ ወንድምነው',1,'0913322306','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(33,'ኤደን ብርሀኔ',1,'0912494467','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(34,'ናትናኤል ወንድምሁነኝ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(35,'ዲ/ን ሚኪያስ ርስቱ',1,'0919196015','2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(36,'አዶንያስ ጥጋቡ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(37,'ኑኃሚን ሽመልስ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(38,'ፍቅር ፈቃዱ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(39,'አብርሀም ታደሰ',1,NULL,'2025-09-11','2026-05-08 22:30:45',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(40,'ማንደፍሮ ሞላ',2,'0922810409','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(41,'ፋሲካ አዋይ',2,'0970417843','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(42,'ሀና መዝገብ',2,'0973995261','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(43,'ቤተልሔም ዘርፉ',2,'0901744754','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(44,'አልአዛር መስፍን',2,'0910501967','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(45,'ህሊና ደረጄ',2,'0936564486','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(46,'መባዊት ወርቁ',2,'0931055729','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(47,'የአብጸጋ ዓብይ',2,'0936564486','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(48,'ሀብታሙ ምስጋናው',2,'0912743283','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(49,'ማርሼት ጌታዬ',2,'0988073656','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(50,'ጫላ ማሞ ከበደ',2,'0945249533','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(51,'ቢኒያም ዓለማየሁ',2,'0935192919','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(52,'ሄርሜላ ሽመልስ',2,'0919791293','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(53,'የዓለምወርቅ ዋኘው',2,'0970702814','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(54,'እዮብ ገረመው',2,'0963010376','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(55,'ጥሩዕድል ዓለማየሁ',2,'0997118626','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(56,'የሮሰን ደረሰ',2,'0941729318','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(57,'አዶኒያስ ፀጋዬ',2,'0913090290','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(58,'ሚስጥረ ሸዋይርጋ',2,'0940852286','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(59,'ቃልኪዳን ታደሰ',3,'0903255059','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(60,'ፋናዬ ማሙዬ',3,'0985130365','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(61,'ዳሰሽ አደራ',3,'0927584977','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(62,'ማራማዊት ወንደሰን',3,'0975864268','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(63,'የአብስራ ዘላለም',3,'0932152046','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(64,'ይዲዲያ ዘውዴ',3,'0961428403','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(65,'ብርሀኔ ኬኔ',3,'0937406397','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(66,'መስከረም አየለ',3,'0989083787','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(67,'ሚኪያስ ሙሉቀን',3,'0916283212','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(68,'ብሩክታዊት ሲሳይ',3,'0913586932','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(69,'ዓለምዬ ምህረት',4,'0962980443','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(70,'አማን ይሁኔ',4,'0946133236','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(71,'ሜላት ተስፋዬ',4,'0906171926','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(72,'ፀሐይ ዘነበ',4,'0942078245','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(73,'ዳግማዊት አስረስ',4,'0967942081','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(74,'ተስፋሁን ባዬ',4,'0943854325','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(75,'አቤነዘር መባ',4,'0985739965','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(76,'ደስታው ባዬ',4,'0918425987','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(77,'ደሳለኝ ጌትነት',4,'0989316448','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(78,'ሜላት አባዲ',4,'0908722397','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(79,'ቃልኪዳን ታደሰ',4,'0988458779','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(80,'አፎሚያ አስራት',4,'0955990224','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(81,'ገሊላ ፀጋዬ',4,'0984708896','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(82,'ሀ/ጊዮርጊስ ሰማኸኝ',4,'0987081669','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(83,'ብሩክ አንተነህ',4,'0988206474','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0),(84,'ኤርሚያስ አሰፋ',4,'0939655127','2025-09-11','2026-05-08 22:30:46',NULL,NULL,'new',0,1,NULL,'2026-08-26 06:52:32',0);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_class`
--

DROP TABLE IF EXISTS `teacher_class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `locked` tinyint(1) DEFAULT 0,
  `attendance_locked` tinyint(1) NOT NULL DEFAULT 0,
  `plan_locked` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `semester_id` (`semester_id`),
  KEY `teacher_class_ibfk_1` (`teacher_id`),
  CONSTRAINT `teacher_class_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_class_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_class_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_class`
--

LOCK TABLES `teacher_class` WRITE;
/*!40000 ALTER TABLE `teacher_class` DISABLE KEYS */;
INSERT INTO `teacher_class` VALUES (1,8,4,1,0,0,0,'2026-05-08 22:33:07'),(2,4,1,1,0,0,0,'2026-05-08 22:33:22'),(3,2,1,1,0,0,0,'2026-05-08 22:33:29'),(4,6,2,1,0,0,0,'2026-05-08 22:33:38'),(5,3,1,1,0,0,0,'2026-05-08 22:34:51'),(6,5,2,1,0,0,0,'2026-05-08 22:35:01'),(7,7,2,1,0,0,0,'2026-05-08 22:35:14'),(8,3,3,1,0,0,0,'2026-05-08 22:35:26'),(9,4,3,1,0,0,0,'2026-05-08 22:35:31'),(10,9,4,1,0,0,0,'2026-05-08 22:35:41');
/*!40000 ALTER TABLE `teacher_class` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_documents`
--

DROP TABLE IF EXISTS `teacher_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `teacher_documents_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_documents`
--

LOCK TABLES `teacher_documents` WRITE;
/*!40000 ALTER TABLE `teacher_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','attendance_submitter') NOT NULL DEFAULT 'teacher',
  `password` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `can_edit_marks` tinyint(1) DEFAULT 1,
  `can_edit_attendance` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ICT ','admin','0943854325',NULL,'admin','$2y$10$pMqNRg3WooVpEkzNhbIURe.k5buShXVBeQxWymynuBWhVMWOMLrGi',1,'2026-02-16 11:15:53',1,0),(2,'ዲ/ን ክብረአብ ዘላለም','kibreab','0939883508',NULL,'teacher','$2y$10$aAiwXFkmAJLoUZN/tkSiWeIdFiceZ9uTR29gXlt/OgZnvwlvfvTSC',0,'2026-05-08 22:30:45',1,0),(3,'ዲ/ን አየለ ሞገስ','ayele','0920425061',NULL,'teacher','$2y$10$B2xt9KG8FhrjQoMWDCf8W.zkJwT51EBfZkul/im1AXF1ULFONDbbm',0,'2026-05-08 22:30:45',1,0),(4,'መ/ር ዳዊት ዓብይ','dawit','0921664431',NULL,'teacher','$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 22:30:45',1,0),(5,'መ/ር ተስፋለም','tesfalem','0965223351',NULL,'teacher','$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 22:30:45',1,0),(6,'መ/ር ዘካርያስ ፈቃዱ','zekarias','0923781476',NULL,'teacher','$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 22:30:45',1,0),(7,'መ/ር ሚኪያስ','mikiyas',NULL,NULL,'teacher','$2y$10$IMGnOzGUuNbnU7tmKHc.yOynteh/PrBHEo1iYzWnlFGmV0rt0XaY2',0,'2026-05-08 22:30:45',1,0),(8,'መ/ር ብሩክ','biruk','0969063911',NULL,'teacher','$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 22:30:45',1,0),(9,'መ/ር መሳይ','mesay','',NULL,'teacher','$2y$10$llDx1j3goq7rhzLZvUNt6eKxD9c.Jt0WznZ3YQ52mrMyhTPftociG',1,'2026-05-08 22:30:45',1,0),(10,'የሮሰን ደረሰ','yerosen','0941129318',NULL,'attendance_submitter','$2y$10$M2yDl7BzTpwcGX.xOzXNluZc3MWeo1yIcCwlMmP3qOui1jyr7Tt2W',0,'2026-05-08 22:38:51',0,1),(11,'ቢኒያም ዓለማየሁ','biniyam','0935192919',NULL,'attendance_submitter','$2y$10$ru7D.VIJFaXEgQrI2tT/6.ZQ6Vxl.Ft26f.lE7d55c.S7CTYCEHXS',1,'2026-05-08 22:39:36',0,1),(12,'ተስፋሁን ባይህ','tesfa','0943854325',NULL,'attendance_submitter','$2y$10$Fiw5lAqP0s84XXEtFOlL5eywHYaELbRZMcSam8O2KhMqTcjl6g19y',1,'2026-05-08 22:41:13',0,1),(13,'ሜላት አባዲ','melat','0908722397',NULL,'attendance_submitter','$2y$10$8k0RxGW8yJ1aHt7E3ib2RePi/e8onf8nicGoOmAWqeifdx87QIEqS',1,'2026-05-08 22:41:56',0,1),(14,'ትምህርት ክፍል','ትምህርት','0939883508',NULL,'admin','$2y$10$ImQ6owhkaYB2cUKTOs9shOT3SeI60NIgsKFbLTYfM5Pkt8n8sVBwa',1,'2026-05-08 22:44:12',1,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-10  2:53:50
