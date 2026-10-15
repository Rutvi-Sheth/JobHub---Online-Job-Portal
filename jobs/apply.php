<?php
/**
 * JobHub - Apply for Job Page
 * File: jobs/apply.php
 * Description: Job application processing with resume selection, cover letter input,
 *              duplicate application check, CSRF protection, and server-side validation.
 */

require_once '../config/db.php';
requireRole('candidate');

$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$jobId) {
    header('Location: listing.php');
    exit;
}

$candId = (int)$_SESSION['user_id'];
$pdo    = getDB();
$errors = [];

// ── Fetch Job Information ─────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT j.*, c.company_name
    FROM jobs j
    JOIN companies c ON j.company_id = c.id
    WHERE j.id = ? AND j.status = 'active'
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    setFlash('danger', 'Job not found or is no longer active.');
    header('Location: listing.php');
    exit;
}

if ((int)$job['vacancies'] <= 0) {
    setFlash('danger', 'Sorry, all vacancies for this position have been filled.');
    header('Location: detail.php?id=' . $jobId);
    exit;
}

if (!empty($job['deadline']) && strtotime($job['deadline']) < strtotime(date('Y-m-d'))) {
    setFlash('danger', 'Sorry, the application deadline for this job has expired.');
    header('Location: detail.php?id=' . $jobId);
    exit;
}

// ── Check Duplicate Application ───────────────────────────────
$checkApp = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?");
$checkApp->execute([$jobId, $candId]);
if ($checkApp->fetchColumn()) {
    setFlash('warning', 'You have already applied for this job.');
    header('Location: detail.php?id=' . $jobId);
    exit;
}

// ── Fetch Candidate Resumes ────────────────────────────────────
$resumesStmt = $pdo->prepare("SELECT * FROM resumes WHERE candidate_id = ? ORDER BY is_primary DESC, uploaded_at DESC");
$resumesStmt->execute([$candId]);
$resumes = $resumesStmt->fetchAll();

// ── Process Job Application ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request session token. Please try again.';
    } else {
        $resumeId    = (int)($_POST['resume_id'] ?? 0);
        $coverLetter = sanitise($_POST['cover_letter'] ?? '');

        if (!$resumeId) {
            $errors[] = 'Please select a resume to attach to your application.';
        } else {
            // Verify resume ownership
            $checkRes = $pdo->prepare("SELECT id FROM resumes WHERE id = ? AND candidate_id = ?");
            $checkRes->execute([$resumeId, $candId]);
            if (!$checkRes->fetchColumn()) {
                $errors[] = 'Selected resume is invalid.';
            }
        }

        if (empty($errors)) {
            $insertApp = $pdo->prepare("
                INSERT INTO applications (job_id, candidate_id, resume_id, cover_letter, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $insertApp->execute([$jobId, $candId, $resumeId, $coverLetter]);

            setFlash('success', 'Application submitted successfully! You can track its status in your dashboard.');
            header('Location: ../candidate/my_applications.php');
            exit;
        }
    }
}

$pageTitle = 'Apply for ' . e($job['title']) . ' – JobHub';
$csrf      = generateCSRF();

include '../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="../index.php">Home</a> <span class="sep">/</span>
      <a href="listing.php">Jobs</a> <span class="sep">/</span>
      <a href="detail.php?id=<?= $jobId ?>"><?= e($job['title']) ?></a> <span class="sep">/</span>
      <span>Apply</span>
    </div>
    <h1 class="page-title">Submit Application</h1>
    <p class="page-sub">Applying for <strong><?= e($job['title']) ?></strong> at <strong><?= e($job['company_name']) ?></strong></p>
  </div>
</div>

<div class="container-sm mb-4">

  <?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
      <i class="fas fa-exclamation-circle"></i>
      <ul style="margin:0; padding-left:16px; list-style:disc;">
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <form action="apply.php?id=<?= $jobId ?>" method="POST" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- Job Summary Box -->
      <div style="background:rgba(108,99,255,0.06); border:1px solid rgba(108,99,255,0.2); border-radius:var(--radius-md); padding:16px; margin-bottom:24px;">
        <div style="font-weight:700; font-size:1.05rem; color:var(--text-primary);"><?= e($job['title']) ?></div>
        <div style="color:var(--primary-light); font-size:0.9rem; font-weight:600; margin-top:2px;"><?= e($job['company_name']) ?></div>
        <div class="job-meta mt-1">
          <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> <?= e($job['location'] ?: 'Remote') ?></span>
          <span class="job-meta-item"><i class="fas fa-clock"></i> <?= e($job['job_type']) ?></span>
        </div>
      </div>

      <!-- Select Resume -->
      <div class="form-group mb-4">
        <label class="form-label" for="resume_id">Select Resume <span class="req">*</span></label>
        <?php if ($resumes): ?>
          <select id="resume_id" name="resume_id" class="form-control" data-validate="required">
            <option value="">-- Choose Resume --</option>
            <?php foreach ($resumes as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= $r['is_primary'] ? 'selected' : '' ?>>
                <?= e($r['file_name']) ?> (Uploaded <?= date('d M Y', strtotime($r['uploaded_at'])) ?>) <?= $r['is_primary'] ? '★ Primary' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">
            Need to upload a new resume? <a href="../candidate/upload_resume.php" target="_blank" style="color:var(--primary-light);">Upload here</a> and refresh.
          </div>
          <div class="invalid-feedback"></div>
        <?php else: ?>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> You have not uploaded any resume yet.
          </div>
          <a href="../candidate/upload_resume.php" class="btn btn-secondary btn-sm"><i class="fas fa-upload"></i> Upload Resume First</a>
        <?php endif; ?>
      </div>

      <!-- Cover Letter -->
      <div class="form-group mb-4">
        <label class="form-label" for="cover_letter">Cover Letter (Optional)</label>
        <textarea id="cover_letter" name="cover_letter" class="form-control" rows="6" maxlength="2000"
                  placeholder="Explain why you are a great fit for this position..."></textarea>
        <div class="form-text">Explain your relevant experience and motivation for applying.</div>
      </div>

      <!-- Submit Button -->
      <div style="display:flex; gap:12px; justify-content:flex-end;">
        <a href="detail.php?id=<?= $jobId ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg" <?= !$resumes ? 'disabled' : '' ?>>
          <i class="fas fa-paper-plane"></i> Submit Application
        </button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
