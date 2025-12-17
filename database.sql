-- Final Consolidated Schema

CREATE TABLE `ai_chat_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL COMMENT 'References consultants.user_id',
  `title` varchar(255) NOT NULL,
  `chat_type` enum('ircc','cases') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL COMMENT 'References consultants.user_id',
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `tokens` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_chat_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL COMMENT 'References consultants.user_id',
  `month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `message_count` int(11) NOT NULL DEFAULT 0,
  `token_count` int(11) NOT NULL DEFAULT 0,
  `chat_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of chats initiated this month',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applicant_consultant_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `applicant_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `relationship_type` enum('primary','secondary','referred') NOT NULL DEFAULT 'primary',
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applicants` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `passport_number` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `current_country` varchar(100) DEFAULT NULL,
  `current_visa_status` varchar(100) DEFAULT NULL,
  `visa_expiry_date` date DEFAULT NULL,
  `target_country` varchar(100) DEFAULT NULL COMMENT 'Country interested in immigrating to',
  `immigration_purpose` enum('study','work','business','family','refugee','permanent_residence') DEFAULT NULL,
  `education_level` enum('high_school','bachelors','masters','phd','other') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `english_proficiency` enum('basic','intermediate','advanced','native','none') DEFAULT NULL,
  `has_previous_refusals` tinyint(1) DEFAULT 0 COMMENT 'Previous visa/immigration refusals',
  `refusal_details` text DEFAULT NULL,
  `has_family_in_target_country` tinyint(1) DEFAULT 0,
  `family_relation_details` text DEFAULT NULL,
  `net_worth` decimal(12,2) DEFAULT NULL COMMENT 'Financial information',
  `documents_folder_url` varchar(255) DEFAULT NULL COMMENT 'Cloud folder with supporting documents',
  `application_stage` enum('inquiry','assessment','document_collection','application_submitted','processing','decision_received','post_approval') DEFAULT 'inquiry',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('created','updated','status_changed','document_added','document_updated','comment_added','assigned','completed') NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `team_member_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','reassigned') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_comments` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, only visible to team members',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `organization_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_service_links` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_service_packages` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_status_history` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#808080' COMMENT 'Hex color code for UI display',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_template_documents` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `instructions` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `visa_id` int(11) NOT NULL,
  `estimated_processing_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) NOT NULL COMMENT 'Unique application reference for tracking',
  `user_id` int(11) NOT NULL COMMENT 'Applicant',
  `visa_id` int(11) NOT NULL COMMENT 'Visa being applied for',
  `booking_id` int(11) DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `submission_status` enum('draft','reviewed','client_signoff','submitted') NOT NULL DEFAULT 'draft' COMMENT 'Tracks submission workflow stage',
  `submitted_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Internal notes for application',
  `expected_completion_date` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `created_by` int(11) NOT NULL COMMENT 'Admin who created the application',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `available_team_members_view` (
  `team_member_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `team_member_name` varchar(201) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `available_days_count` bigint(21) DEFAULT NULL,
  PRIMARY KEY (`team_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_action_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('created','updated','status_changed','assigned','cancelled','rescheduled','payment_added','refund_processed','document_added','feedback_added','completed') NOT NULL,
  `description` text NOT NULL,
  `before_state` text DEFAULT NULL COMMENT 'JSON of relevant fields before change',
  `after_state` text DEFAULT NULL COMMENT 'JSON of relevant fields after change',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_documents` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `is_private` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'If true, only admins/team members can view',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_feedback` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `admin_response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_flow_sessions` (
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `visa_service_id` int(11) DEFAULT NULL,
  `service_consultation_id` int(11) DEFAULT NULL,
  `selected_date` date DEFAULT NULL,
  `selected_time` time DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `client_notes` text DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `flow_step` varchar(50) NOT NULL DEFAULT 'select_service',
  `is_completed` tinyint(1) DEFAULT 0,
  `resulting_booking_id` int(11) DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` enum('credit_card','paypal','bank_transfer','cash','other') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','partially_refunded') NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `processed_by` int(11) NOT NULL,
  `refund_transaction_id` varchar(255) DEFAULT NULL,
  `refund_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `reminder_type` enum('email','sms','push','system') NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_status_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_name` varchar(201) DEFAULT NULL,
  `booking_datetime` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `status_description` text DEFAULT NULL,
  `status_color` varchar(7) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#808080' COMMENT 'Hex color code for UI display',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(20) NOT NULL COMMENT 'Unique booking reference for client',
  `user_id` int(11) NOT NULL COMMENT 'Client who made the booking',
  `visa_service_id` int(11) NOT NULL,
  `service_consultation_id` int(11) NOT NULL COMMENT 'Links to service and consultation mode',
  `consultant_id` int(11) NOT NULL COMMENT 'The consultant offering the service',
  `team_member_id` int(11) DEFAULT NULL COMMENT 'Assigned team member, can be NULL if not yet assigned',
  `organization_id` int(11) NOT NULL COMMENT 'Organization the booking belongs to',
  `status_id` int(11) NOT NULL,
  `booking_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `client_notes` text DEFAULT NULL COMMENT 'Notes provided by client during booking',
  `admin_notes` text DEFAULT NULL COMMENT 'Internal notes for admins/team members',
  `location` text DEFAULT NULL COMMENT 'For in-person meetings',
  `meeting_link` varchar(255) DEFAULT NULL COMMENT 'For virtual meetings',
  `time_zone` varchar(50) NOT NULL DEFAULT 'UTC',
  `language_preference` varchar(50) DEFAULT 'English',
  `reminded_at` datetime DEFAULT NULL COMMENT 'When the last reminder was sent',
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `reschedule_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times booking was rescheduled',
  `original_booking_id` int(11) DEFAULT NULL COMMENT 'If rescheduled, reference to original booking',
  `completed_by` int(11) DEFAULT NULL COMMENT 'User who marked booking as completed',
  `completion_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `business_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0 = Sunday, 1 = Monday, etc.',
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time NOT NULL DEFAULT '09:00:00',
  `close_time` time NOT NULL DEFAULT '17:00:00',
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `client_booking_history_view` (
  `booking_id` int(11) NOT NULL,
  `reference_number` varchar(20) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(201) DEFAULT NULL,
  `consultant_name` varchar(201) DEFAULT NULL,
  `visa_type` varchar(100) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `consultation_mode` varchar(100) DEFAULT NULL,
  `booking_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `status_color` varchar(7) DEFAULT NULL,
  `total_price` decimal(11,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','partially_refunded') DEFAULT NULL,
  `rating` int(4) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0 = Sunday, 1 = Monday, etc.',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_bookings` int(11) DEFAULT NULL COMMENT 'Maximum number of bookings allowed in this time slot',
  `is_available` tinyint(1) DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_availability_settings` (
  `consultant_id` int(11) NOT NULL AUTO_INCREMENT,
  `advance_booking_days` int(11) DEFAULT 90,
  `min_notice_hours` int(11) DEFAULT 24,
  `max_daily_bookings` int(11) DEFAULT 10,
  `default_appointment_duration` int(11) DEFAULT 60,
  `buffer_between_appointments` int(11) DEFAULT 15,
  `auto_confirm_bookings` tinyint(1) DEFAULT 0,
  `allow_instant_bookings` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_blocked_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `visa_service_id` int(11) DEFAULT NULL COMMENT 'NULL means blocked for all services',
  `blocked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0 COMMENT 'If true, blocks this date every year',
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_booking_stats_view` (
  `consultant_id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_name` varchar(201) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `total_bookings` bigint(21) DEFAULT NULL,
  `completed_bookings` decimal(22,0) DEFAULT NULL,
  `cancelled_bookings` decimal(22,0) DEFAULT NULL,
  `pending_bookings` decimal(22,0) DEFAULT NULL,
  `confirmed_bookings` decimal(22,0) DEFAULT NULL,
  `average_rating` decimal(5,1) DEFAULT NULL,
  `total_ratings` bigint(21) DEFAULT NULL,
  PRIMARY KEY (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_profiles` (
  `consultant_id` int(11) NOT NULL AUTO_INCREMENT,
  `bio` text DEFAULT NULL,
  `specializations` text DEFAULT NULL,
  `years_experience` int(11) DEFAULT 0,
  `education` text DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `social_linkedin` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_public_profiles_view` (
  `consultant_id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_name` varchar(201) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `specializations` text DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `services_count` bigint(21) DEFAULT NULL,
  `bookings_count` bigint(21) DEFAULT NULL,
  `average_rating` decimal(5,1) DEFAULT NULL,
  `reviews_count` bigint(21) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT NULL,
  `display_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_services` (
  `consultant_service_id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consultant_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_services_view` (
  `visa_service_id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) DEFAULT NULL,
  `consultant_name` varchar(201) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `visa_type` varchar(100) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `available_consultation_modes` bigint(21) DEFAULT NULL,
  `consultation_modes` longtext DEFAULT NULL,
  PRIMARY KEY (`visa_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_teams` (
  `consultant_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `membership_plan` varchar(50) DEFAULT NULL,
  `max_team_members` int(11) DEFAULT NULL,
  `team_members_count` int(11) DEFAULT NULL,
  `available_slots` bigint(12) DEFAULT NULL,
  PRIMARY KEY (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultant_working_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `is_working_day` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultants` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_plan_id` int(11) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `team_members_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consultation_modes` (
  `consultation_mode_id` int(11) NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_global` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`consultation_mode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin','applicant','consultant','team_member') NOT NULL,
  `is_muted` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL COMMENT 'When participant left the conversation',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_summaries` (
  `conversation_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `type` enum('direct','group','application') DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `participant_count` bigint(21) DEFAULT NULL,
  `message_count` bigint(21) DEFAULT NULL,
  `last_message` mediumtext DEFAULT NULL,
  `last_message_user_id` int(11) DEFAULT NULL,
  `last_message_user_name` varchar(201) DEFAULT NULL,
  `booking_reference` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_typing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_typing` tinyint(1) NOT NULL DEFAULT 0,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL COMMENT 'Title for group conversations, NULL for direct messages',
  `type` enum('direct','group','application') NOT NULL COMMENT 'direct: 1-on-1, group: multiple participants, application: related to application',
  `created_by` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL COMMENT 'If conversation is related to an application',
  `organization_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_message_at` datetime DEFAULT NULL COMMENT 'Timestamp of the last message',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `countries` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `country_name` varchar(100) NOT NULL,
  `country_code` char(3) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `inactive_reason` varchar(255) DEFAULT NULL,
  `inactive_since` date DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `decision_tree_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `decision_tree_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `next_question_id` int(11) DEFAULT NULL COMMENT 'Next question to show if this option is selected, NULL if endpoint',
  `is_endpoint` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, selecting this option ends the assessment',
  `endpoint_result` text DEFAULT NULL COMMENT 'Result to show if this is an endpoint',
  `endpoint_eligible` tinyint(1) DEFAULT NULL COMMENT 'Whether this endpoint indicates eligibility',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `decision_tree_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_text` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('view','download','print','share','edit') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_sharing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) NOT NULL,
  `permission` enum('view','edit','download') NOT NULL DEFAULT 'view',
  `expires_at` datetime DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_statistics_view` (
  `organization_id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_name` varchar(100) DEFAULT NULL,
  `total_documents` bigint(21) DEFAULT NULL,
  `templates_used` bigint(21) DEFAULT NULL,
  `clients_with_documents` bigint(21) DEFAULT NULL,
  `applications_with_documents` bigint(21) DEFAULT NULL,
  `bookings_with_documents` bigint(21) DEFAULT NULL,
  `documents_emailed` bigint(21) DEFAULT NULL,
  `document_views` bigint(21) DEFAULT NULL,
  `last_document_date` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_template_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `category` enum('client','consultant','application','booking','organization','system') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_global` tinyint(1) DEFAULT 0,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_automation_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) DEFAULT NULL COMMENT 'User ID if recipient exists in system',
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `scheduled_time` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `template_type` enum('general','welcome','password_reset','booking_confirmation','booking_reminder','booking_cancellation','application_status','document_request','document_approval','document_rejection','marketing','newsletter') NOT NULL DEFAULT 'general',
  `organization_id` int(11) DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `generated_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `generated_date` datetime NOT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_date` datetime DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `max_team_members` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','quarterly','annually') NOT NULL DEFAULT 'monthly',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(50) NOT NULL COMMENT 'Emoji or reaction type',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_read_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who read the message',
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Sender of the message',
  `message` text NOT NULL,
  `is_system_message` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indicates if message was generated by system',
  `parent_message_id` int(11) DEFAULT NULL COMMENT 'For message replies/threads',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_channels` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `channel_type` enum('email','sms','push','webhook') NOT NULL,
  `channel_value` varchar(255) NOT NULL COMMENT 'Email address, phone number, device token, or webhook URL',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `last_used` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_deliveries` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `status` enum('pending','sent','delivered','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `delivery_data` text DEFAULT NULL COMMENT 'JSON data from delivery provider',
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_confirmation` tinyint(1) DEFAULT 1,
  `booking_reminder` tinyint(1) DEFAULT 1,
  `reminder_hours_before` int(11) DEFAULT 24,
  `booking_cancellation` tinyint(1) DEFAULT 1,
  `booking_rescheduled` tinyint(1) DEFAULT 1,
  `payment_confirmation` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `related_booking_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_statistics_view` (
  `organization_id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_name` varchar(100) DEFAULT NULL,
  `notification_type_id` int(11) DEFAULT NULL,
  `type_name` varchar(50) DEFAULT NULL,
  `total_count` bigint(21) DEFAULT NULL,
  `read_count` decimal(22,0) DEFAULT NULL,
  `unread_count` decimal(22,0) DEFAULT NULL,
  `dismissed_count` decimal(22,0) DEFAULT NULL,
  `affected_users_count` bigint(21) DEFAULT NULL,
  `latest_notification_date` datetime DEFAULT NULL,
  PRIMARY KEY (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `email_content` text DEFAULT NULL,
  `sms_content` text DEFAULT NULL,
  `push_content` text DEFAULT NULL,
  `in_app_content` text DEFAULT NULL,
  `variables` text DEFAULT NULL COMMENT 'JSON of available variables for this template',
  `organization_id` int(11) DEFAULT NULL COMMENT 'NULL for system templates, ID for org-specific ones',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System notifications cannot be modified/deleted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User receiving the notification',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_dismissed` tinyint(1) NOT NULL DEFAULT 0,
  `dismissed_at` datetime DEFAULT NULL,
  `action_link` varchar(255) DEFAULT NULL COMMENT 'URL to redirect when clicked',
  `related_booking_id` int(11) DEFAULT NULL,
  `related_application_id` int(11) DEFAULT NULL,
  `related_conversation_id` int(11) DEFAULT NULL,
  `related_message_id` int(11) DEFAULT NULL,
  `related_document_id` int(11) DEFAULT NULL,
  `related_ai_chat_id` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'User or system that created the notification',
  `expires_at` datetime DEFAULT NULL COMMENT 'When notification should expire/auto-dismiss',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `oauth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_user_id` varchar(255) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organization_conversations` (
  `organization_id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_name` varchar(100) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` enum('direct','group','application') DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `active_participants` bigint(21) DEFAULT NULL,
  `message_count` bigint(21) DEFAULT NULL,
  `users_with_unread` bigint(21) DEFAULT NULL,
  PRIMARY KEY (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organization_tasks_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','normal','high') DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `creator_name` varchar(201) DEFAULT NULL,
  `creator_type` enum('applicant','consultant','admin','member','custom') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `method_type` enum('credit_card','paypal','bank_transfer') NOT NULL,
  `provider` varchar(50) NOT NULL,
  `account_number` varchar(255) DEFAULT NULL COMMENT 'Last 4 digits for credit cards',
  `expiry_date` varchar(10) DEFAULT NULL COMMENT 'MM/YY format for credit cards',
  `token` varchar(255) DEFAULT NULL COMMENT 'Payment provider token',
  `billing_address_line1` varchar(255) DEFAULT NULL,
  `billing_address_line2` varchar(255) DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_postal_code` varchar(20) DEFAULT NULL,
  `billing_country` varchar(2) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL COMMENT 'External payment processor transaction ID',
  `payment_date` datetime NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `promo_code_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_code_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `final_price` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `current_uses` int(11) DEFAULT 0,
  `min_plan_price` decimal(10,2) DEFAULT NULL,
  `applicable_plans` varchar(255) DEFAULT NULL COMMENT 'Comma-separated plan IDs, NULL for all plans',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `token_id` int(11) NOT NULL,
  `payload` text NOT NULL,
  `status` enum('pending','sent','delivered','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_notification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_token` varchar(255) NOT NULL,
  `device_type` enum('android','ios','web') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `received_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) DEFAULT NULL COMMENT 'User ID if sender exists in system',
  `sender_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `received_at` datetime NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_availability_exceptions` (
  `exception_id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_service_id` int(11) NOT NULL,
  `exception_date` date NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`exception_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_availability_slots` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `slot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_bookings` int(11) DEFAULT 1,
  `current_bookings` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_booking_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_service_id` int(11) NOT NULL,
  `min_notice_hours` int(11) DEFAULT 24 COMMENT 'Minimum hours of notice required for booking',
  `max_advance_days` int(11) DEFAULT 90 COMMENT 'Maximum days in advance that can be booked',
  `buffer_before_minutes` int(11) DEFAULT 0 COMMENT 'Buffer time before appointment',
  `buffer_after_minutes` int(11) DEFAULT 0 COMMENT 'Buffer time after appointment',
  `cancellation_policy` text DEFAULT NULL,
  `reschedule_policy` text DEFAULT NULL,
  `payment_required` tinyint(1) DEFAULT 0 COMMENT 'Whether payment is required at booking time',
  `deposit_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Deposit amount if required',
  `deposit_percentage` int(11) DEFAULT 0 COMMENT 'Or percentage of total price',
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_consultation_modes` (
  `service_consultation_id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_service_id` int(11) NOT NULL,
  `consultation_mode_id` int(11) NOT NULL,
  `additional_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_minutes` int(11) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_consultation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_service_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_package_items` (
  `package_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `visa_service_id` int(11) NOT NULL,
  `service_consultation_id` int(11) NOT NULL,
  `item_order` int(11) NOT NULL COMMENT 'Order of services in the package',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`package_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_packages` (
  `package_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `discount_percentage` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `consultant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_service_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Client who left the review',
  `booking_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Verified if client actually used the service',
  `is_public` tinyint(1) DEFAULT 1,
  `consultant_response` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_types` (
  `service_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_global` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `special_days` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 1,
  `alternative_open_time` time DEFAULT NULL,
  `alternative_close_time` time DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `membership_plan_id` int(11) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `status` enum('active','canceled','expired','pending') NOT NULL DEFAULT 'pending',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the action',
  `affected_user_id` int(11) DEFAULT NULL COMMENT 'The user being acted upon, if applicable',
  `activity_type` enum('created','updated','status_changed','assigned','unassigned','assignee_status_changed','commented','attachment_added') NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `assignee_id` int(11) NOT NULL COMMENT 'The user assigned to the task (can be consultant or team member)',
  `assigned_by` int(11) NOT NULL COMMENT 'The user who made the assignment',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_assignments_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `task_name` varchar(255) DEFAULT NULL,
  `assignment_status` enum('pending','in_progress','completed','cancelled') DEFAULT NULL,
  `assignee_name` varchar(201) DEFAULT NULL,
  `assignee_type` enum('applicant','consultant','admin','member','custom') DEFAULT NULL,
  `assigned_by_name` varchar(201) DEFAULT NULL,
  `assigned_by_type` enum('applicant','consultant','admin','member','custom') DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Who uploaded the attachment',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Can be consultant or team member',
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `creator_id` int(11) NOT NULL COMMENT 'The user who created the task (can be consultant or team member)',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0 = Sunday, 1 = Monday, etc.',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_minutes` int(11) NOT NULL DEFAULT 60,
  `buffer_time_minutes` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_member_time_off` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultant_id` int(11) NOT NULL,
  `member_user_id` int(11) NOT NULL,
  `member_type` varchar(50) NOT NULL COMMENT 'consultant or custom',
  `invitation_status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `invited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unread_messages_by_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) DEFAULT NULL,
  `unread_count` bigint(21) DEFAULT NULL,
  `latest_unread_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `upcoming_bookings_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) DEFAULT NULL,
  `booking_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `status_color` varchar(7) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(201) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `visa_type` varchar(100) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `consultation_mode` varchar(100) DEFAULT NULL,
  `consultant_name` varchar(201) DEFAULT NULL,
  `team_member_role` varchar(50) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `additional_fee` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(11,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','partially_refunded') DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `location` text DEFAULT NULL,
  `time_zone` varchar(50) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_assessment_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `answer_time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT 0,
  `result_eligible` tinyint(1) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_organization_conversations` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` enum('direct','group','application') DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `user_role` enum('member','admin','applicant','consultant','team_member') DEFAULT NULL,
  `unread_messages` bigint(21) DEFAULT NULL,
  `participant_count` bigint(21) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_unread_notifications_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) DEFAULT NULL,
  `type_name` varchar(50) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(201) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `action_link` varchar(255) DEFAULT NULL,
  `related_booking_id` int(11) DEFAULT NULL,
  `related_application_id` int(11) DEFAULT NULL,
  `related_conversation_id` int(11) DEFAULT NULL,
  `related_message_id` int(11) DEFAULT NULL,
  `related_document_id` int(11) DEFAULT NULL,
  `related_ai_chat_id` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `organization_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `seconds_ago` bigint(21) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Store only hashed passwords',
  `user_type` enum('applicant','consultant','admin','member','custom') NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Set to 1 after OTP verification',
  `email_verification_token` varchar(100) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `auth_provider` enum('local','google') DEFAULT 'local',
  `profile_picture` varchar(255) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visa_required_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visa_services` (
  `visa_service_id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_id` int(11) NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_bookable` tinyint(1) DEFAULT 1,
  `booking_instructions` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`visa_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visas` (
  `visa_id` int(11) NOT NULL AUTO_INCREMENT,
  `country_id` int(11) NOT NULL,
  `visa_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `validity_period` int(11) DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `inactive_reason` varchar(255) DEFAULT NULL,
  `inactive_since` date DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`visa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
-- Clean Database Schema
-- Generated by Antigravity

-- Placeholder for missing function generate_application_reference
CREATE FUNCTION generate_application_reference() RETURNS VARCHAR(20) CHARSET utf8mb4
DETERMINISTIC
BEGIN
    RETURN CONCAT('REF-', UNIX_TIMESTAMP());
END$$
DELIMITER ;
DELIMITER $$
-- Placeholder for missing procedure send_notification
CREATE PROCEDURE send_notification(
    IN p_type VARCHAR(50),
    IN p_user_id INT,
    IN p_title VARCHAR(255),
    IN p_message TEXT,
    IN p_link VARCHAR(255),
    IN p_related_id INT,
    IN p_app_id INT,
    IN p_booking_id INT,
    IN p_param1 INT,
    IN p_param2 INT,
    IN p_param3 INT,
    IN p_org_id INT,
    IN p_sender_id INT,
    OUT p_notif_id INT
)
BEGIN
    -- Dummy implementation
    SET p_notif_id = 0;
END$$
DELIMITER ;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
DELIMITER $$
CREATE TRIGGER `after_application_status_update` AFTER UPDATE ON `applications` FOR EACH ROW BEGIN
    DECLARE v_status_name VARCHAR(50);
    DECLARE v_visa_type VARCHAR(100);
    DECLARE v_applicant_name VARCHAR(255);
    DECLARE v_consultant_name VARCHAR(255);
    DECLARE v_notification_id INT;
    IF NEW.status_id != OLD.status_id THEN
        SELECT name INTO v_status_name FROM application_statuses WHERE id = NEW.status_id;
        SELECT visa_type INTO v_visa_type
        FROM visas WHERE visa_id = NEW.visa_id;
        SELECT CONCAT(first_name, ' ', last_name) INTO v_applicant_name
        FROM users WHERE id = NEW.user_id;
        SELECT CONCAT(first_name, ' ', last_name) INTO v_consultant_name
        FROM users WHERE id = NEW.consultant_id;
        CALL send_notification(
            'application_status_update',
            NEW.user_id,
            CONCAT('Application status updated to: ', v_status_name),
            CONCAT('Your ', v_visa_type, ' application (Ref: ', NEW.reference_number, ') status has been updated to ', v_status_name, '.'),
            CONCAT('/dashboard/applications/', NEW.id),
            NULL, NEW.id, NULL, NULL, NULL, NULL,
            NEW.organization_id,
            NEW.consultant_id,
            v_notification_id
        );
        CASE v_status_name
            WHEN 'submitted' THEN
                CALL send_notification(
                    'application_status_update',
                    NEW.consultant_id,
                    CONCAT('Application submitted by ', v_applicant_name),
                    CONCAT(v_applicant_name, '''s ', v_visa_type, ' application (Ref: ', NEW.reference_number, ') has been submitted.'),
                    CONCAT('/dashboard/consultant/applications/', NEW.id),
                    NULL, NEW.id, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.user_id,
                    v_notification_id
                );
            WHEN 'additional_documents_requested' THEN
                CALL send_notification(
                    'document_requested',
                    NEW.user_id,
                    'Additional documents requested',
                    CONCAT('Additional documents have been requested for your ', v_visa_type, ' application (Ref: ', NEW.reference_number, ').'),
                    CONCAT('/dashboard/applications/', NEW.id, '/documents'),
                    NULL, NEW.id, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.consultant_id,
                    v_notification_id
                );
            WHEN 'approved' THEN
                CALL send_notification(
                    'application_approved',
                    NEW.user_id,
                    'Application Approved!',
                    CONCAT('Congratulations! Your ', v_visa_type, ' application (Ref: ', NEW.reference_number, ') has been approved.'),
                    CONCAT('/dashboard/applications/', NEW.id),
                    NULL, NEW.id, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.consultant_id,
                    v_notification_id
                );
        END CASE;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_application_insert` BEFORE INSERT ON `applications` FOR EACH ROW BEGIN
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        SET NEW.reference_number = generate_application_reference();
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_document_status_update` AFTER UPDATE ON `application_documents` FOR EACH ROW BEGIN
    DECLARE v_document_name VARCHAR(100);
    DECLARE v_application_id INT;
    DECLARE v_applicant_id INT;
    DECLARE v_consultant_id INT;
    DECLARE v_notification_id INT;
    IF NEW.status != OLD.status THEN
        SELECT name INTO v_document_name
        FROM document_types WHERE id = NEW.document_type_id;
        SELECT a.id, a.user_id, a.consultant_id 
        INTO v_application_id, v_applicant_id, v_consultant_id
        FROM applications a
        WHERE a.id = NEW.application_id;
        CASE NEW.status
            WHEN 'approved' THEN
                CALL send_notification(
                    'document_approved',
                    v_applicant_id,
                    CONCAT(v_document_name, ' approved'),
                    CONCAT('Your ', v_document_name, ' has been approved.'),
                    CONCAT('/dashboard/applications/', v_application_id, '/documents'),
                    NULL, v_application_id, NULL, NULL, NEW.id, NULL,
                    NEW.organization_id,
                    NEW.reviewed_by,
                    v_notification_id
                );
            WHEN 'rejected' THEN
                CALL send_notification(
                    'document_rejected',
                    v_applicant_id,
                    CONCAT(v_document_name, ' rejected'),
                    CONCAT('Your ', v_document_name, ' has been rejected. Reason: ', IFNULL(NEW.rejection_reason, 'No reason provided')),
                    CONCAT('/dashboard/applications/', v_application_id, '/documents'),
                    NULL, v_application_id, NULL, NULL, NEW.id, NULL,
                    NEW.organization_id,
                    NEW.reviewed_by,
                    v_notification_id
                );
            WHEN 'submitted' THEN
                CALL send_notification(
                    'document_uploaded',
                    v_consultant_id,
                    CONCAT('New document submitted: ', v_document_name),
                    CONCAT('A new ', v_document_name, ' has been submitted and is waiting for review.'),
                    CONCAT('/dashboard/consultant/applications/', v_application_id, '/documents'),
                    NULL, v_application_id, NULL, NULL, NEW.id, NULL,
                    NEW.organization_id,
                    NEW.submitted_by,
                    v_notification_id
                );
        END CASE;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_confirmed` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    DECLARE v_status_name VARCHAR(50);
    SELECT name INTO v_status_name 
    FROM booking_statuses 
    WHERE id = NEW.status_id;
    IF v_status_name = 'confirmed' AND (OLD.status_id != NEW.status_id) THEN
        INSERT INTO booking_action_queue
        (booking_id, action_type, created_at)
        VALUES
        (NEW.id, 'create_conversation', NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_status_change` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    DECLARE v_status_name VARCHAR(50);
    IF NEW.status_id != OLD.status_id THEN
        SELECT name INTO v_status_name 
        FROM booking_statuses 
        WHERE id = NEW.status_id;
        INSERT INTO notification_queue
        (user_id, related_booking_id, notification_type, status_name, created_at)
        VALUES
        (NEW.user_id, NEW.id, 'booking_status_change', v_status_name, NOW());
        INSERT INTO notification_queue
        (user_id, related_booking_id, notification_type, status_name, created_at)
        VALUES
        (NEW.consultant_id, NEW.id, 'booking_status_change', v_status_name, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_booking_insert` BEFORE INSERT ON `bookings` FOR EACH ROW BEGIN
    DECLARE v_reference VARCHAR(20);
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        SET NEW.reference_number = CONCAT('BK', DATE_FORMAT(NOW(), '%y'), LPAD(FLOOR(RAND() * 100000000), 8, '0'));
    END IF;
    SET NEW.end_datetime = DATE_ADD(NEW.booking_datetime, INTERVAL NEW.duration_minutes MINUTE);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_slot_bookings_after_insert` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    UPDATE service_availability_slots
    SET current_bookings = current_bookings + 1
    WHERE consultant_id = NEW.consultant_id
    AND visa_service_id = NEW.visa_service_id
    AND slot_date = DATE(NEW.booking_datetime)
    AND start_time <= TIME(NEW.booking_datetime)
    AND end_time >= TIME(NEW.end_datetime);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_slot_bookings_after_update` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF (NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL) OR 
       (NEW.status_id != OLD.status_id AND (
           SELECT name FROM booking_statuses WHERE id = NEW.status_id
       ) IN ('cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant')) THEN
        UPDATE service_availability_slots
        SET current_bookings = GREATEST(0, current_bookings - 1)
        WHERE consultant_id = NEW.consultant_id
        AND visa_service_id = NEW.visa_service_id
        AND slot_date = DATE(NEW.booking_datetime)
        AND start_time <= TIME(NEW.booking_datetime)
        AND end_time >= TIME(NEW.end_datetime);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_message_insert` AFTER INSERT ON `messages` FOR EACH ROW BEGIN
    UPDATE conversations
    SET last_message_at = NEW.created_at
    WHERE id = NEW.conversation_id;
    INSERT INTO message_read_status (message_id, user_id, read_at)
    VALUES (NEW.id, NEW.user_id, NOW());
    UPDATE conversation_typing
    SET is_typing = 0, last_updated = NOW()
    WHERE conversation_id = NEW.conversation_id AND user_id = NEW.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_promo_usage_insert` AFTER INSERT ON `promo_code_usage` FOR EACH ROW BEGIN
    UPDATE promo_codes
    SET current_uses = current_uses + 1
    WHERE id = NEW.promo_code_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_task_status_after_assignment_update` AFTER UPDATE ON `task_assignments` FOR EACH ROW BEGIN
    DECLARE all_completed BOOLEAN;
    DECLARE task_has_assignments BOOLEAN;
    SELECT COUNT(*) > 0 INTO task_has_assignments
    FROM task_assignments
    WHERE task_id = NEW.task_id AND deleted_at IS NULL;
    SELECT COUNT(*) = 0 INTO all_completed
    FROM task_assignments
    WHERE task_id = NEW.task_id 
      AND status != 'completed' 
      AND deleted_at IS NULL;
    IF task_has_assignments AND all_completed THEN
        UPDATE tasks 
        SET status = 'completed', completed_at = NOW() 
        WHERE id = NEW.task_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `check_team_member_limit` BEFORE INSERT ON `team_members` FOR EACH ROW BEGIN
    DECLARE current_count INT;
    DECLARE max_allowed INT;
    SELECT `team_members_count` INTO current_count
    FROM `consultants`
    WHERE `user_id` = NEW.`consultant_id`;
    SELECT mp.`max_team_members` INTO max_allowed
    FROM `consultants` c
    JOIN `membership_plans` mp ON c.`membership_plan_id` = mp.`id`
    WHERE c.`user_id` = NEW.`consultant_id`;
    IF current_count >= max_allowed THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot add more team members. Membership plan limit reached.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_team_count_after_delete` AFTER DELETE ON `team_members` FOR EACH ROW BEGIN
    UPDATE `consultants`
    SET `team_members_count` = `team_members_count` - 1
    WHERE `user_id` = OLD.`consultant_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_team_count_after_insert` AFTER INSERT ON `team_members` FOR EACH ROW BEGIN
    UPDATE `consultants`
    SET `team_members_count` = `team_members_count` + 1
    WHERE `user_id` = NEW.`consultant_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO notification_settings (user_id, type_id, email_enabled, push_enabled, sms_enabled, in_app_enabled)
    SELECT NEW.id, id, 1, 1, 0, 1
    FROM notification_types;
    IF NEW.email IS NOT NULL AND NEW.email != '' THEN
        INSERT INTO notification_channels (user_id, channel_type, channel_value, is_verified, is_primary)
        VALUES (NEW.id, 'email', NEW.email, NEW.email_verified, 1);
    END IF;
    IF NEW.phone IS NOT NULL AND NEW.phone != '' THEN
        INSERT INTO notification_channels (user_id, channel_type, channel_value, is_verified, is_primary)
        VALUES (NEW.id, 'sms', NEW.phone, 0, 1);
    END IF;
    INSERT INTO notifications (
        type_id,
        user_id,
        title,
        message,
        action_link,
        organization_id
    ) VALUES (
        (SELECT id FROM notification_types WHERE type_name = 'reminder'),
        NEW.id,
        'Welcome to the platform',
        'Thank you for joining! Complete your profile to get started.',
        '/dashboard/profile',
        NEW.organization_id
    );
END
$$
DELIMITER ;
DROP TABLE IF EXISTS `application_services_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `application_services_view`  AS SELECT `a`.`id` AS `application_id`, `a`.`reference_number` AS `reference_number`, `a`.`user_id` AS `applicant_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `applicant_name`, `a`.`visa_id` AS `visa_id`, `v`.`visa_type` AS `visa_type`, `co`.`country_name` AS `country_name`, `vs`.`visa_service_id` AS `visa_service_id`, `st`.`service_name` AS `service_name`, `a`.`status_id` AS `status_id`, `aps`.`name` AS `status_name`, `a`.`booking_id` AS `booking_id`, `b`.`reference_number` AS `booking_reference`, `a`.`organization_id` AS `organization_id`, `o`.`name` AS `organization_name`, `a`.`consultant_id` AS `consultant_id`, concat(`cu`.`first_name`,' ',`cu`.`last_name`) AS `consultant_name`, `a`.`created_at` AS `created_at`, `a`.`submitted_at` AS `submitted_at` FROM ((((((((((`applications` `a` join `users` `u` on(`a`.`user_id` = `u`.`id`)) join `visas` `v` on(`a`.`visa_id` = `v`.`visa_id`)) join `countries` `co` on(`v`.`country_id` = `co`.`country_id`)) join `application_statuses` `aps` on(`a`.`status_id` = `aps`.`id`)) join `organizations` `o` on(`a`.`organization_id` = `o`.`id`)) join `users` `cu` on(`a`.`consultant_id` = `cu`.`id`)) left join `application_service_links` `asl` on(`a`.`id` = `asl`.`application_id`)) left join `visa_services` `vs` on(`asl`.`visa_service_id` = `vs`.`visa_service_id`)) left join `service_types` `st` on(`vs`.`service_type_id` = `st`.`service_type_id`)) left join `bookings` `b` on(`a`.`booking_id` = `b`.`id`)) WHERE `a`.`deleted_at` is null ORDER BY `a`.`created_at` DESC ;
DROP TABLE IF EXISTS `available_booking_slots_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `available_booking_slots_view`  AS SELECT `sas`.`slot_id` AS `slot_id`, `sas`.`consultant_id` AS `consultant_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `consultant_name`, `sas`.`visa_service_id` AS `visa_service_id`, `vs`.`base_price` AS `base_price`, `v`.`visa_type` AS `visa_type`, `st`.`service_name` AS `service_name`, `sas`.`slot_date` AS `slot_date`, `sas`.`start_time` AS `start_time`, `sas`.`end_time` AS `end_time`, `sas`.`max_bookings` AS `max_bookings`, `sas`.`current_bookings` AS `current_bookings`, `sas`.`max_bookings`- `sas`.`current_bookings` AS `available_slots`, `sas`.`organization_id` AS `organization_id`, `o`.`name` AS `organization_name` FROM (((((`service_availability_slots` `sas` join `users` `u` on(`sas`.`consultant_id` = `u`.`id`)) join `visa_services` `vs` on(`sas`.`visa_service_id` = `vs`.`visa_service_id`)) join `visas` `v` on(`vs`.`visa_id` = `v`.`visa_id`)) join `service_types` `st` on(`vs`.`service_type_id` = `st`.`service_type_id`)) join `organizations` `o` on(`sas`.`organization_id` = `o`.`id`)) WHERE `sas`.`is_available` = 1 AND `sas`.`current_bookings` < `sas`.`max_bookings` AND `sas`.`slot_date` >= curdate() AND `vs`.`is_active` = 1 AND `vs`.`is_bookable` = 1 ;
COMMIT;
