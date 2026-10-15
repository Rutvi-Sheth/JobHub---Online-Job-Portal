<?php
/**
 * JobHub - Job Detail Page
 * File: jobs/detail.php
 * Description: View comprehensive job information, requirements, company info,
 *              view counter update, and Apply button.
 */

require_once '../config/db.php';

$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$jobId) {
    header('Location: listing.php');
    exit;
}

$pdo = getDB();

// ── Increment view count ──
$pdo->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?")->execute([$jobId]);

// ── Fetch Job & Company Details ──
$stmt = $pdo->prepare("SELECT j.*,c.company_name,
                      c.industry AS company_industry,c.company_size,
                      c.website AS company_website, 
                      c.location AS company_location,
                      c.description AS company_description, 
                      c.logo AS company_logo,
                      u.full_name AS recruiter_name,
                      u.email AS recruiter_email
      FROM jobs j
      JOIN companies c ON j.company_id = c.id
      JOIN users u ON j.recruiter_id = u.id
      WHERE j.id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) 
{
    setFlash('danger', 'Job not found.');
    header('Location: listing.php');
    exit;
}

$pageTitle = e($job['title']) . ' at ' . e($job['company_name']) . ' – JobHub';
$metaDesc  = e(substr(strip_tags($job['description']), 0, 160));

// Check if application deadline has passed
$isExpired = (!empty($job['deadline']) && strtotime($job['deadline']) < strtotime(date('Y-m-d')));

// Check candidate application state
$alreadyApplied = false;
if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'candidate') 
{
    $candId = (int)$_SESSION['user_id'];
    $checkApp = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?");
    $checkApp->execute([$jobId, $candId]);
    $alreadyApplied = (bool)$checkApp->fetchColumn();
}

include '../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="../index.php">Home</a> <span class="sep">/</span>
      <a href="listing.php">Jobs</a> <span class="sep">/</span>
      <span><?= e($job['title']) ?></span>
    </div>

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-top:16px;">
      <div style="display:flex; align-items:center; gap:20px;">
        <div class="company-logo" style="width:72px; height:72px; font-size:2rem;">
          <?php if ($job['company_logo']): ?>
            <img src="../<?= e($job['company_logo']) ?>" alt="<?= e($job['company_name']) ?>">
          <?php else: ?>
            <i class="fas fa-building"></i>
          <?php endif; ?>
        </div>
        <div>
          <h1 class="page-title" style="margin-bottom:4px;"><?= e($job['title']) ?></h1>
          <div style="font-size:1rem; color:var(--primary-light); font-weight:600;">
            <?= e($job['company_name']) ?>
          </div>
          <div class="job-meta mt-2">
            <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> <?= e($job['location'] ?: 'Remote') ?></span>
            <span class="job-meta-item"><i class="fas fa-clock"></i> <?= e($job['job_type']) ?></span>
            <span class="job-meta-item"><i class="fas fa-eye"></i> <?= (int)$job['views'] ?> views</span>
            <span class="job-meta-item"><i class="fas fa-calendar-alt"></i> Posted <?= date('d M Y', strtotime($job['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <div>
        <?php if (!isLoggedIn()): ?>
          <a href="../login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Login to Apply</a>
        <?php elseif (($_SESSION['user_role'] ?? '') === 'candidate'): ?>
          <?php if ($alreadyApplied): ?>
            <button class="btn btn-success btn-lg" disabled><i class="fas fa-check-circle"></i> Already Applied</button>
          <?php elseif ($isExpired): ?>
            <button class="btn btn-secondary btn-lg" disabled><i class="fas fa-calendar-times"></i> Deadline Expired</button>
          <?php elseif ((int)$job['vacancies'] <= 0 || $job['status'] === 'closed'): ?>
            <button class="btn btn-secondary btn-lg" disabled><i class="fas fa-lock"></i> All Positions Filled</button>
          <?php else: ?>
            <a href="apply.php?id=<?= (int)$job['id'] ?>" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Apply Now</a>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge badge-info" style="padding:10px 16px; font-size:0.85rem;">LoggedIn as <?= ucfirst(e($_SESSION['user_role'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="container mb-4">
  <div style="display:grid; grid-template-columns: 1fr 340px; gap: 28px;" class="detail-layout">

    <!-- Left Column: Description & Requirements -->
    <main>
      <!-- Description Card -->
      <div class="card mb-3">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
          <i class="fas fa-align-left" style="color:var(--primary); margin-right:8px;"></i> Job Description
        </h2>
        <div style="color:var(--text-secondary); line-height:1.8; font-size:0.95rem; white-space: pre-line;">
          <?= e($job['description']) ?>
        </div>
      </div>

      <!-- Requirements Card -->
      <?php if ($job['requirements']): ?>
      <div class="card mb-3">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
          <i class="fas fa-tasks" style="color:var(--primary); margin-right:8px;"></i> Key Requirements
        </h2>
        <div style="color:var(--text-secondary); line-height:1.8; font-size:0.95rem; white-space: pre-line;">
          <?= e($job['requirements']) ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Required Skills Card -->
      <?php if ($job['skills']): ?>
      <div class="card mb-3">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
          <i class="fas fa-tools" style="color:var(--primary); margin-right:8px;"></i> Required Skills
        </h2>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
          <?php foreach (array_map('trim', explode(',', $job['skills'])) as $sk): ?>
            <?php if ($sk): ?>
              <span class="badge badge-primary" style="padding:6px 14px; font-size:0.85rem;"><?= e($sk) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </main>

    <!-- Right Column: Job Overview & Company Info -->
    <aside>
      <!-- Summary Card -->
      <div class="card mb-3">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
          Job Overview
        </h2>

        <div style="display:flex; flex-direction:column; gap:16px; font-size:0.9rem;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted);"><i class="fas fa-money-bill-wave" style="width:20px; color:var(--accent);"></i> Salary</span>
            <strong style="color:var(--accent);">
              <?php if ($job['salary_min'] && $job['salary_max']): ?>
                ₹<?= number_format($job['salary_min']/100000, 1) ?>L - ₹<?= number_format($job['salary_max']/100000, 1) ?>L
              <?php else: ?>
                Negotiable
              <?php endif; ?>
            </strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted);"><i class="fas fa-user-tie" style="width:20px; color:var(--primary);"></i> Experience</span>
            <strong style="color:var(--text-primary);"><?= e($job['experience'] ?: 'Not Specified') ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted);"><i class="fas fa-graduation-cap" style="width:20px; color:var(--info);"></i> Education</span>
            <strong style="color:var(--text-primary);"><?= e($job['education'] ?: 'Any Graduate') ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted);"><i class="fas fa-users" style="width:20px; color:var(--warning);"></i> Vacancies</span>
            <strong style="color:var(--text-primary);"><?= (int)$job['vacancies'] ?> Positions</strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:var(--text-muted);"><i class="fas fa-hourglass-end" style="width:20px; color:var(--danger);"></i> Deadline</span>
            <strong style="color:<?= $isExpired ? 'var(--danger)' : 'var(--text-primary)' ?>;">
              <?= $job['deadline'] ? date('d M Y', strtotime($job['deadline'])) : 'Open' ?>
              <?= $isExpired ? '<span class="badge badge-danger" style="font-size:0.7rem;padding:2px 6px;margin-left:4px;">Expired</span>' : '' ?>
            </strong>
          </div>
        </div>

        <div class="mt-4">
          <?php if (!isLoggedIn()): ?>
            <a href="../login.php" class="btn btn-primary btn-block btn-lg"><i class="fas fa-paper-plane"></i> Apply for Job</a>
          <?php elseif (($_SESSION['user_role'] ?? '') === 'candidate'): ?>
            <?php if ($alreadyApplied): ?>
              <button class="btn btn-success btn-block btn-lg" disabled><i class="fas fa-check-circle"></i> Applied</button>
            <?php elseif ($isExpired): ?>
              <button class="btn btn-secondary btn-block btn-lg" disabled><i class="fas fa-calendar-times"></i> Deadline Expired</button>
            <?php elseif ((int)$job['vacancies'] <= 0 || $job['status'] === 'closed'): ?>
              <button class="btn btn-secondary btn-block btn-lg" disabled><i class="fas fa-lock"></i> All Positions Filled</button>
            <?php else: ?>
              <a href="apply.php?id=<?= (int)$job['id'] ?>" class="btn btn-primary btn-block btn-lg"><i class="fas fa-paper-plane"></i> Apply Now</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Company Card -->
      <div class="card">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
          About the Company
        </h2>
        <div style="font-weight:700; font-size:1rem; color:var(--text-primary); margin-bottom:6px;">
          <?= e($job['company_name']) ?>
        </div>
        <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; margin-bottom:14px;">
          <?= e($job['company_description'] ?: 'No company description provided.') ?>
        </p>

        <div style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem; color:var(--text-secondary);">
          <?php if ($job['company_industry']): ?>
            <div><i class="fas fa-briefcase" style="width:18px; color:var(--primary);"></i> Industry: <?= e($job['company_industry']) ?></div>
          <?php endif; ?>
          <?php if ($job['company_location']): ?>
            <div><i class="fas fa-map-marker-alt" style="width:18px; color:var(--primary);"></i> Location: <?= e($job['company_location']) ?></div>
          <?php endif; ?>
          <?php if ($job['company_website']): ?>
            <div><i class="fas fa-globe" style="width:18px; color:var(--primary);"></i> <a href="<?= e($job['company_website']) ?>" target="_blank" style="color:var(--primary-light);"><?= e($job['company_website']) ?></a></div>
          <?php endif; ?>
        </div>
      </div>
    </aside>

  </div>
</div>

<style>
  
@media(max-width: 860px) 
{
  .detail-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
