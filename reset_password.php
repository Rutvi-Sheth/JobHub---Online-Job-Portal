<?php
/**
 * JobHub - Reset Password Page
 * File: reset_password.php
 * Description: Allows users to input a new password after successful OTP verification.
 *              Includes secure validations for strength and matching confirmation.
 */

require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) 
{
    redirectToDashboard();
}

$email = strtolower(sanitise($_GET['email'] ?? ''));
$token = sanitise($_GET['token'] ?? '');

// ── Security Guards & Access Control ──
// Verify match between GET parameters and active session verification parameters
if (empty($email) || empty($token) ||
    empty($_SESSION['reset_verified_email']) || $_SESSION['reset_verified_email'] !== $email ||
    empty($_SESSION['reset_verified_token']) || $_SESSION['reset_verified_token'] !== $token) 
{
    
    setFlash('danger', 'Access denied. Please complete OTP verification first.');
    header('Location: forgot_password.php');
    exit;
}

$pdo = getDB();

// Double check validity in the database to prevent manual URL manipulation
$stmt = $pdo->prepare("SELECT id, expires_at FROM password_resets
                      WHERE email = ? AND token = ? LIMIT 1");
$stmt->execute([$email, $token]);
$dbRecord = $stmt->fetch();

if (!$dbRecord) 
{
    setFlash('danger', 'Invalid reset token. Please request a new password reset.');
    header('Location: forgot_password.php');
    exit;
}

// Expiry check
$currentTime = date('Y-m-d H:i:s');
if ($currentTime > $dbRecord['expires_at']) 
{
    setFlash('danger', 'Your reset session has expired. Please request a new OTP code.');
    header('Location: forgot_password.php');
    exit;
}

$pageTitle = 'Reset Password – JobHub';
$metaDesc  = 'Enter a secure new password for your JobHub account.';

$error   = '';
$success = '';

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
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // 2. Validate Password strength
        if (empty($password)) 
        {
            $error = 'Please enter a new password.';
        }
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^])[A-Za-z\d@$!%*#?&^]{8,}$/', $password)) {
            $error = 'Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, one number, and one special character.';
        } 
        elseif ($password !== $confirm)
        {
            $error = 'Passwords do not match.';
        } 
        else 
        {
            // 3. Password reset execution
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Start Transaction to ensure consistency
            $pdo->beginTransaction();
            try 
            {
                // Update password in users table
                $updateUser = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $updateUser->execute([$hashedPassword, $email]);

                // Delete temporary reset tokens for safety
                $deleteTokens = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $deleteTokens->execute([$email]);

                $pdo->commit();

                // 4. Clean up session verification states
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_verified_email']);
                unset($_SESSION['reset_verified_token']);

                setFlash('success', 'Your password has been reset successfully. Please log in with your new password.');
                header('Location: login.php');
                exit;
            } 
            catch (Exception $e) 
            {
                $pdo->rollBack();
                $error = 'An error occurred while resetting your password. Please try again.';
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

    <h1 class="auth-title">Create New Password 🔒</h1>
    <p class="auth-sub">Please enter a strong new password for your account.</p>

    <!-- Error/Flash alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert" data-auto-hide="6000">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Reset Form -->
    <form action="reset_password.php?email=<?= urlencode($email) ?>&token=<?= urlencode($token) ?>" method="POST" id="resetForm" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- New Password -->
      <div class="form-group">
        <label class="form-label" for="password">New Password <span class="req" aria-hidden="true">*</span></label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password"
                 class="form-control"
                 placeholder="Min 8 chars, upper+lower+number+special"
                 data-validate="required|password"
                 autocomplete="new-password"
                 aria-required="true">
          <button type="button" class="toggle-pw" aria-label="Show/hide password">
            <i class="fas fa-eye" aria-hidden="true"></i>
          </button>
        </div>
        <div class="invalid-feedback" aria-live="polite"></div>

        <!-- Password strength bar -->
        <div id="strengthBar" style="height:4px;border-radius:2px;background:var(--border);margin-top:8px;overflow:hidden;">
          <div id="strengthFill" style="height:100%;width:0%;transition:width 0.3s,background 0.3s;border-radius:2px;"></div>
        </div>
        <div class="form-text" id="strengthText"></div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm Password <span class="req" aria-hidden="true">*</span></label>
        <div class="password-wrapper">
          <input type="password" id="confirm_password" name="confirm_password"
                 class="form-control"
                 placeholder="Re-enter your password"
                 data-validate="required|confirm:password"
                 autocomplete="new-password"
                 aria-required="true">
          <button type="button" class="toggle-pw" aria-label="Show/hide confirm password">
            <i class="fas fa-eye" aria-hidden="true"></i>
          </button>
        </div>
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="resetBtn">
        <i class="fas fa-key" aria-hidden="true"></i> Update Password
      </button>
    </form>

  </div>
</main>

<!-- Password strength JS -->
<script>
(function() 
{
  const pwInput  = document.getElementById('password');
  const fill     = document.getElementById('strengthFill');
  const text     = document.getElementById('strengthText');
  if (!pwInput) return;

  pwInput.addEventListener('input', () => 
  {
    const val = pwInput.value;
    let score = 0;
    if (val.length >= 8)                     score++;
    if (/[A-Z]/.test(val))                   score++;
    if (/[a-z]/.test(val))                   score++;
    if (/\d/.test(val))                      score++;
    if (/[@$!%*#?&^]/.test(val))             score++;

    const pct    = (score / 5) * 100;
    const colors = ['','#EF4444','#F59E0B','#F59E0B','#10B981','#10B981'];
    const labels = ['','Weak','Fair','Fair','Strong','Very Strong'];

    fill.style.width      = pct + '%';
    fill.style.background = colors[score] || '';
    text.textContent      = score > 0 ? 'Strength: ' + labels[score] : '';
    text.style.color      = colors[score] || '';
  });
})();
</script>

<?php include 'includes/footer.php'; ?>
