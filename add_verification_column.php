<?php
// Include database connection
require_once 'config/db_connect.php';

// SQL to add is_verified column
$sql_queries = [
    // Add is_verified column
    "ALTER TABLE `consultant_profiles` 
    ADD COLUMN IF NOT EXISTS `is_verified` BOOLEAN DEFAULT FALSE AFTER `is_featured`",
    
    // Add verified_by and verified_at columns
    "ALTER TABLE `consultant_profiles` 
    ADD COLUMN IF NOT EXISTS `verified_by` INT NULL AFTER `is_verified`,
    ADD COLUMN IF NOT EXISTS `verified_at` DATETIME NULL AFTER `verified_by`",
    
    // Add foreign key if not exists (this is trickier since MySQL doesn't have an IF NOT EXISTS for constraints)
    "SET @fk_exists = (
        SELECT COUNT(1) FROM information_schema.table_constraints 
        WHERE constraint_name = 'verified_by_fk' 
        AND table_name = 'consultant_profiles'
    )",
    
    "SET @sql = IF(@fk_exists = 0, 
        'ALTER TABLE `consultant_profiles` ADD CONSTRAINT `verified_by_fk` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)', 
        'SELECT \"Foreign key already exists\"'
    )",
    
    "PREPARE stmt FROM @sql",
    "EXECUTE stmt",
    "DEALLOCATE PREPARE stmt",
    
    // Create verification documents table
    "CREATE TABLE IF NOT EXISTS `consultant_verifications` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `consultant_id` INT NOT NULL,
      `document_type` VARCHAR(50) NOT NULL,
      `document_path` VARCHAR(255) NOT NULL,
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `verified` BOOLEAN DEFAULT FALSE,
      `verified_by` INT NULL,
      `verified_at` DATETIME NULL,
      `notes` TEXT NULL,
      PRIMARY KEY (`id`),
      INDEX `idx_consultant_id` (`consultant_id`),
      CONSTRAINT `consultant_verifications_fk` FOREIGN KEY (`consultant_id`) REFERENCES `consultants`(`user_id`) ON DELETE CASCADE,
      CONSTRAINT `verification_verified_by_fk` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)
    )"
];

// Execute each query
$success = true;
foreach ($sql_queries as $sql) {
    if (!$conn->query($sql)) {
        echo "Error executing SQL: " . $conn->error . "<br>SQL: " . $sql . "<br><br>";
        $success = false;
    }
}

if ($success) {
    echo "Database updates completed successfully!";
} else {
    echo "Database updates completed with errors.";
}

$conn->close();
?> 