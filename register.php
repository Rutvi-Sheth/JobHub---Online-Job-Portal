<?php
/**
 * JobHub - User Registration
 * File: register.php
 * Description: Handles new user registration for all three roles.
 *              - Client-side validation via main.js
 *              - Server-side validation (PHP)
 *              - Duplicate email check
 *              - Password hashing (bcrypt)
 *              - CSRF protection
 *              - Prepared statements (PDO)
 */

require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) redirectToDashboard();

$pageTitle = 'Register – JobHub';
$metaDesc  = 'Create your free JobHub account and start your career journey today.';

$errors  = [];
$success = false;

// ── Process POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
  {

    // 1. CSRF Check
    if (!validateCSRF($_POST['csrf_token'] ?? ''))
    {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } 
    else 
    {
        // 2. Sanitise inputs
        $fullName  = sanitise($_POST['full_name'] ?? '');
        $email     = strtolower(sanitise($_POST['email'] ?? ''));
        $phone     = sanitise($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';            // Don't strip tags from passwords
        $confirm   = $_POST['confirm_password'] ?? '';
        $role      = sanitise($_POST['role'] ?? 'candidate');

        // 3. Server-side validation
        if (empty($fullName))
            $errors[] = 'Full name is required.';
        elseif (!preg_match("/^[A-Za-z\s'\-]{2,100}$/", $fullName))
            $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';

        if (empty($email))
            $errors[] = 'Email address is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Please enter a valid email address.';

        if (!empty($phone) && !preg_match('/^\d{10}$/', $phone))
            $errors[] = 'Phone number must be exactly 10 digits.';

        if (empty($password))
            $errors[] = 'Password is required.';
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^])[A-Za-z\d@$!%*#?&^]{8,}$/', $password))
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, a number, and a special character.';

        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';

        if (!in_array($role, ['admin', 'recruiter', 'candidate'], true))
            $role = 'candidate';

        // 4. Duplicate email check
        if (empty($errors)) 
       {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) 
            {
                $errors[] = 'This email address is already registered. Please <a href="login.php">login</a>.';
            }
        }

        // 5. Insert user
        if (empty($errors)) 
          {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt   = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role)
                      VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $email, $hashed, $phone ?: null, $role]);
            $userId = (int)$pdo->lastInsertId();

            // Create empty candidate profile row
            if ($role === 'candidate') 
            {
                $pdo->prepare("INSERT INTO candidate_profiles (user_id) VALUES (?)")->execute([$userId]);
            }

            setFlash('success', 'Account created successfully! Please login.');
            header('Location: login.php');
            exit;
        }
    }
}

// Generate CSRF token for the form
$csrf = generateCSRF();

// Pre-select role from URL param
$preRole = in_array($_GET['role'] ?? '', ['recruiter','candidate']) ? $_GET['role'] : 'candidate';

include 'includes/header.php';
?>

<main class="auth-page" id="main-content">
  <div class="auth-card animate-fade-up">

    <!-- Logo -->
    <div class="auth-logo">
      <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;">
        <img src="assets/images/logo.jpg" alt="JobHub Logo" style="height:36px; width:36px; border-radius:8px; object-fit:cover;">
        <span class="brand" style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;color:var(--text-primary);">
          Job<span class="brand-dot" style="color:var(--primary);">Hub</span>
        </span>
      </a>
    </div>

    <h1 class="auth-title">Create Your Account</h1>
    <p class="auth-sub">Join thousands of professionals and companies on JobHub.</p>

    <!-- Error Messages -->
    <?php if ($errors): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <ul style="margin:0; padding-left:16px; list-style:disc;">
          <?php foreach ($errors as $err): ?>
            <li><?= $err /* Already sanitised — some contain trusted HTML links */ ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Role Selector -->
    <div class="role-selector" role="group" aria-label="Select account type">
      <button type="button" class="role-btn <?= $preRole === 'candidate' ? 'active' : '' ?>"
              data-role="candidate" id="roleCandidate" aria-pressed="<?= $preRole === 'candidate' ? 'true' : 'false' ?>">
        <i class="fas fa-user-graduate" aria-hidden="true"></i>
        Candidate
      </button>
      <button type="button" class="role-btn <?= $preRole === 'recruiter' ? 'active' : '' ?>"
              data-role="recruiter" id="roleRecruiter" aria-pressed="<?= $preRole === 'recruiter' ? 'true' : 'false' ?>">
        <i class="fas fa-building" aria-hidden="true"></i>
        Recruiter
      </button>
    </div>

    <!-- Registration Form -->
    <form action="register.php" method="POST" id="registerForm" data-validate-form novalidate>
      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <!-- Role (set by JS) -->
      <input type="hidden" name="role" id="roleInput" value="<?= e($preRole) ?>">

      <!-- Full Name -->
      <div class="form-group">
        <label class="form-label" for="full_name">Full Name <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="full_name" name="full_name"
               class="form-control <?= in_array('Full name is required.', $errors) ? 'is-invalid' : '' ?>"
               value="<?= e($_POST['full_name'] ?? '') ?>"
               placeholder="e.g. Rahul Sharma"
               data-validate="required|name"
               maxlength="100"
               autocomplete="name"
               aria-required="true">
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="req" aria-hidden="true">*</span></label>
        <input type="email" id="email" name="email"
               class="form-control"
               value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="you@example.com"
               data-validate="required|email"
               maxlength="150"
               autocomplete="email"
               aria-required="true">
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label class="form-label" for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone"
               class="form-control"
               value="<?= e($_POST['phone'] ?? '') ?>"
               placeholder="10-digit mobile number"
               data-validate="phone"
               maxlength="10"
               autocomplete="tel"
               pattern="\d{10}">
        <div class="form-text">Optional. Enter 10-digit number without country code.</div>
        <div class="invalid-feedback" aria-live="polite"></div>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label" for="password">Password <span class="req" aria-hidden="true">*</span></label>
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

      <!-- Terms -->
      <div class="form-group" style="display:flex; align-items:flex-start; gap:10px;">
        <input type="checkbox" id="terms" name="terms" required
               style="margin-top:3px; accent-color:var(--primary); width:16px; height:16px; flex-shrink:0;">
        <label for="terms" style="font-size:0.82rem; color:var(--text-muted); cursor:pointer;">
          I agree to the <a href="#" style="color:var(--primary-light);">Terms of Service</a> and
          <a href="#" style="color:var(--primary-light);">Privacy Policy</a> of JobHub.
        </label>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
        <i class="fas fa-user-plus" aria-hidden="true"></i> Create Account
      </button>

    </form>

    <div class="auth-divider">or</div>
    <p style="text-align:center; font-size:0.875rem; color:var(--text-muted);">
      Already have an account?
      <a href="login.php" style="color:var(--primary-light); font-weight:600;">Login here</a>
    </p>
  </div>
</main>

<!-- Password strength JS -->
<script>
(function() {
  const pwInput  = document.getElementById('password');
  const fill     = document.getElementById('strengthFill');
  const text     = document.getElementById('strengthText');
  if (!pwInput) return;

  pwInput.addEventListener('input', () => {
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
