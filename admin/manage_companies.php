<?php
/**
 * JobHub - Admin Manage Companies
 * File: admin/manage_companies.php
 * Description: View, verify, or delete registered companies.
 */

require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Manage Companies – Admin JobHub';
$pdo       = getDB();
$csrf      = generateCSRF();

// ── Handle Delete ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) 
  {
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $cid = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM companies WHERE id=?")->execute([$cid]);
        setFlash('success', 'Company profile deleted.');
        header('Location: manage_companies.php'); exit;
    }
}

// ── Handle Toggle Verification ──
if (isset($_GET['toggle_verify']) && is_numeric($_GET['toggle_verify'])) 
  {
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $cid = (int)$_GET['toggle_verify'];
        $stmt = $pdo->prepare("SELECT is_verified FROM companies WHERE id=?");
        $stmt->execute([$cid]);
        $curr = (int)$stmt->fetchColumn();
        $pdo->prepare("UPDATE companies SET is_verified=? WHERE id=?")->execute([$curr ? 0 : 1, $cid]);
        setFlash('success', 'Company verification status updated.');
        header('Location: manage_companies.php'); exit;
    }
}

// ── Filters & Search ──
$searchFilter = sanitise($_GET['search'] ?? '');

$sql = "SELECT c.*, u.full_name AS recruiter_name, u.email AS recruiter_email,
        (SELECT COUNT(*) FROM jobs j WHERE j.company_id = c.id) AS job_count
        FROM companies c
        JOIN users u ON c.recruiter_id = u.id
        WHERE 1=1";

$params = [];

if ($searchFilter !== '') 
{
    $sql .= " AND (c.company_name LIKE ? OR c.industry LIKE ? OR c.location LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Admin navigation">
    <a href="dashboard.php"        class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="manage_users.php"     class="sidebar-link"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="manage_jobs.php"      class="sidebar-link"><i class="fas fa-briefcase"></i> Manage Jobs</a>
    <a href="manage_companies.php" class="sidebar-link active"><i class="fas fa-building"></i> Manage Companies</a>
    <a href="../logout.php"        class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Manage Companies</h1>
      <p style="color:var(--text-muted);">Verify and manage recruiter company profiles.</p>
    </div>

    <!-- Search Card -->
    <div class="card mb-3" style="padding:16px 20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;flex:2;min-width:200px;">
          <label class="form-label" style="margin-bottom:4px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= e($searchFilter) ?>" placeholder="Company name, industry, or location...">
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;">Search</button>
        <a href="manage_companies.php" class="btn btn-secondary" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- Companies Table -->
    <div class="card">
      <div style="margin-bottom:16px;font-size:0.85rem;color:var(--text-muted);">
        Found <strong style="color:var(--text-primary);"><?= count($companies) ?></strong> companies
      </div>
      <?php if ($companies): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Company Name</th>
              <th>Industry</th>
              <th>Recruiter</th>
              <th>Location</th>
              <th>Jobs</th>
              <th>Verified</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($companies as $i => $c): ?>

            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <strong style="color:var(--text-primary);"><?= e($c['company_name']) ?></strong><br>
                <?php if ($c['website']): ?>
                  <small><a href="<?= e($c['website']) ?>" target="_blank" style="color:var(--primary-light);"><?= e($c['website']) ?></a></small>
                <?php endif; ?>
              </td>
              
              <td><span class="badge badge-primary"><?= e($c['industry'] ?: 'Not specified') ?></span></td>
              <td>
                <?= e($c['recruiter_name']) ?><br>
                <small style="color:var(--text-muted);"><?= e($c['recruiter_email']) ?></small>
              </td>
              
              <td><?= e($c['location'] ?: '–') ?></td>
              <td style="text-align:center;"><strong><?= (int)$c['job_count'] ?></strong></td>
              <td>
                <span class="badge <?= $c['is_verified'] ? 'badge-success' : 'badge-warning' ?>">
                  <?= $c['is_verified'] ? '✓ Verified' : 'Unverified' ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="manage_companies.php?toggle_verify=<?= $c['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-sm <?= $c['is_verified'] ? 'btn-warning' : 'btn-success' ?>"
                     title="Toggle Verification">
                    <i class="fas <?= $c['is_verified'] ? 'fa-times-circle' : 'fa-check-circle' ?>"></i>
                  </a>
                  <a href="manage_companies.php?delete=<?= $c['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-danger btn-sm"
                     title="Delete"
                     onclick="return confirm('Are you sure you want to delete this company profile?')">
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
        <p class="text-muted text-center" style="padding:36px;">No companies found matching your criteria.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
