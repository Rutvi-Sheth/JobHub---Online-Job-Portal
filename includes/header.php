<?php
/**
 * JobHub - Navigation Header
 * File: includes/header.php
 * Description: Responsive sticky navbar with role-aware links,
 *              user dropdown, and mobile hamburger menu.
 *
 * Usage: include at the top of every public page.
 * Requires: config/db.php to be included before this file.
 */

// Determine the path depth so CSS/JS links work from any subdirectory
$depth = substr_count(str_replace('\\','/',__DIR__), '/') 
       - substr_count(str_replace('\\','/',realpath(__DIR__.'/..')), '/');
$base = BASE_URL;

// Active link helper
function isActive(string $page): string {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current === $page) ? 'active' : '';
}

$userLoggedIn = isLoggedIn();
$userRole     = $_SESSION['user_role']  ?? '';
$userName     = $_SESSION['user_name']  ?? '';
$userInitial  = strtoupper($userName[0] ?? 'U');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($metaDesc ?? 'JobHub - Find your dream job or hire the best talent. Thousands of jobs across all industries.') ?>">
  <meta name="keywords" content="job portal, jobs, careers, hire, recruiter, candidates, JobHub">
  <meta name="robots" content="index, follow">

  <!-- Open Graph -->
  <meta property="og:title"       content="<?= e($pageTitle ?? 'JobHub') ?>">
  <meta property="og:description" content="<?= e($metaDesc ?? 'Find your dream job with JobHub') ?>">
  <meta property="og:type"        content="website">

  <title><?= e($pageTitle ?? 'JobHub – Dream. Apply. Succeed.') ?></title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Favicon -->
  <link rel="icon" type="image/jpeg" href="<?= $base ?>/assets/images/logo.jpg">
  <!-- Global CSS -->
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

  <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────── -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">

    <!-- Brand -->
    <a href="<?= $base ?>/index.php" class="navbar-brand" aria-label="JobHub Home">
      <img src="<?= $base ?>/assets/images/logo.jpg" alt="JobHub Logo" class="brand-logo-img">
      <span class="brand-text">Job<span class="brand-dot">Hub</span></span>
    </a>

    <!-- Desktop Nav Links -->
    <ul class="navbar-nav" role="list">
      <li><a href="<?= $base ?>/index.php"          class="nav-link <?= isActive('index.php') ?>">Home</a></li>
      <li><a href="<?= $base ?>/jobs/listing.php"   class="nav-link <?= isActive('listing.php') ?>">Browse Jobs</a></li>
      <li><a href="<?= $base ?>/jobs/search.php"    class="nav-link <?= isActive('search.php') ?>">Search</a></li>
      <?php if (!$userLoggedIn): ?>
        <li><a href="<?= $base ?>/register.php" class="nav-link <?= isActive('register.php') ?>">Register</a></li>
      <?php endif; ?>
    </ul>

    <!-- Desktop Action Buttons -->
    <div class="navbar-actions">
      <?php if ($userLoggedIn): ?>
        <!-- Dashboard Link -->
        <?php
          switch ($userRole) {
            case 'admin':     $dashLink = $base . '/admin/dashboard.php';     break;
            case 'recruiter': $dashLink = $base . '/recruiter/dashboard.php'; break;
            default:          $dashLink = $base . '/candidate/dashboard.php'; break;
          }
        ?>
        <a href="<?= $dashLink ?>" class="btn btn-secondary btn-sm">
          <i class="fas fa-th-large"></i> Dashboard
        </a>

        <!-- User Avatar Dropdown -->
        <div class="user-menu">
          <div class="user-avatar"
               data-dropdown-toggle="userDropdown"
               aria-haspopup="true"
               aria-expanded="false"
               title="<?= e($userName) ?>">
            <?= $userInitial ?>
          </div>
          <div class="dropdown-menu" id="userDropdown" role="menu">
            <div class="dropdown-item" style="cursor:default; opacity:0.7;">
              <i class="fas fa-user-circle"></i>
              <div>
                <div style="font-weight:600; color:var(--text-primary); font-size:0.85rem;"><?= e($userName) ?></div>
                <div style="font-size:0.72rem; color:var(--text-muted); text-transform:capitalize;"><?= e($userRole) ?></div>
              </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="<?= $dashLink ?>" class="dropdown-item" role="menuitem">
              <i class="fas fa-th-large"></i> Dashboard
            </a>
            <?php if ($userRole === 'candidate'): ?>
              <a href="<?= $base ?>/candidate/profile.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-user"></i> My Profile
              </a>
              <a href="<?= $base ?>/candidate/upload_resume.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-file-upload"></i> My Resume
              </a>
              <a href="<?= $base ?>/candidate/my_applications.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-clipboard-list"></i> Applications
              </a>
            <?php elseif ($userRole === 'recruiter'): ?>
              <a href="<?= $base ?>/recruiter/company_profile.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-building"></i> Company
              </a>
              <a href="<?= $base ?>/recruiter/post_job.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-plus-circle"></i> Post Job
              </a>
            <?php elseif ($userRole === 'admin'): ?>
              <a href="<?= $base ?>/admin/manage_users.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-users-cog"></i> Manage Users
              </a>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <a href="<?= $base ?>/logout.php" class="dropdown-item" role="menuitem" style="color:var(--danger);">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a>
          </div>
        </div>

      <?php else: ?>
        <a href="<?= $base ?>/login.php"    class="btn btn-secondary btn-sm">Login</a>
        <a href="<?= $base ?>/register.php" class="btn btn-primary  btn-sm">Get Started</a>
      <?php endif; ?>

      <!-- Hamburger (mobile) -->
      <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu" role="dialog" aria-label="Mobile navigation">
  <a href="<?= $base ?>/index.php"        class="nav-link"><i class="fas fa-home"></i> Home</a>
  <a href="<?= $base ?>/jobs/listing.php" class="nav-link"><i class="fas fa-briefcase"></i> Browse Jobs</a>
  <a href="<?= $base ?>/jobs/search.php"  class="nav-link"><i class="fas fa-search"></i> Search Jobs</a>
  <?php if ($userLoggedIn): ?>
    <a href="<?= $dashLink ?>" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="<?= $base ?>/logout.php" class="nav-link" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
  <?php else: ?>
    <a href="<?= $base ?>/login.php"    class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a>
    <a href="<?= $base ?>/register.php" class="btn btn-primary btn-block mt-2"><i class="fas fa-user-plus"></i> Register</a>
  <?php endif; ?>
</div>

<!-- Flash Message -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> container mt-2" data-auto-hide="5000" role="alert">
  <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'danger' ? 'fa-times-circle' : 'fa-info-circle') ?>"></i>
  <?= e($flash['message']) ?>
</div>
<?php endif; ?>
