<?php
/**
 * JobHub - View Applicants (Recruiter)
 * File: recruiter/applicants.php
 * Description: Review applicants, update status, view resume.
 */

require_once '../config/db.php';
requireRole('recruiter');

$pageTitle = 'Applicants – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
// ── Handle status update ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) 
{
    if (!validateCSRF($_POST['csrf_token'] ?? '')) 
    {
        setFlash('danger', 'Session expired. Please refresh and try again.');
        header('Location: applicants.php');
        exit;
    }

    $appId     = (int)($_POST['app_id'] ?? 0);
    $newStatus = sanitise($_POST['status'] ?? '');
    $note      = sanitise($_POST['recruiter_note'] ?? '');
    $allowed   = ['pending','reviewed','shortlisted','rejected','hired'];

    if (!in_array($newStatus, $allowed, true)) 
    {
        setFlash('danger', 'Invalid status selected.');
        header('Location: applicants.php');
        exit;
    }

    // Verify this application belongs to this recruiter's job & get old status and job_id
    $verify = $pdo->prepare("SELECT a.id, a.status AS old_status, a.job_id FROM applications a JOIN jobs j ON a.job_id=j.id WHERE a.id=? AND j.recruiter_id=?");
    $verify->execute([$appId, $userId]);
    $currentApp = $verify->fetch();

    if ($currentApp) 
    {
        $oldStatus = $currentApp['old_status'];
        $jobId     = (int)$currentApp['job_id'];

        $pdo->prepare("UPDATE applications SET status=?, recruiter_note=? WHERE id=?")
            ->execute([$newStatus, $note, $appId]);

        // When candidate is newly HIRED: decrement vacancy!
        if ($newStatus === 'hired' && $oldStatus !== 'hired') 
        {
            $pdo->prepare("UPDATE jobs SET vacancies = GREATEST(0, vacancies - 1) WHERE id = ?")
                ->execute([$jobId]);

            // Check remaining vacancies
            $checkVac = $pdo->prepare("SELECT vacancies FROM jobs WHERE id = ?");
            $checkVac->execute([$jobId]);
            $remVac = (int)$checkVac->fetchColumn();

            if ($remVac === 0) 
            {
                $pdo->prepare("UPDATE jobs SET status = 'closed' WHERE id = ?")->execute([$jobId]);
                setFlash('success', 'Candidate marked as Hired! All vacancies filled — Job is now closed.');
            } else 
            {
                setFlash('success', "Candidate marked as Hired! Job vacancies updated ($remVac remaining).");
            }
        }
        // If previously 'hired' candidate is changed to another status: restore 1 vacancy
        elseif ($oldStatus === 'hired' && $newStatus !== 'hired') 
        {
            $pdo->prepare("UPDATE jobs SET vacancies = vacancies + 1, status = 'active' WHERE id = ?")
                ->execute([$jobId]);
            setFlash('success', 'Application status updated and 1 job vacancy restored.');
        } else 
        {
            setFlash('success', 'Application status updated successfully.');
        }
    } else 
    {
        setFlash('danger', 'Unauthorized: This application does not belong to your posted jobs.');
    }
    header('Location: applicants.php');
    exit;
}

// ── Filters ───
$filterJob    = isset($_GET['job'])    ? (int)$_GET['job']    : 0;
$filterStatus = isset($_GET['status']) ? sanitise($_GET['status']) : '';

$sql    = "SELECT a.id, a.status, a.cover_letter, a.applied_at, a.recruiter_note,
                  u.id AS uid, u.full_name AS candidate_name, u.email AS candidate_email, u.phone AS candidate_phone,
                  j.id AS job_id, j.title AS job_title,
                  r.file_path AS resume_path, r.file_name AS resume_name
           FROM applications a
           JOIN users     u ON a.candidate_id = u.id
           JOIN jobs      j ON a.job_id       = j.id
           LEFT JOIN resumes r ON a.resume_id = r.id
           WHERE j.recruiter_id = ?";
$params = [$userId];

if ($filterJob)    { $sql .= " AND j.id = ?";       $params[] = $filterJob; }
if ($filterStatus) { $sql .= " AND a.status = ?";   $params[] = $filterStatus; }
$sql .= " ORDER BY a.applied_at DESC";

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$applications = $stmt->fetchAll();

// ── Job list for filter ───
$myJobs = $pdo->prepare("SELECT id, title FROM jobs WHERE recruiter_id=? ORDER BY title");
$myJobs->execute([$userId]); $myJobs = $myJobs->fetchAll();

$csrf = generateCSRF();
include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Recruiter navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="post_job.php"        class="sidebar-link"><i class="fas fa-plus-circle"></i> Post a Job</a>
    <a href="manage_jobs.php"     class="sidebar-link"><i class="fas fa-list"></i> Manage Jobs</a>
    <a href="applicants.php"      class="sidebar-link active"><i class="fas fa-users"></i> Applicants</a>
    <a href="company_profile.php" class="sidebar-link"><i class="fas fa-building"></i> Company Profile</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Applicants</h1>
      <p style="color:var(--text-muted);">Review and manage candidate applications.</p>
    </div>

    <!-- Filters -->
    <div class="card mb-3" style="padding:16px 20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
          <label class="form-label" style="margin-bottom:4px;">Filter by Job</label>
          <select name="job" class="form-control">
            <option value="">All Jobs</option>
            <?php foreach ($myJobs as $j): ?>
              <option value="<?= $j['id'] ?>" <?= $filterJob === (int)$j['id'] ? 'selected' : '' ?>><?= e($j['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px;">
          <label class="form-label" style="margin-bottom:4px;">Filter by Status</label>
          <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <?php foreach (['pending','reviewed','shortlisted','rejected','hired'] as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;">Filter</button>
        <a href="applicants.php" class="btn btn-secondary" style="height:42px;">Reset</a>
      </form>
    </div>

    <?php if ($applications): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($applications as $app):
        $statusColors = ['pending'=>'#F59E0B','reviewed'=>'#3B82F6','shortlisted'=>'#43E97B','rejected'=>'#EF4444','hired'=>'#10B981'];
        $color = $statusColors[$app['status']] ?? '#6C63FF';
      ?>
      <div class="card" style="border-left:3px solid <?= $color ?>;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

          <!-- Candidate Info -->
          <div style="display:flex;align-items:flex-start;gap:14px;flex:1;min-width:0;">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:white;flex-shrink:0;">
              <?= strtoupper($app['candidate_name'][0] ?? 'U') ?>
            </div>
            <div style="min-width:0;">
              <div style="font-weight:700;font-size:1rem;color:var(--text-primary);"><?= e($app['candidate_name']) ?></div>
              <div style="font-size:0.8rem;color:var(--text-muted);"><?= e($app['candidate_email']) ?></div>
              <?php if ($app['candidate_phone']): ?>
                <div style="font-size:0.8rem;color:var(--text-muted);"><i class="fas fa-phone"></i> <?= e($app['candidate_phone']) ?></div>
              <?php endif; ?>
              <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">
                Applied for: <strong style="color:var(--text-secondary);"><?= e($app['job_title']) ?></strong>
                · <?= date('d M Y', strtotime($app['applied_at'])) ?>
              </div>
            </div>
          </div>

          <!-- Status -->
          <div style="flex-shrink:0;">
            <span class="badge" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44;font-size:0.82rem;padding:5px 12px;">
              <?= ucfirst(e($app['status'])) ?>
            </span>
          </div>
        </div>

        <!-- Action Row -->
        <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;align-items:center;">
          <?php if ($app['resume_path']): ?>
            <a href="../<?= e($app['resume_path']) ?>" target="_blank" class="btn btn-outline btn-sm">
              <i class="fas fa-file-pdf"></i> View Resume
            </a>
          <?php endif; ?>
          <button class="btn btn-secondary btn-sm" onclick="openModal('modal-<?= $app['id'] ?>')">
            <i class="fas fa-edit"></i> Update Status
          </button>
          <?php if ($app['cover_letter']): ?>
            <button class="btn btn-secondary btn-sm" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
              <i class="fas fa-envelope"></i> Cover Letter
            </button>
            <div style="display:none;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:0.85rem;color:var(--text-secondary);line-height:1.7;width:100%;margin-top:8px;">
              <?= nl2br(e($app['cover_letter'])) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Status Update Modal -->
        <div class="modal-overlay" id="modal-<?= $app['id'] ?>">
          <div class="modal">
            <div class="modal-header">
              <span class="modal-title">Update Application Status</span>
              <button class="modal-close" onclick="closeModal('modal-<?= $app['id'] ?>')">&times;</button>
            </div>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
              Candidate: <strong><?= e($app['candidate_name']) ?></strong>
            </p>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="update_status" value="1">
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <div class="form-group">
                <label class="form-label">New Status</label>
                <select name="status" class="form-control">
                  <?php foreach (['pending','reviewed','shortlisted','rejected','hired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $app['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Note to Candidate</label>
                <textarea name="recruiter_note" class="form-control" rows="3"
                          placeholder="Optional note about the application..."><?= e($app['recruiter_note'] ?? '') ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">Update Status</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
      <div class="card text-center" style="padding:60px 24px;">
        <i class="fas fa-users" style="font-size:3rem;opacity:0.2;color:var(--primary);display:block;margin-bottom:16px;"></i>
        <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:8px;">No Applicants Found</h2>
        <p style="color:var(--text-muted);">No candidates have applied yet<?= $filterStatus ? " with status '$filterStatus'" : '' ?>.</p>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
