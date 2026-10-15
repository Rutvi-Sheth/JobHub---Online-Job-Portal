<?php
/**
 * JobHub - Candidate Profile Edit
 * File: candidate/profile.php
 * Description: Allows candidate to update their personal info,
 *              skills, education, and social links.
 *              CSRF + PDO prepared statements + server validation.
 */

require_once '../config/db.php';
requireRole('candidate');

$pageTitle = 'My Profile – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
$errors    = [];
$success   = false;

// ── Fetch current data ─────────────────────────────────────────
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

$profile = $pdo->prepare("SELECT * FROM candidate_profiles WHERE user_id = ?");
$profile->execute([$userId]);
$profile = $profile->fetch();

// ── Process POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token. Please refresh.';
    } else {
        // Sanitise
        $fullName  = sanitise($_POST['full_name']  ?? '');
        $phone     = sanitise($_POST['phone']       ?? '');
        $headline  = sanitise($_POST['headline']    ?? '');
        $about     = sanitise($_POST['about']       ?? '');
        $skills    = sanitise($_POST['skills']      ?? '');
        $education = sanitise($_POST['education']   ?? '');
        $location  = sanitise($_POST['location']    ?? '');
        $experience= sanitise($_POST['experience']  ?? '');
        $linkedin  = sanitise($_POST['linkedin']    ?? '');
        $github    = sanitise($_POST['github']      ?? '');
        $portfolio = sanitise($_POST['portfolio']   ?? '');
        $dob       = sanitise($_POST['dob']         ?? '');
        $gender    = sanitise($_POST['gender']      ?? '');

        // Validate
        if (empty($fullName))
            $errors[] = 'Full name is required.';
        elseif (!preg_match("/^[A-Za-z\s'\-]{2,100}$/", $fullName))
            $errors[] = 'Full name contains invalid characters.';

        if (!empty($phone) && !preg_match('/^\d{10}$/', $phone))
            $errors[] = 'Phone must be 10 digits.';

        if (!empty($linkedin) && !filter_var($linkedin, FILTER_VALIDATE_URL))
            $errors[] = 'Invalid LinkedIn URL.';
        if (!empty($github)   && !filter_var($github,   FILTER_VALIDATE_URL))
            $errors[] = 'Invalid GitHub URL.';
        if (!empty($portfolio) && !filter_var($portfolio, FILTER_VALIDATE_URL))
            $errors[] = 'Invalid Portfolio URL.';

        if (empty($errors)) {
            // Update users table
            $pdo->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?")
                ->execute([$fullName, $phone ?: null, $userId]);

            // Upsert candidate_profiles
            if ($profile)
            {
                $pdo->prepare("UPDATE candidate_profiles
                              SET headline=?, about=?, skills=?, education=?, location=?,
                              experience=?, linkedin=?, github=?, portfolio=?, dob=?, gender=?
                              WHERE user_id=?")->execute([$headline, $about, $skills, $education, $location,
                              $experience, $linkedin, $github, $portfolio,
                              $dob ?: null, $gender ?: null, $userId]);
            } 
            else 
            {
                $pdo->prepare("INSERT INTO candidate_profiles(user_id,headline,about,skills,education,location,experience,linkedin,github,portfolio,dob,gender)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$userId,$headline,$about,$skills,$education,$location,
                              $experience,$linkedin,$github,$portfolio,$dob ?: null,$gender ?: null]);
            }

            // Update session name
            $_SESSION['user_name'] = $fullName;
            $success = true;

            // Refresh data
            $user    = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $user->execute([$userId]); $user = $user->fetch();
            $profile = $pdo->prepare("SELECT * FROM candidate_profiles WHERE user_id=?");
            $profile->execute([$userId]); $profile = $profile->fetch();
        }
    }
}

$csrf = generateCSRF();
include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" aria-label="Candidate navigation">
    <div style="text-align:center;padding:16px 0 24px;">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:white;margin:0 auto 10px;">
        <?= strtoupper($user['full_name'][0] ?? 'U') ?>
      </div>
      <div style="font-weight:600;font-size:0.9rem;"><?= e($user['full_name']) ?></div>
      <span class="badge badge-primary mt-1">Candidate</span>
    </div>

    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="../jobs/listing.php" class="sidebar-link"><i class="fas fa-search"></i> Browse Jobs</a>
    <a href="my_applications.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Applications</a>
    <a href="profile.php"         class="sidebar-link active"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="upload_resume.php"   class="sidebar-link"><i class="fas fa-file-upload"></i> Resume</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Edit My Profile</h1>
      <p style="color:var(--text-muted);">Keep your profile up-to-date to attract the right recruiters.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-hide="4000">
        <i class="fas fa-check-circle"></i> Profile updated successfully!
      </div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin:0;padding-left:16px;list-style:disc;">
          <?php foreach ($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="profile.php" method="POST" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- Basic Information -->
      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i>Basic Information
        </h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control"
                   value="<?= e($user['full_name']) ?>" data-validate="required|name" maxlength="100">
            <div class="invalid-feedback"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="email_disp">Email</label>
            <input type="email" id="email_disp" class="form-control" value="<?= e($user['email']) ?>" disabled>
            <div class="form-text">Email cannot be changed.</div>
          </div>
          <div class="form-group">
            <label class="form-label" for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   value="<?= e($user['phone'] ?? '') ?>" data-validate="phone" maxlength="10">
            <div class="invalid-feedback"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="dob">Date of Birth</label>
            <input type="date" id="dob" name="dob" class="form-control"
                   value="<?= e($profile['dob'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="gender">Gender</label>
            <select id="gender" name="gender" class="form-control">
              <option value="">Select Gender</option>
              <?php foreach (['Male','Female','Other','Prefer not to say'] as $g): ?>
                <option value="<?= $g ?>" <?= ($profile['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="location">Location</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?= e($profile['location'] ?? '') ?>" placeholder="e.g. Mumbai, India">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="headline">Professional Headline</label>
          <input type="text" id="headline" name="headline" class="form-control"
                 value="<?= e($profile['headline'] ?? '') ?>"
                 placeholder="e.g. PHP Developer | 2+ Years Experience" maxlength="200">
        </div>
        <div class="form-group">
          <label class="form-label" for="about">About Me</label>
          <textarea id="about" name="about" class="form-control" rows="4" maxlength="1000"
                    placeholder="Tell recruiters about yourself, your goals, and what makes you unique..."><?= e($profile['about'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Professional Details -->
      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px;"></i>Professional Details
        </h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label" for="experience">Experience Level</label>
            <select id="experience" name="experience" class="form-control">
              <option value="">Select Experience</option>
              <?php foreach (['Fresher','0-1 years','1-3 years','3-5 years','5-10 years','10+ years'] as $exp): ?>
                <option value="<?= $exp ?>" <?= ($profile['experience'] ?? '') === $exp ? 'selected' : '' ?>><?= $exp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="education">Highest Education</label>
            <input type="text" id="education" name="education" class="form-control"
                   value="<?= e($profile['education'] ?? '') ?>"
                   placeholder="e.g. BCA – University of Mumbai, 2024">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="skills">Skills</label>
          <input type="text" id="skills" name="skills" class="form-control"
                 value="<?= e($profile['skills'] ?? '') ?>"
                 placeholder="PHP, MySQL, JavaScript, HTML, CSS, Laravel (comma-separated)">
          <div class="form-text">Separate skills with commas.</div>
        </div>
      </div>

      <!-- Social Links -->
      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-link" style="color:var(--primary);margin-right:8px;"></i>Social Links
        </h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label" for="linkedin"><i class="fab fa-linkedin"></i> LinkedIn Profile</label>
            <input type="url" id="linkedin" name="linkedin" class="form-control"
                   value="<?= e($profile['linkedin'] ?? '') ?>"
                   placeholder="https://linkedin.com/in/yourname" data-validate="url">
            <div class="invalid-feedback"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="github"><i class="fab fa-github"></i> GitHub Profile</label>
            <input type="url" id="github" name="github" class="form-control"
                   value="<?= e($profile['github'] ?? '') ?>"
                   placeholder="https://github.com/yourname" data-validate="url">
            <div class="invalid-feedback"></div>
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="portfolio"><i class="fas fa-globe"></i> Portfolio Website</label>
            <input type="url" id="portfolio" name="portfolio" class="form-control"
                   value="<?= e($profile['portfolio'] ?? '') ?>"
                   placeholder="https://yourportfolio.com" data-validate="url">
            <div class="invalid-feedback"></div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Profile
        </button>
      </div>
    </form>
  </main>
</div>

<style>
@media(max-width:768px)
{
  .dashboard-wrapper form [style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important;}
}
</style>

<?php include '../includes/footer.php'; ?>
