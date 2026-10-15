<?php
/**
 * JobHub - Verify OTP Page
 * File: verify_otp.php
 * Description: Verifies the 6-digit OTP code entered by the user.
 *              Displays a simulated email delivery notification banner
 *              containing the generated OTP for local testing.
 */

require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) 
{
    redirectToDashboard();
}

$email = strtolower(sanitise($_GET['email'] ?? ''));

// Safety check: redirect to forgot_password.php if no email parameter is provided
// or if it doesn't match the current active session reset email.
if (empty($email) || empty($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $email) 
{
    setFlash('danger', 'Access denied. Please request a password reset first.');
    header('Location: forgot_password.php');
    exit;
}

$pageTitle = 'Verify OTP – JobHub';
$metaDesc  = 'Verify the 6-digit OTP verification code sent to your email address.';

$error   = '';
$success = '';

// Retrieve simulated OTP from session to display in demo alert banner
$simulatedOtp = $_SESSION['reset_otp_generated'] ?? '';

// ── Process POST ────
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{

    // 1. CSRF Check
    if (!validateCSRF($_POST['csrf_token'] ?? '')) 
    {
        $error = 'Invalid session. Please refresh and try again.';
    } else
    {
        $otp = sanitise($_POST['otp'] ?? '');

        // 2. Validate OTP format (6 digits)
        if (empty($otp)) 
        {
            $error = 'Please enter the 6-digit OTP code.';
        }
        elseif (!preg_match('/^\d{6}$/', $otp)) 
        {
            $error = 'OTP must be exactly 6 numeric digits.';
        } 
        else
        {
            $pdo = getDB();

            // 3. Fetch reset record
            $stmt = $pdo->prepare("SELECT token, expires_at FROM password_resets
                                  WHERE email = ? AND otp = ? LIMIT 1");
            $stmt->execute([$email, $otp]);
            $resetRecord = $stmt->fetch();

            if (!$resetRecord) 
            {
                $error = 'Incorrect OTP code. Please check and try again.';
            }
            else
            {
                // 4. Expiry check
                $currentTime = date('Y-m-d H:i:s');
                if ($currentTime > $resetRecord['expires_at']) 
                {
                    $error = 'This OTP has expired (15-minute limit). Please request a new one.';
                } 
                else
                {
                    // OTP is valid! Save verification state to session
                    $_SESSION['reset_verified_email'] = $email;
                    $_SESSION['reset_verified_token'] = $resetRecord['token'];

                    // Clear the plain simulated OTP from session for safety
                    unset($_SESSION['reset_otp_generated']);

                    setFlash('success', 'OTP verified successfully. You can now reset your password.');
                    header('Location: reset_password.php?email=' . urlencode($email) . '&token=' . urlencode($resetRecord['token']));
                    exit;
                }
            }
        }
    }
}

// Generate CSRF token
$csrf = generateCSRF();
include 'includes/header.php';
?>

<!-- Simulated Email Alert Banner for local testing -->
<?php if (!empty($simulatedOtp)): ?>
  <div style="background: rgba(16, 185, 129, 0.12); border-bottom: 1.5px solid rgba(16, 185, 129, 0.3); padding: 12px 24px; color: #10B981; font-weight: 500; font-size: 0.9rem; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
    <i class="fas fa-paper-plane"></i>
    <span><strong>[DEMO TESTING MODE]:</strong> A verification email was sent. Use OTP code: <strong style="text-decoration: underline; font-size: 1.05rem; letter-spacing: 1px;"><?= e($simulatedOtp) ?></strong> to proceed.</span>
  </div>
<?php endif; ?>

<main class="auth-page" id="main-content" style="min-height: calc(100vh - 120px);">
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

    <h1 class="auth-title">Verify OTP Code 📩</h1>
    <p class="auth-sub">Please enter the 6-digit verification code sent to <strong><?= e($email) ?></strong>.</p>

    <!-- Error/Flash alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert" data-auto-hide="6000">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Verification Form -->
    <form action="verify_otp.php?email=<?= urlencode($email) ?>" method="POST" id="verifyForm" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- OTP Code -->
      <div class="form-group">
        <label class="form-label" for="otp">Enter 6-Digit OTP <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="otp" name="otp"
               class="form-control text-center"
               placeholder="123456"
               maxlength="6"
               pattern="\d{6}"
               data-validate="required"
               autocomplete="one-time-code"
               style="font-size: 1.5rem; letter-spacing: 8px; font-weight: 700;"
               aria-required="true">
        <div class="invalid-feedback" aria-live="polite"></div>
        <div class="form-text text-center">OTP is valid for 15 minutes.</div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="verifyBtn">
        <i class="fas fa-shield-alt" aria-hidden="true"></i> Verify &amp; Proceed
      </button>
    </form>

    <div class="auth-divider">Didn't receive code?</div>

    <div style="text-align:center;">
      <a href="forgot_password.php" class="btn btn-secondary btn-block">
        <i class="fas fa-redo"></i> Resend OTP Code
      </a>
    </div>

  </div>
</main>

<script>
document.getElementById('verifyForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('verifyBtn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Verifying...';
  }
});
</script>

<?php include 'includes/footer.php'; ?>
