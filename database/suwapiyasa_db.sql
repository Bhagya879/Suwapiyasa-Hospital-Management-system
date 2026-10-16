-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 11:04 AM
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
-- Database: `suwapiyasa_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `appointment_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `schedule_id`, `appointment_date`, `appointment_time`, `reason`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'APT000001', 1, 1, 1, '2026-10-01', '08:00:00', 'Fevver', 'Pending', 'Too', '2026-09-27 15:27:15', '2026-09-30 18:55:13'),
(3, 'APT000002', 1, 1, 1, '2026-10-05', '08:00:00', 'test', 'Pending', '', '2026-10-02 18:22:58', '2026-10-02 18:22:58');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doctor_number` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT 0,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `joining_date` date NOT NULL,
  `status` enum('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `doctor_number`, `full_name`, `registration_number`, `specialization`, `qualification`, `gender`, `date_of_birth`, `phone`, `email`, `address`, `city`, `department`, `years_of_experience`, `consultation_fee`, `joining_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'DOC000001', 'Kamal', 'RT23455667', 'Cardiology', 'MBBS', 'Male', '1992-02-26', '0763456654', 'test@gmail.com', '123/No', 'kandy', 'OPD', 3, 2500.00, '2026-09-26', 'Active', '2026-09-26 07:34:57', '2026-09-26 07:34:57');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `schedule_type` enum('Consultation','Clinic','Emergency','Follow-up') NOT NULL DEFAULT 'Consultation',
  `room_number` varchar(30) DEFAULT NULL,
  `max_patients` int(11) NOT NULL DEFAULT 20,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `start_time`, `end_time`, `schedule_type`, `room_number`, `max_patients`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '08:00:00', '12:00:00', 'Consultation', 'Room-101', 10, 'Active', 'Not', '2026-09-26 15:05:05', '2026-09-26 15:05:05');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedule_days`
--

CREATE TABLE `doctor_schedule_days` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `schedule_day` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedule_days`
--

INSERT INTO `doctor_schedule_days` (`id`, `schedule_id`, `schedule_day`, `created_at`) VALUES
(1, 1, 'Monday', '2026-09-26 18:03:25'),
(2, 1, 'Tuesday', '2026-09-26 18:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `medical_documents`
--

CREATE TABLE `medical_documents` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `document_type` enum('Blood Test Report','X-Ray','Scan','Lab Report','Other Medical Document') NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_documents`
--

INSERT INTO `medical_documents` (`id`, `patient_id`, `appointment_id`, `uploaded_by`, `document_name`, `file_path`, `document_type`, `uploaded_at`) VALUES
(1, 1, 1, 4, 'localhost_Projects_Suwapiyasa_Hospital_Management_System_medical_index.php_page=vital_signs.png', 'uploads/medical/1caafb82fe757150b2f9ce13bb0fc87faf6ac98e6b2761b2.png', 'Blood Test Report', '2026-10-02 13:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `symptoms` text NOT NULL,
  `diagnosis` text NOT NULL,
  `treatment` text NOT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `appointment_id`, `visit_date`, `symptoms`, `diagnosis`, `treatment`, `notes`, `follow_up_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-10-01', 'More', 'Test', 'Test', 'Test', '2026-12-02', 4, '2026-10-01 23:53:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `nurses`
--

CREATE TABLE `nurses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nurse_number` varchar(20) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT 0,
  `joining_date` date NOT NULL,
  `status` enum('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nurses`
--

INSERT INTO `nurses` (`id`, `user_id`, `nurse_number`, `registration_number`, `full_name`, `qualification`, `specialization`, `gender`, `date_of_birth`, `phone`, `email`, `address`, `city`, `department`, `years_of_experience`, `joining_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 'NUR000001', 'RT2345589', 'Hasara', 'Nursing', 'Genral nursing', 'Female', '1997-01-29', '0763456456', 'hasi@gmail.com', '123/No', 'Kurunegeala', 'OPD', 3, '2026-09-29', 'Active', '2026-09-29 16:11:12', '2026-09-29 16:11:12');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `patient_number` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `nic` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `registration_date` date NOT NULL DEFAULT curdate(),
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `patient_number`, `full_name`, `nic`, `date_of_birth`, `gender`, `blood_group`, `marital_status`, `phone`, `email`, `address`, `city`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `allergies`, `chronic_conditions`, `current_medications`, `registration_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'PAT000001', 'bagya', '20034586785', '2003-02-26', 'Female', 'A-', 'Single', '0763456654', 'bgya4@gmail.com', '123/89', 'kandy', 'Ganga', '0765434123', 'Mother', 'No', 'NO', 'No', '2026-09-26', 'Active', '2026-09-26 06:53:30', '2026-09-26 06:53:30');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `prescription_number` varchar(30) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `prescription_date` date NOT NULL,
  `instructions` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `item_instructions` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `queue_number` varchar(20) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `queue_date` date NOT NULL,
  `check_in_time` datetime NOT NULL DEFAULT current_timestamp(),
  `called_time` datetime DEFAULT NULL,
  `completed_time` datetime DEFAULT NULL,
  `status` enum('Waiting','Serving','Skipped','Completed') NOT NULL DEFAULT 'Waiting',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue`
--

INSERT INTO `queue` (`id`, `queue_number`, `appointment_id`, `patient_id`, `doctor_id`, `queue_date`, `check_in_time`, `called_time`, `completed_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'A001', 1, 1, 1, '2026-10-01', '2026-09-28 21:11:30', '2026-10-01 00:25:44', '2026-09-28 21:13:10', 'Serving', NULL, '2026-09-28 15:41:30', '2026-09-30 18:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','nurse','patient') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2a$12$PJj8y2W/mlKeyucOa6/NP.b0SOq0ApXrLGAOqsKIkleqNrCCrO81m', 'admin', '2026-09-24 18:14:30'),
(2, 'testuser', '$2y$10$g/Y14bTVbjleIBVGXSHRnuLc4mVfDoOx3RGQ32oEz.Wem0ZuQlam6', 'patient', '2026-09-26 06:53:30'),
(3, 'testdoc', '$2y$10$0uUB4o5xfyCuEOCz3PHzZuO4uXPuamcN13q.t5saEeq6w3UTp5K9u', 'doctor', '2026-09-26 07:34:57'),
(4, 'testnur', '$2y$10$eJo2dW24UKqAK0YoERoWneupJ1RZXKFnN.elzL.L2uJbeFE3V53PG', 'nurse', '2026-09-29 16:11:12');

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `temperature` decimal(4,1) NOT NULL,
  `pulse_rate` smallint(6) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `height` decimal(5,2) NOT NULL,
  `oxygen_level` decimal(5,2) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vital_signs`
--

INSERT INTO `vital_signs` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `blood_pressure`, `temperature`, `pulse_rate`, `weight`, `height`, `oxygen_level`, `recorded_by`, `recorded_at`, `updated_at`) VALUES
(1, 1, 1, 1, '120/50', 37.0, 73, 60.00, 158.00, 98.00, 4, '2026-10-01 23:25:41', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_number` (`appointment_number`),
  ADD UNIQUE KEY `unique_doctor_datetime` (`doctor_id`,`appointment_date`,`appointment_time`),
  ADD KEY `fk_appointment_patient` (`patient_id`),
  ADD KEY `fk_appointment_schedule` (`schedule_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `doctor_number` (`doctor_number`),
  ADD UNIQUE KEY `registration_number` (`registration_number`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedule_doctor` (`doctor_id`);

--
-- Indexes for table `doctor_schedule_days`
--
ALTER TABLE `doctor_schedule_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule_date` (`schedule_id`,`schedule_day`),
  ADD KEY `idx_schedule_date` (`schedule_day`);

--
-- Indexes for table `medical_documents`
--
ALTER TABLE `medical_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_patient` (`patient_id`),
  ADD KEY `idx_document_appointment` (`appointment_id`),
  ADD KEY `idx_document_uploader` (`uploaded_by`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_appointment_record` (`appointment_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_doctor` (`doctor_id`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `nurses`
--
ALTER TABLE `nurses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `nurse_number` (`nurse_number`),
  ADD UNIQUE KEY `registration_number` (`registration_number`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `patient_number` (`patient_number`),
  ADD UNIQUE KEY `nic` (`nic`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prescription_number` (`prescription_number`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_prescription_patient` (`patient_id`),
  ADD KEY `idx_prescription_doctor` (`doctor_id`),
  ADD KEY `fk_prescription_user` (`created_by`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prescription_item` (`prescription_id`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `queue_number` (`queue_number`),
  ADD UNIQUE KEY `unique_queue_appointment` (`appointment_id`),
  ADD UNIQUE KEY `unique_queue_number_date` (`queue_number`,`queue_date`),
  ADD KEY `fk_queue_patient` (`patient_id`),
  ADD KEY `fk_queue_doctor` (`doctor_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_appointment_vital` (`appointment_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_doctor` (`doctor_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctor_schedule_days`
--
ALTER TABLE `doctor_schedule_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medical_documents`
--
ALTER TABLE `medical_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `nurses`
--
ALTER TABLE `nurses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_schedule_days`
--
ALTER TABLE `doctor_schedule_days`
  ADD CONSTRAINT `fk_schedule_dates_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `doctor_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_documents`
--
ALTER TABLE `medical_documents`
  ADD CONSTRAINT `fk_document_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `fk_document_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_document_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_medical_record_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_record_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_record_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_record_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `nurses`
--
ALTER TABLE `nurses`
  ADD CONSTRAINT `fk_nurses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescription_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `fk_prescription_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_prescription_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_prescription_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_prescription_item_header` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `fk_queue_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `fk_vital_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vital_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vital_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vital_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
