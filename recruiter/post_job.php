<?php
/**
 * JobHub - Post Job / Edit Job
 * File: recruiter/post_job.php
 * Description: Create or edit a job posting with full validation.
 *              Requires company profile to be set up first.
 */

require_once '../config/db.php';
requireRole('recruiter');

$pageTitle = 'Post a Job – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
$errors    = [];
$success   = false;

// ── Require company profile ───────────────────────────────────
$company = $pdo->prepare("SELECT * FROM companies WHERE recruiter_id=? LIMIT 1");
$company->execute([$userId]); $company = $company->fetch();
if (!$company) {
    setFlash('warning', 'Please set up your Company Profile before posting jobs.');
    header('Location: company_profile.php'); exit;
}

// ── Edit mode ─────────────────────────────────────────────────
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = false;
$job     = null;
if ($editId) {
    $job = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND recruiter_id=?");
    $job->execute([$editId, $userId]); $job = $job->fetch();
    if (!$job) { setFlash('danger','Job not found.'); header('Location: manage_jobs.php'); exit; }
    $editing = true;
    $pageTitle = 'Edit Job – JobHub';
}

// ── Process POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        // Sanitise
        $title       = sanitise($_POST['title']       ?? '');
        $category    = sanitise($_POST['category']    ?? '');
        $jobType     = sanitise($_POST['job_type']    ?? '');
        $location    = sanitise($_POST['location']    ?? '');
        $description = sanitise($_POST['description'] ?? '');
        $requirements= sanitise($_POST['requirements'] ?? '');
        $skills      = sanitise($_POST['skills']      ?? '');
        $salMin      = (float)($_POST['salary_min']   ?? 0);
        $salMax      = (float)($_POST['salary_max']   ?? 0);
        $experience  = sanitise($_POST['experience']  ?? '');
        $education   = sanitise($_POST['education']   ?? '');
        $vacancies   = max(1, (int)($_POST['vacancies'] ?? 1));
        $deadline    = sanitise($_POST['deadline']    ?? '');
        $status      = sanitise($_POST['status']      ?? 'active');
        $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

        // Validate
        if (empty($title))       $errors[] = 'Job title is required.';
        if (strlen($title) > 150) $errors[] = 'Job title too long (max 150 chars).';
        if (empty($description)) $errors[] = 'Job description is required.';
        if (empty($jobType) || !in_array($jobType, ['Full-Time','Part-Time','Contract','Internship','Remote']))
            $errors[] = 'Please select a valid job type.';
        if ($salMin < 0)  $errors[] = 'Minimum salary cannot be negative.';
        if ($salMax > 0 && $salMax < $salMin) $errors[] = 'Max salary must be greater than min salary.';
        if (!empty($deadline)) {
            $dl = strtotime($deadline);
            if (!$dl || $dl < strtotime('today')) $errors[] = 'Deadline must be today or a future date.';
        }

        if (empty($errors)) {
            $params = [
                $company['id'], $userId, $title, $description, $requirements, $skills,
                $jobType, $category, $location,
                $salMin ?: null, $salMax ?: null,
                $experience, $education, $vacancies,
                $deadline ?: null, $status, $isFeatured
            ];

            if ($editing) {
                $pdo->prepare("
                    UPDATE jobs SET
                      company_id=?, recruiter_id=?, title=?, description=?, requirements=?, skills=?,
                      job_type=?, category=?, location=?, salary_min=?, salary_max=?,
                      experience=?, education=?, vacancies=?, deadline=?, status=?, is_featured=?
                    WHERE id=? AND recruiter_id=?
                ")->execute(array_merge($params, [$editId, $userId]));
                $success = 'updated';
            } else {
                $pdo->prepare("
                    INSERT INTO jobs
                      (company_id, recruiter_id, title, description, requirements, skills,
                       job_type, category, location, salary_min, salary_max,
                       experience, education, vacancies, deadline, status, is_featured)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute($params);
                $newJobId = (int)$pdo->lastInsertId();
                setFlash('success', 'Job posted successfully!');
                header('Location: manage_jobs.php'); exit;
            }
        }
    }
}

// Prefill from job when editing
$v = $job ?? [];
$csrf = generateCSRF();
include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Recruiter navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="post_job.php"        class="sidebar-link active"><i class="fas fa-plus-circle"></i> Post a Job</a>
    <a href="manage_jobs.php"     class="sidebar-link"><i class="fas fa-list"></i> Manage Jobs</a>
    <a href="applicants.php"      class="sidebar-link"><i class="fas fa-users"></i> Applicants</a>
    <a href="company_profile.php" class="sidebar-link"><i class="fas fa-building"></i> Company Profile</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">
        <?= $editing ? 'Edit Job Posting' : 'Post a New Job' ?>
      </h1>
      <p style="color:var(--text-muted);">Fill in the details to <?= $editing ? 'update' : 'create' ?> your job listing.</p>
    </div>

    <?php if ($success === 'updated'): ?>
      <div class="alert alert-success" data-auto-hide="4000"><i class="fas fa-check-circle"></i> Job updated successfully!</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin:0;padding-left:16px;list-style:disc;">
          <?php foreach ($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="post_job.php<?= $editing ? '?edit='.$editId : '' ?>" method="POST" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- Basic Info -->
      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-briefcase" style="color:var(--primary);margin-right:8px;"></i>Job Details
        </h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="title">Job Title <span class="req">*</span></label>
            <input type="text" id="title" name="title" class="form-control"
                   value="<?= e($_POST['title'] ?? $v['title'] ?? '') ?>"
                   data-validate="required|minlen:3|maxlen:150"
                   placeholder="e.g. Senior PHP Developer" maxlength="150">
            <div class="invalid-feedback"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="job_type">Job Type <span class="req">*</span></label>
            <select id="job_type" name="job_type" class="form-control" data-validate="required">
              <option value="">Select Type</option>
              <?php foreach (['Full-Time','Part-Time','Contract','Internship','Remote'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['job_type'] ?? $v['job_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="category">Category</label>
            <select id="category" name="category" class="form-control">
              <option value="">Select Category</option>
              <?php
                $cats = ['Information Technology','Finance & Accounting','Marketing & Sales','Human Resources','Healthcare','Education','Engineering','Design & Creative','Customer Service','Legal'];
                foreach ($cats as $cat):
              ?>
                <option value="<?= $cat ?>" <?= ($_POST['category'] ?? $v['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="location">Location</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?= e($_POST['location'] ?? $v['location'] ?? '') ?>"
                   placeholder="e.g. Mumbai, India / Remote">
          </div>

          <div class="form-group">
            <label class="form-label" for="experience">Experience Required</label>
            <select id="experience" name="experience" class="form-control">
              <option value="">Any Experience</option>
              <?php foreach (['Fresher','0-1 years','1-3 years','3-5 years','5-10 years','10+ years'] as $exp): ?>
                <option value="<?= $exp ?>" <?= ($_POST['experience'] ?? $v['experience'] ?? '') === $exp ? 'selected' : '' ?>><?= $exp ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="education">Education Required</label>
            <input type="text" id="education" name="education" class="form-control"
                   value="<?= e($_POST['education'] ?? $v['education'] ?? '') ?>"
                   placeholder="e.g. B.Tech / BCA / Any Graduate">
          </div>

          <div class="form-group">
            <label class="form-label" for="salary_min">Min Salary (₹/year)</label>
            <input type="number" id="salary_min" name="salary_min" class="form-control"
                   value="<?= e($_POST['salary_min'] ?? $v['salary_min'] ?? '') ?>"
                   min="0" step="1000" placeholder="e.g. 300000">
          </div>

          <div class="form-group">
            <label class="form-label" for="salary_max">Max Salary (₹/year)</label>
            <input type="number" id="salary_max" name="salary_max" class="form-control"
                   value="<?= e($_POST['salary_max'] ?? $v['salary_max'] ?? '') ?>"
                   min="0" step="1000" placeholder="e.g. 600000">
          </div>

          <div class="form-group">
            <label class="form-label" for="vacancies">Number of Vacancies</label>
            <input type="number" id="vacancies" name="vacancies" class="form-control"
                   value="<?= e($_POST['vacancies'] ?? $v['vacancies'] ?? 1) ?>"
                   min="1" max="999">
          </div>

          <div class="form-group">
            <label class="form-label" for="deadline">Application Deadline</label>
            <input type="date" id="deadline" name="deadline" class="form-control"
                   value="<?= e($_POST['deadline'] ?? $v['deadline'] ?? '') ?>"
                   min="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="status">Job Status</label>
            <select id="status" name="status" class="form-control">
              <option value="active"   <?= ($_POST['status'] ?? $v['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= ($_POST['status'] ?? $v['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Draft)</option>
              <option value="closed"   <?= ($_POST['status'] ?? $v['status'] ?? '') === 'closed'   ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>

          <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-top:28px;">
            <input type="checkbox" id="is_featured" name="is_featured"
                   style="accent-color:var(--primary);width:18px;height:18px;"
                   <?= ($_POST['is_featured'] ?? $v['is_featured'] ?? 0) ? 'checked' : '' ?>>
            <label for="is_featured" style="font-size:0.875rem;color:var(--text-secondary);cursor:pointer;margin:0;">
              <i class="fas fa-star" style="color:var(--warning);"></i> Feature this job (appears at top)
            </label>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-align-left" style="color:var(--primary);margin-right:8px;"></i>Job Content
        </h2>
        <div class="form-group">
          <label class="form-label" for="description">Job Description <span class="req">*</span></label>
          <textarea id="description" name="description" class="form-control" rows="6"
                    data-validate="required|minlen:50" maxlength="5000"
                    placeholder="Describe the role, responsibilities, company culture, and what the candidate will be doing..."><?= e($_POST['description'] ?? $v['description'] ?? '') ?></textarea>
          <div class="invalid-feedback"></div>
        </div>
        <div class="form-group">
          <label class="form-label" for="requirements">Requirements</label>
          <textarea id="requirements" name="requirements" class="form-control" rows="4"
                    maxlength="3000"
                    placeholder="List the qualifications, skills, and experience required for this role..."><?= e($_POST['requirements'] ?? $v['requirements'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="skills">Required Skills</label>
          <input type="text" id="skills" name="skills" class="form-control"
                 value="<?= e($_POST['skills'] ?? $v['skills'] ?? '') ?>"
                 placeholder="PHP, MySQL, JavaScript, HTML, CSS (comma-separated)">
          <div class="form-text">Separate skills with commas for better searchability.</div>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="manage_jobs.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="fas fa-paper-plane"></i>
          <?= $editing ? 'Update Job' : 'Post Job' ?>
        </button>
      </div>
    </form>
  </main>
</div>

<style>
@media(max-width:768px){ form [style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important;} }
</style>
<?php include '../includes/footer.php'; ?>
