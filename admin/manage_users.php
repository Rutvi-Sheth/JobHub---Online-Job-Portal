<?php
/**
 * JobHub - Admin Manage Users
 * File: admin/manage_users.php
 * Description: View, activate/deactivate, and delete all users.
 */

require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Manage Users – JobHub';
$pdo       = getDB();
$csrf      = generateCSRF();

// ── Toggle active ──
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) 
{
    if (validateCSRF($_GET['csrf'] ?? ''))
    {
        $uid  = (int)$_GET['toggle'];
        $cur  = $pdo->prepare("SELECT is_active FROM users WHERE id=?");
        $cur->execute([$uid]); $cur = $cur->fetchColumn();
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$cur ? 0 : 1, $uid]);
        setFlash('success','User status toggled.');
        header('Location: manage_users.php'); exit;
    }
}

// ── Delete user ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) 
{
    if (validateCSRF($_GET['csrf'] ?? '')) 
    {
        $uid = (int)$_GET['delete'];
        // Prevent deleting yourself
        if ($uid === (int)$_SESSION['user_id']) 
        {
            setFlash('danger','You cannot delete your own account.');
        } else 
        {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            setFlash('success','User deleted.');
        }
        header('Location: manage_users.php'); exit;
    }
}

// ── Filters ──
$roleFilter   = sanitise($_GET['role']   ?? '');
$searchFilter = sanitise($_GET['search'] ?? '');

$sql    = "SELECT id, full_name, email, phone, role, is_active, created_at FROM users WHERE 1=1";
$params = [];

if ($roleFilter && in_array($roleFilter, ['admin','recruiter','candidate'], true)) 
{
    $sql .= " AND role=?"; $params[] = $roleFilter;
}

if ($searchFilter) 
{
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$searchFilter%"; $params[] = "%$searchFilter%";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Admin navigation">
    <a href="dashboard.php"        class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="manage_users.php"     class="sidebar-link active"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="manage_jobs.php"      class="sidebar-link"><i class="fas fa-briefcase"></i> Manage Jobs</a>
    <a href="manage_companies.php" class="sidebar-link"><i class="fas fa-building"></i> Manage Companies</a>
    <a href="../logout.php"        class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Manage Users</h1>
      <p style="color:var(--text-muted);">Manage all registered users on the platform.</p>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-3" style="padding:16px 20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;flex:2;min-width:180px;">
          <label class="form-label" style="margin-bottom:4px;">Search</label>
          <input type="text" name="search" class="form-control" value="<?= e($searchFilter) ?>" placeholder="Name or email...">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:130px;">
          <label class="form-label" style="margin-bottom:4px;">Role</label>
          <select name="role" class="form-control">
            <option value="">All Roles</option>
            <option value="admin"     <?= $roleFilter==='admin'     ? 'selected' : '' ?>>Admin</option>
            <option value="recruiter" <?= $roleFilter==='recruiter' ? 'selected' : '' ?>>Recruiter</option>
            <option value="candidate" <?= $roleFilter==='candidate' ? 'selected' : '' ?>>Candidate</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;">Search</button>
        <a href="manage_users.php" class="btn btn-secondary" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- Users Table -->
    <div class="card">
      <div style="margin-bottom:16px;font-size:0.85rem;color:var(--text-muted);">
        Showing <strong style="color:var(--text-primary);"><?= count($users) ?></strong> users
      </div>
      <?php if ($users): ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:30px;height:30px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;flex-shrink:0;">
                    <?= strtoupper($u['full_name'][0]) ?>
                  </div>
                  <span style="color:var(--text-primary);font-weight:600;"><?= e($u['full_name']) ?></span>
                </div>
              </td>
             
              <td><?= e($u['email']) ?></td>
              <td><?= e($u['phone'] ?: '–') ?></td>
              
              <td>
                <span class="badge <?= $u['role']==='admin' ? 'badge-danger' : ($u['role']==='recruiter' ? 'badge-info' : 'badge-primary') ?>">
                  <?= ucfirst(e($u['role'])) ?>
                </span>
              </td>
              
              <td>
                <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-warning' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Disabled' ?>
                </span>
              </td>
              
              <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="manage_users.php?toggle=<?= $u['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                     title="<?= $u['is_active'] ? 'Disable' : 'Enable' ?> user"
                     onclick="return confirm('Toggle user status?')">
                    <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                  </a>

                  <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                  <a href="manage_users.php?delete=<?= $u['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Permanently delete this user and all their data?')">
                    <i class="fas fa-trash"></i>
                  </a>
                  
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-muted text-center" style="padding:36px;">No users found matching your criteria.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '../includes/footer.php'; ?>
