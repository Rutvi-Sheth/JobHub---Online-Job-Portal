<?php
/**
 * JobHub - Admin Manage Jobs
 * File: admin/manage_jobs.php
 * Description: View all posted jobs, toggle status, featured flag, or delete jobs.
 */

require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Manage Jobs – Admin JobHub';
$pdo       = getDB();
$csrf      = generateCSRF();

// ── Handle Delete ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) 
{
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $jid = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$jid]);
        setFlash('success', 'Job deleted successfully.');
        header('Location: manage_jobs.php'); exit;
    }
}

// ── Handle Toggle Status ──
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) 
{
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $jid = (int)$_GET['toggle_status'];
        $stmt = $pdo->prepare("SELECT status FROM jobs WHERE id=?");
        $stmt->execute([$jid]);
        $curr = $stmt->fetchColumn();
        $next = ($curr === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([$next, $jid]);
        setFlash('success', 'Job status updated.');
        header('Location: manage_jobs.php'); exit;
    }
}

// ── Handle Toggle Featured ──
if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) 
{
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $jid = (int)$_GET['toggle_featured'];
        $stmt = $pdo->prepare("SELECT is_featured FROM jobs WHERE id=?");
        $stmt->execute([$jid]);
        $curr = (int)$stmt->fetchColumn();
        $pdo->prepare("UPDATE jobs SET is_featured=? WHERE id=?")->execute([$curr ? 0 : 1, $jid]);
        setFlash('success', 'Featured status updated.');
        header('Location: manage_jobs.php'); exit;
    }
}

// ── Filters & Search ──
$searchFilter = sanitise($_GET['search'] ?? '');
$statusFilter = sanitise($_GET['status'] ?? '');

$sql = "SELECT j.*, c.company_name, u.full_name AS recruiter_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS app_count
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        JOIN users u ON j.recruiter_id = u.id
        WHERE 1=1";
$params = [];

if ($statusFilter && in_array($statusFilter, ['active', 'inactive', 'closed'], true)) 
{
    $sql .= " AND j.status = ?";
    $params[] = $statusFilter;
}

if ($searchFilter !== '') 
{
    $sql .= " AND (j.title LIKE ? OR c.company_name LIKE ? OR j.category LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

$sql .= " ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Admin navigation">
    <a href="dashboard.php"        class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="manage_users.php"     class="sidebar-link"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="manage_jobs.php"      class="sidebar-link active"><i class="fas fa-briefcase"></i> Manage Jobs</a>
    <a href="manage_companies.php" class="sidebar-link"><i class="fas fa-building"></i> Manage Companies</a>
    <a href="../logout.php"        class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Manage All Jobs</h1>
      <p style="color:var(--text-muted);">View, update, or remove job listings across the platform.</p>
    </div>

    <!-- Filter Card -->
    <div class="card mb-3" style="padding:16px 20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;flex:2;min-width:180px;">
          <label class="form-label" style="margin-bottom:4px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= e($searchFilter) ?>" placeholder="Title, company, or category...">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
          <label class="form-label" style="margin-bottom:4px;">Status</label>
          <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="active"   <?= $statusFilter==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter==='inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="closed"   <?= $statusFilter==='closed'   ? 'selected' : '' ?>>Closed</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;">Search</button>
        <a href="manage_jobs.php" class="btn btn-secondary" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- Jobs Table -->
    <div class="card">
      <div style="margin-bottom:16px;font-size:0.85rem;color:var(--text-muted);">
        Found <strong style="color:var(--text-primary);"><?= count($jobs) ?></strong> job postings
      </div>
      <?php if ($jobs): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Job Title</th>
              <th>Company</th>
              <th>Recruiter</th>
              <th>Category</th>
              <th>Apps</th>
              <th>Featured</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            
          <?php foreach ($jobs as $i => $j): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              
              <td>
                <strong style="color:var(--text-primary);"><?= e($j['title']) ?></strong><br>
                <small style="color:var(--text-muted);"><?= e($j['job_type']) ?> · <?= e($j['location'] ?: 'Remote') ?></small>
              </td>
              
              <td><?= e($j['company_name']) ?></td>
              <td><?= e($j['recruiter_name']) ?></td>
              
              <td>
                <span class="badge badge-primary"><?= e($j['category'] ?: 'General') ?></span>
              </td>
              
              <td style="text-align:center;"><strong><?= (int)$j['app_count'] ?></strong></td>
              
              <td>
                <a href="manage_jobs.php?toggle_featured=<?= $j['id'] ?>&csrf=<?= e($csrf) ?>"
                   class="badge <?= $j['is_featured'] ? 'badge-warning' : 'badge-secondary' ?>"
                   title="Click to toggle featured flag">
                  <?= $j['is_featured'] ? '★ Featured' : 'Normal' ?>
                </a>
              </td>
              
              <td>
                <span class="badge <?= $j['status']==='active' ? 'badge-success' : ($j['status']==='closed' ? 'badge-danger' : 'badge-warning') ?>">
                  <?= ucfirst(e($j['status'])) ?>
                </span>
              </td>
              
              <td>
                <div style="display:flex;gap:6px;">
                  
                <a href="../jobs/detail.php?id=<?= $j['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="View"><i class="fas fa-eye"></i></a>
                  <a href="manage_jobs.php?toggle_status=<?= $j['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-sm <?= $j['status']==='active' ? 'btn-warning' : 'btn-success' ?>"
                     title="Toggle Status">
                    <i class="fas <?= $j['status']==='active' ? 'fa-pause' : 'fa-play' ?>"></i>
                  </a>
                  
                  <a href="manage_jobs.php?delete=<?= $j['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-danger btn-sm"
                     title="Delete"
                     onclick="return confirm('Are you sure you want to delete this job posting?')">
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
        <p class="text-muted text-center" style="padding:36px;">No jobs found matching your criteria.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
