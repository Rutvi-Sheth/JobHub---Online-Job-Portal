<?php
/**
 * JobHub - Forgot Password Page
 * File: forgot_password.php
 * Description: Initiates password reset process by validating the email
 *              and generating a secure OTP and verification token.
 */

require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$pageTitle = 'Forgot Password – JobHub';
$metaDesc  = 'Reset your JobHub password securely using an OTP code sent to your email.';

$error   = '';
$success = '';

// ── Process POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
  {

    // 1. CSRF Check
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session. Please refresh and try again.';
    }
    else
      {
        $email = strtolower(sanitise($_POST['email'] ?? ''));

        // 2. Validate email field
        if (empty($email)) 
        {
            $error = 'Please enter your email address.';
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        {
            $error = 'Please enter a valid email address.';
        } else
        {
            $pdo = getDB();

            // 3. Verify user existence
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $userExists = $stmt->fetchColumn();

            if (!$userExists) 
            {
                $error = 'This email address is not registered on JobHub.';
            } 
            else 
            {
                // 4. Generate 6-digit OTP and secure token
                $otp   = (string)random_int(100000, 999999);
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 900); // 15 minutes limit

                // 5. Save to password_resets table (cleanup previous ones for this email first)
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, otp, token, expires_at)
                              VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$email, $otp, $token, $expiry]);

                // Store state in session for tracking on verification step
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp_generated'] = $otp; // Saved to display simulation banner

                setFlash('success', 'A secure 6-digit OTP has been generated for your account.');
                header('Location: verify_otp.php?email=' . urlencode($email));
                exit;
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

    <h1 class="auth-title">Reset Password 🔑</h1>
    <p class="auth-sub">Enter your email address to receive a secure OTP code to reset your password.</p>

    <!-- Error/Flash alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert" data-auto-hide="6000">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Request Form -->
    <form action="forgot_password.php" method="POST" id="forgotForm" data-validate-form novalidate>
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

      <!-- Submit -->
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="forgotBtn">
        <i class="fas fa-paper-plane" aria-hidden="true"></i> Send OTP Code
      </button>
    </form>

    <div class="auth-divider">Back to Login</div>

    <div style="text-align:center;">
      <a href="login.php" class="btn btn-secondary btn-block">
        <i class="fas fa-arrow-left"></i> Return to Login
      </a>
    </div>

  </div>
</main>

<script>
document.getElementById('forgotForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('forgotBtn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Generating OTP...';
  }
});
</script>

<?php include 'includes/footer.php'; ?>
