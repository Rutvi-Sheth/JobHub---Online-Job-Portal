<?php
/**
 * JobHub - Login Page
 * File: login.php
 * Description: Authenticates users with email/password.
 *              Uses PDO prepared statements, bcrypt verification,
 *              session management, and CSRF protection.
 */

require_once 'config/db.php';

// Already logged in? Redirect to dashboard
if (isLoggedIn()) redirectToDashboard();

$pageTitle = 'Login – JobHub';
$metaDesc  = 'Log in to your JobHub account to access jobs, applications, and your dashboard.';

$error   = '';
$success = '';

// Handle access_denied error from requireRole redirect
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') 
{
    $error = 'Access denied. You do not have permission to view that page.';
}

// ── Process POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
  {

    // 1. CSRF Check
    if (!validateCSRF($_POST['csrf_token'] ?? ''))
    {
        $error = 'Invalid session. Please refresh and try again.';
    }
     else 
    {
        $email    = strtolower(sanitise($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // 2. Basic validation
        if (empty($email) || empty($password))
        {
            $error = 'Please enter both email and password.';
        } 
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        {
            $error = 'Invalid email format.';
        } 
        else
        {
            // 3. Fetch user by email
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, is_active
                  FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 4. Verify password with bcrypt
            if (!$user || !password_verify($password, $user['password'])) 
            {
                $error = 'Incorrect email or password. Please try again.';
            } 
            elseif (!(int)$user['is_active']) 
            {
                $error = 'Your account has been deactivated. Please contact admin.';
            } 
            else 
            {
                // 5. Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // 6. Set session variables
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email']= $user['email'];

                // 7. Remember me cookie (30 days)
                if ($remember) 
                {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
                    // In production: store token hash in DB against user_id
                }

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirectToDashboard();
            }
        }
    }
}

// Generate CSRF token
$csrf = generateCSRF();
include 'includes/header.php';
?>

<main class="auth-page" id="main-content">
  <div class="auth-card animate-fade-up">

    <!-- Logo -->
    <div class="auth-logo">
      <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;">
        <img src="assets/images/logo.jpg" alt="JobHub Logo" style="height:36px; width:36px; border-radius:8px; object-fit:cover;">
        <span style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;color:var(--text-primary);">
          Job<span style="color:var(--primary);">Hub</span>
        </span>
      </a>
    </div>

    <h1 class="auth-title">Welcome Back! 👋</h1>
    <p class="auth-sub">Login to access your dashboard and continue your journey.</p>


    <!-- Error -->

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert" data-auto-hide="6000">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Login Form -->

    <form action="login.php" method="POST" id="loginForm" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- Email -->
       
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="req" aria-hidden="true">*</span></label>
        <input type="email" id="email" name="email"
               class="form-control"
               value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="you@example.com"
               data-validate="required|email"
               autocomplete="email"
               aria-required="true">
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Password -->

      <div class="form-group">
        <label class="form-label" for="password">
          Password <span class="req" aria-hidden="true">*</span>
          <a href="forgot_password.php" style="float:right; font-size:0.78rem; color:var(--primary-light); font-weight:400;">Forgot password?</a>
        </label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password"
                 class="form-control"
                 placeholder="Enter your password"
                 data-validate="required"
                 autocomplete="current-password"
                 aria-required="true">
          <button type="button" class="toggle-pw" aria-label="Show/hide password">
            <i class="fas fa-eye" aria-hidden="true"></i>
          </button>
        </div>
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Remember Me -->
      <div class="form-group" style="display:flex; align-items:center; gap:10px;">
        <input type="checkbox" id="remember" name="remember"
               style="accent-color:var(--primary); width:16px; height:16px;">
        <label for="remember" style="font-size:0.85rem; color:var(--text-muted); cursor:pointer; margin:0;">
          Keep me logged in for 30 days
        </label>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
        <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login to JobHub
      </button>
    </form>

    <div class="auth-divider">New to JobHub?</div>

    <div style="display:flex; gap:10px;">
      <a href="register.php?role=candidate" class="btn btn-outline btn-block">
        <i class="fas fa-user-graduate"></i> Join as Candidate
      </a>
      <a href="register.php?role=recruiter" class="btn btn-secondary btn-block">
        <i class="fas fa-building"></i> Post Jobs
      </a>
    </div>

  </div>
</main>

<!-- Loading state for submit button -->

<script>
document.getElementById('loginForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Logging in...';
  }
});
</script>

<?php include 'includes/footer.php'; ?>
