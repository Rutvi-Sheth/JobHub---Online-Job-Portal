<?php
/**
 * JobHub - Manage Jobs (Recruiter)
 * File: recruiter/manage_jobs.php
 * Description: List all jobs with status toggle and delete.
 */

require_once '../config/db.php';
requireRole('recruiter');

$pageTitle = 'Manage Jobs – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
$csrf      = generateCSRF();

// ── Handle DELETE ─────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (validateCSRF($_GET['csrf'] ?? '')) {
        $pdo->prepare("DELETE FROM jobs WHERE id=? AND recruiter_id=?")->execute([(int)$_GET['delete'], $userId]);
        setFlash('success','Job deleted.'); header('Location: manage_jobs.php'); exit;
    }
}

// ── Handle STATUS TOGGLE ──────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    if (validateCSRF($_GET['csrf'] ?? '')) {
        $job = $pdo->prepare("SELECT status FROM jobs WHERE id=? AND recruiter_id=?");
        $job->execute([(int)$_GET['toggle'], $userId]); $job = $job->fetch();
        if ($job) {
            $newStatus = $job['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE jobs SET status=? WHERE id=? AND recruiter_id=?")->execute([$newStatus, (int)$_GET['toggle'], $userId]);
        }
        setFlash('success','Job status updated.'); header('Location: manage_jobs.php'); exit;
    }
}

// ── Fetch jobs ────────────────────────────────────────────────
$filter = sanitise($_GET['status'] ?? '');
$sql    = "SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) AS app_count
           FROM jobs j WHERE j.recruiter_id=?";
$params = [$userId];
if ($filter && in_array($filter, ['active','inactive','closed'], true)) {
    $sql .= " AND j.status=?"; $params[] = $filter;
}
$sql .= " ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$jobs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Recruiter navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="post_job.php"        class="sidebar-link"><i class="fas fa-plus-circle"></i> Post a Job</a>
    <a href="manage_jobs.php"     class="sidebar-link active"><i class="fas fa-list"></i> Manage Jobs</a>
    <a href="applicants.php"      class="sidebar-link"><i class="fas fa-users"></i> Applicants</a>
    <a href="company_profile.php" class="sidebar-link"><i class="fas fa-building"></i> Company Profile</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Manage Job Postings</h1>
      <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus"></i> Post New Job</a>
    </div>

    <!-- Filter -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
      <a href="manage_jobs.php" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
      <a href="manage_jobs.php?status=active"   class="btn btn-sm <?= $filter==='active'   ? 'btn-primary' : 'btn-secondary' ?>">Active</a>
      <a href="manage_jobs.php?status=inactive" class="btn btn-sm <?= $filter==='inactive' ? 'btn-primary' : 'btn-secondary' ?>">Inactive</a>
      <a href="manage_jobs.php?status=closed"   class="btn btn-sm <?= $filter==='closed'   ? 'btn-primary' : 'btn-secondary' ?>">Closed</a>
    </div>

    <?php if ($jobs): ?>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Job Title</th>
            <th>Type</th>
            <th>Vacancies</th>
            <th>Applications</th>
            <th>Status</th>
            <th>Deadline</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $i => $job): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <strong style="color:var(--text-primary);"><?= e($job['title']) ?></strong><br>
              <small style="color:var(--text-muted);"><?= e($job['location'] ?: 'Remote') ?></small>
            </td>
            <td><span class="badge badge-primary"><?= e($job['job_type']) ?></span></td>
            <td>
              <?php if ((int)$job['vacancies'] > 0): ?>
                <span class="badge badge-accent" style="font-weight:700;"><?= (int)$job['vacancies'] ?> Left</span>
              <?php else: ?>
                <span class="badge badge-danger" style="font-weight:700;">Filled (0)</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <a href="applicants.php?job=<?= $job['id'] ?>" style="color:var(--primary-light);font-weight:700;">
                <?= (int)$job['app_count'] ?>
              </a>
            </td>
            <td>
              <span class="badge <?= $job['status']==='active' ? 'badge-success' : ($job['status']==='closed' ? 'badge-danger' : 'badge-warning') ?>">
                <?= ucfirst(e($job['status'])) ?>
              </span>
            </td>
            <td>
              <?= $job['deadline'] ? date('d M Y', strtotime($job['deadline'])) : '–' ?>
              <?php if ($job['deadline'] && strtotime($job['deadline']) < strtotime(date('Y-m-d'))): ?>
                <br><span class="badge badge-danger" style="font-size:0.7rem;padding:2px 6px;">Expired</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="../jobs/detail.php?id=<?= $job['id'] ?>" class="btn btn-secondary btn-sm" title="Preview"><i class="fas fa-eye"></i></a>
                <a href="post_job.php?edit=<?= $job['id'] ?>" class="btn btn-outline btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                <a href="manage_jobs.php?toggle=<?= $job['id'] ?>&csrf=<?= e($csrf) ?>"
                   class="btn btn-sm <?= $job['status']==='active' ? 'btn-warning' : 'btn-success' ?>" title="Toggle Status"
                   onclick="return confirm('Toggle job status?')">
                  <i class="fas <?= $job['status']==='active' ? 'fa-pause' : 'fa-play' ?>"></i>
                </a>
                <a href="manage_jobs.php?delete=<?= $job['id'] ?>&csrf=<?= e($csrf) ?>"
                   class="btn btn-danger btn-sm" title="Delete"
                   onclick="return confirm('Permanently delete this job and all its applications?')">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="card text-center" style="padding:60px 24px;">
        <i class="fas fa-briefcase" style="font-size:3rem;opacity:0.2;color:var(--primary);display:block;margin-bottom:16px;"></i>
        <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:8px;">No Jobs Found</h2>
        <p style="color:var(--text-muted);margin-bottom:24px;">Post your first job to start receiving applications.</p>
        <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus"></i> Post a Job</a>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
