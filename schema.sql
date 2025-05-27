-- Table for administrators
CREATE TABLE `admins` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'admin'
);

-- Table for business owners
CREATE TABLE `owners` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `profile_img` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for main categories
CREATE TABLE `categories` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL UNIQUE
);

-- Table for subcategories under main categories
CREATE TABLE `subcategories` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
);

-- Table for custom fields associated with subcategories
CREATE TABLE `custom_fields` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `subcategory_id` INT NOT NULL,
  `field_name` VARCHAR(255) NOT NULL,
  `field_type` VARCHAR(50) NOT NULL, -- e.g., "text", "number", "textarea", "select", "checkbox", "radio"
  FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories`(`id`) ON DELETE CASCADE
);

-- Table for businesses
CREATE TABLE `businesses` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `owner_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `subcategory_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `contact` VARCHAR(255) NULL,
  `image` VARCHAR(255) NULL,
  `location` VARCHAR(255) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending', -- e.g., 'pending', 'approved', 'rejected'
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`) REFERENCES `owners`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories`(`id`) ON DELETE RESTRICT
);

-- Table for storing data from custom forms
CREATE TABLE `custom_form_data` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `business_id` INT NOT NULL,
  `field_name` VARCHAR(255) NOT NULL, -- Stores the field name at the time of submission
  `field_value` TEXT NOT NULL,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
);
