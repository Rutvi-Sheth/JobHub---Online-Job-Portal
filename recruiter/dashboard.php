<?php
/**
 * JobHub - Recruiter Dashboard
 * File: recruiter/dashboard.php
 * Description: Shows recruiter's job stats, recent applicants,
 *              and quick actions.
 */

require_once '../config/db.php';
requireRole('recruiter');

$pageTitle = 'Recruiter Dashboard – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();

// ── Get company info ───
$company = $pdo->prepare("SELECT * FROM companies WHERE recruiter_id = ? LIMIT 1");
$company->execute([$userId]);
$company = $company->fetch();
$companyId = $company ? (int)$company['id'] : null;

// ── Stats ───
$totalJobs = 0; $activeJobs = 0; $totalApps = 0; $newApps = 0;
if ($companyId) 
{
    $totalJobs  = (int)$pdo->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=?")->execute([$userId]) ? $pdo->query("SELECT COUNT(*) FROM jobs WHERE recruiter_id=$userId")->fetchColumn() : 0;

    $sj = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=?"); $sj->execute([$userId]); $totalJobs = (int)$sj->fetchColumn();
    $sa = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=? AND status='active'"); $sa->execute([$userId]); $activeJobs = (int)$sa->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.recruiter_id=?"); $st->execute([$userId]); $totalApps = (int)$st->fetchColumn();
    $sn = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.recruiter_id=? AND a.status='pending'"); $sn->execute([$userId]); $newApps = (int)$sn->fetchColumn();
}

// ── Recent applicants ───
$recentApplicants = [];
if ($companyId) 
{
    $stmt = $pdo->prepare("SELECT a.id, a.status, a.applied_at,
                          u.full_name AS candidate_name, 
                          u.email AS candidate_email,
                          j.title AS job_title
                FROM applications a
                JOIN users u ON a.candidate_id = u.id
                JOIN jobs  j ON a.job_id = j.id
                WHERE j.recruiter_id = ?
                ORDER BY a.applied_at DESC LIMIT 6");
    $stmt->execute([$userId]);
    $recentApplicants = $stmt->fetchAll();
}

// ── Recent jobs ───
$myJobs = [];
if ($companyId) 
{
    $stmt = $pdo->prepare("SELECT j.id, j.title, j.status, j.created_at, j.deadline,
                          (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS app_count
                  FROM jobs j WHERE j.recruiter_id = ?
                  ORDER BY j.created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $myJobs = $stmt->fetchAll();
}

$user = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
$user->execute([$userId]); $user = $user->fetch();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" aria-label="Recruiter navigation">
    <div style="text-align:center;padding:16px 0 24px;">
      <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#FF6584,#6C63FF);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:white;margin:0 auto 10px;">
        <?= strtoupper($user['full_name'][0] ?? 'R') ?>
      </div>
      <div style="font-weight:600;font-size:0.9rem;"><?= e($user['full_name']) ?></div>
      <span class="badge badge-info mt-1">Recruiter</span>
    </div>

    <a href="dashboard.php"       class="sidebar-link active"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="post_job.php"        class="sidebar-link"><i class="fas fa-plus-circle"></i> Post a Job</a>
    <a href="manage_jobs.php"     class="sidebar-link"><i class="fas fa-list"></i> Manage Jobs</a>
    <a href="applicants.php"      class="sidebar-link">
      <i class="fas fa-users"></i> Applicants
      <?php if ($newApps): ?><span class="badge badge-warning"><?= $newApps ?></span><?php endif; ?>
    </a>
    <a href="company_profile.php" class="sidebar-link"><i class="fas fa-building"></i> Company Profile</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:28px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">
        Recruiter Dashboard
      </h1>
      <p style="color:var(--text-muted);">Manage your job postings and review applicants.</p>
    </div>

    <?php if (!$company): ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        Please <a href="company_profile.php" style="color:var(--primary-light);font-weight:600;">set up your Company Profile</a> before posting jobs.
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-briefcase"></i></div>
        <div class="stat-info"><div class="stat-val"><?= $totalJobs ?></div><div class="stat-lbl">Total Jobs</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><div class="stat-val"><?= $activeJobs ?></div><div class="stat-lbl">Active Jobs</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info"><div class="stat-val"><?= $totalApps ?></div><div class="stat-lbl">Total Applications</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-bell"></i></div>
        <div class="stat-info"><div class="stat-val"><?= $newApps ?></div><div class="stat-lbl">New Applications</div></div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div style="display:flex;gap:12px;margin-bottom:28px;flex-wrap:wrap;">
      <a href="post_job.php"        class="btn btn-primary"><i class="fas fa-plus"></i> Post New Job</a>
      <a href="manage_jobs.php"     class="btn btn-secondary"><i class="fas fa-list"></i> Manage Jobs</a>
      <a href="applicants.php"      class="btn btn-secondary"><i class="fas fa-users"></i> View Applicants</a>
      <a href="company_profile.php" class="btn btn-secondary"><i class="fas fa-building"></i> Company Profile</a>
    </div>

    <!-- My Recent Jobs -->
    <div class="card mb-3">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-briefcase" style="color:var(--primary);"></i> Recent Job Postings</h2>
        <a href="manage_jobs.php" class="btn btn-secondary btn-sm">View All</a>
      </div>
      <?php if ($myJobs): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Applications</th>
              <th>Status</th>
              <th>Deadline</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myJobs as $job): ?>
            <tr>
              <td><strong style="color:var(--text-primary);"><?= e($job['title']) ?></strong></td>
              <td><span class="badge badge-primary"><?= $job['app_count'] ?> applicants</span></td>
              <td>
                <span class="badge <?= $job['status'] === 'active' ? 'badge-success' : ($job['status'] === 'closed' ? 'badge-danger' : 'badge-warning') ?>">
                  <?= ucfirst(e($job['status'])) ?>
                </span>
              </td>
              <td><?= $job['deadline'] ? date('d M Y', strtotime($job['deadline'])) : '–' ?></td>
              <td>
                <a href="../jobs/detail.php?id=<?= (int)$job['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
                <a href="post_job.php?edit=<?= (int)$job['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-muted text-center" style="padding:24px;">No jobs posted yet. <a href="post_job.php">Post your first job!</a></p>
      <?php endif; ?>
    </div>

    <!-- Recent Applicants -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-users" style="color:var(--primary);"></i> Recent Applicants</h2>
        <a href="applicants.php" class="btn btn-secondary btn-sm">View All</a>
      </div>
      <?php if ($recentApplicants): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>Candidate</th><th>Job</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($recentApplicants as $app): ?>
            <tr>
              <td>
                <strong style="color:var(--text-primary);"><?= e($app['candidate_name']) ?></strong><br>
                <small><?= e($app['candidate_email']) ?></small>
              </td>
              <td><?= e($app['job_title']) ?></td>
              <td>
                <?php $sc=['pending'=>'badge-warning','reviewed'=>'badge-info','shortlisted'=>'badge-accent','rejected'=>'badge-danger','hired'=>'badge-success']; ?>
                <span class="badge <?= $sc[$app['status']] ?? 'badge-primary' ?>"><?= ucfirst(e($app['status'])) ?></span>
              </td>
              <td><?= date('d M', strtotime($app['applied_at'])) ?></td>
              <td><a href="applicants.php?app=<?= (int)$app['id'] ?>" class="btn btn-primary btn-sm">Review</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-muted text-center" style="padding:24px;">No applicants yet.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
