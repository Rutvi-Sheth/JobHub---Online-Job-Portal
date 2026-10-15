<?php
/**
 * JobHub - Candidate My Applications
 * File: candidate/my_applications.php
 * Description: Lists all job applications with status badges,
 *              filtering by status, and job detail links.
 */

require_once '../config/db.php';
requireRole('candidate');

$pageTitle = 'My Applications – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();

// ── Filters ──
$filterStatus = sanitise($_GET['status'] ?? '');
$validStatuses = ['pending','reviewed','shortlisted','rejected','hired'];

$sql    = "SELECT a.id, a.status, a.cover_letter, a.applied_at,j.id 
                  AS job_id, j.title AS job_title, j.location, j.job_type, j.deadline,
                  c.company_name, c.logo AS company_logo
                  FROM applications a
                  JOIN jobs j      ON a.job_id     = j.id
                  JOIN companies c ON j.company_id = c.id
                  WHERE a.candidate_id = ?";
$params = [$userId];

if ($filterStatus && in_array($filterStatus, $validStatuses, true)) 
{
    $sql    .= " AND a.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY a.applied_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Counts per status for filter badges
$counts = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM applications WHERE candidate_id=? GROUP BY status");
$counts->execute([$userId]);
$statusCounts = [];

foreach ($counts->fetchAll() as $row) 
{
    $statusCounts[$row['status']] = (int)$row['cnt'];
}

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Candidate navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="../jobs/listing.php" class="sidebar-link"><i class="fas fa-search"></i> Browse Jobs</a>
    <a href="my_applications.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> Applications</a>
    <a href="profile.php"         class="sidebar-link"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="upload_resume.php"   class="sidebar-link"><i class="fas fa-file-upload"></i> Resume</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <div>
        <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">My Applications</h1>
        <p style="color:var(--text-muted);margin-top:4px;">Track the status of your job applications.</p>
      </div>
      <a href="../jobs/listing.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Apply for More Jobs
      </a>
    </div>

    <!-- Status Filter Pills -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;" role="group" aria-label="Filter by status">
      <a href="my_applications.php"
         class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : 'btn-secondary' ?>">
        All <span style="opacity:0.7;">(<?= array_sum($statusCounts) ?>)</span>
      </a>
      <?php
        $filterDefs = [
          'pending'     => ['label'=>'Pending',     'class'=>'badge-warning'],
          'reviewed'    => ['label'=>'Reviewed',    'class'=>'badge-info'],
          'shortlisted' => ['label'=>'Shortlisted', 'class'=>'badge-accent'],
          'rejected'    => ['label'=>'Rejected',    'class'=>'badge-danger'],
          'hired'       => ['label'=>'Hired',       'class'=>'badge-success'],
        ];
        foreach ($filterDefs as $st => $def):
          $cnt = $statusCounts[$st] ?? 0;
      ?>
      <a href="my_applications.php?status=<?= $st ?>"
         class="btn btn-sm <?= $filterStatus === $st ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $def['label'] ?> <span style="opacity:0.7;">(<?= $cnt ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Applications List -->
    <?php if ($applications): ?>
      <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($applications as $app):
          $statusClasses = [
            'pending'     => ['badge-warning', '#F59E0B'],
            'reviewed'    => ['badge-info',    '#3B82F6'],
            'shortlisted' => ['badge-accent',  '#43E97B'],
            'rejected'    => ['badge-danger',  '#EF4444'],
            'hired'       => ['badge-success', '#10B981'],];
          [$badgeCls, $color] = $statusClasses[$app['status']] ?? ['badge-primary','#6C63FF'];
        ?>
        
        <div class="card" style="border-left:3px solid <?= $color ?>;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

            <!-- Left: job info -->
            <div style="display:flex;align-items:flex-start;gap:14px;flex:1;min-width:0;">
              <div class="company-logo">
                <?php if ($app['company_logo']): ?>
                  <img src="../<?= e($app['company_logo']) ?>" alt="<?= e($app['company_name']) ?>">
                <?php else: ?>
                  <i class="fas fa-building"></i>
                <?php endif; ?>
              </div>
              <div style="min-width:0;">
                <div style="font-weight:700;font-size:1rem;color:var(--text-primary);"><?= e($app['job_title']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:3px;"><?= e($app['company_name']) ?></div>
                <div class="job-meta" style="margin-top:8px;">
                  <?php if ($app['location']): ?>
                  <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i><?= e($app['location']) ?></span>
                  <?php endif; ?>
                  <span class="job-meta-item"><i class="fas fa-briefcase"></i><?= e($app['job_type']) ?></span>
                  <span class="job-meta-item"><i class="fas fa-calendar-alt"></i>Applied <?= date('d M Y', strtotime($app['applied_at'])) ?></span>
                  <?php if ($app['deadline']): ?>
                  <span class="job-meta-item"><i class="fas fa-hourglass-half"></i>Deadline <?= date('d M Y', strtotime($app['deadline'])) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Right: status + actions -->
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0;">
              <span class="badge <?= $badgeCls ?>" style="font-size:0.8rem;padding:5px 12px;">
                <?= ucfirst(e($app['status'])) ?>
              </span>
              <a href="../jobs/detail.php?id=<?= (int)$app['job_id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-eye"></i> View Job
              </a>
            </div>
          </div>

          <?php if ($app['cover_letter']): ?>
          <details style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
            <summary style="font-size:0.8rem;color:var(--primary-light);cursor:pointer;font-weight:600;">
              <i class="fas fa-envelope-open-text"></i> View Cover Letter
            </summary>
            <div style="margin-top:10px;font-size:0.85rem;color:var(--text-secondary);line-height:1.7;background:rgba(255,255,255,0.02);border-radius:8px;padding:12px;">
              <?= nl2br(e($app['cover_letter'])) ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="card text-center" style="padding:60px 24px;">
        <i class="fas fa-clipboard-list" style="font-size:3rem;opacity:0.2;color:var(--primary);display:block;margin-bottom:16px;"></i>
        <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:8px;">No Applications Yet</h2>
        <p style="color:var(--text-muted);margin-bottom:24px;">
          <?= $filterStatus
            ? "No '" . e($filterStatus) . "' applications found."
            : "You haven't applied to any jobs yet. Start exploring!" ?>
        </p>
        <a href="../jobs/listing.php" class="btn btn-primary">
          <i class="fas fa-search"></i> Browse Jobs
        </a>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
