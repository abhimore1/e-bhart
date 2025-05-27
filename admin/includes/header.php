<?php
session_start(); // Start PHP session at the very top
// Adjust path to config/database.php assuming admin/includes/ is two levels down from root where config/ is.
require_once __DIR__ . '/../../config/database.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>City Depository - Admin Panel</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Google Font 'Poppins' -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Main Site CSS (adjust path from admin/includes/ to root assets/) -->
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <!-- Admin Panel Specific CSS (optional - create this file if needed) -->
    <!-- <link rel="stylesheet" href="../assets/css/admin_style.css"> -->
    <style>
        /* Specific admin panel styles can be moved to a separate admin_style.css if they grow */
        body {
            background-color: var(--theme-light-gray); /* Light grey background for admin area */
        }
        .navbar-admin { /* Custom class for admin navbar theming */
            background-color: var(--theme-primary-blue-dark, #0056b3); /* Darker blue for admin distinction */
        }
        .navbar-admin .navbar-brand,
        .navbar-admin .nav-link {
            color: #fff;
        }
        .navbar-admin .nav-link:hover,
        .navbar-admin .nav-link.active {
            color: #e9ecef; /* Lighter color for hover/active on dark background */
            font-weight: 500;
        }
        .card-header {
            font-weight: 500;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-admin"> <!-- Used custom class and removed bg-danger -->
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Admin Panel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNavbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if (isset($_SESSION['admin_id'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">Categories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subcategories.php' ? 'active' : ''; ?>" href="subcategories.php">Subcategories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_custom_fields.php' ? 'active' : ''; ?>" href="manage_custom_fields.php">Custom Fields</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_businesses.php' ? 'active' : ''; ?>" href="manage_businesses.php">Businesses</a> 
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_owners.php' ? 'active' : ''; ?> disabled" href="#">Owners</a> <!-- Placeholder -->
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['admin_email'] ?? ''); ?>)</a>
            </li>
        <?php else: ?>
             <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>" href="login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 content-wrapper">
<!-- Main content starts here -->
