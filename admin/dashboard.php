<?php
/**
 * JobHub - Admin Dashboard
 * File: admin/dashboard.php
 * Description: Site-wide statistics, recent activity, quick links.
 */

require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard – JobHub';
$pdo       = getDB();

// ── Site-wide stats ──
$stats = [
    'users'        => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'candidates'   => $pdo->query("SELECT COUNT(*) FROM users WHERE role='candidate'")->fetchColumn(),
    'recruiters'   => $pdo->query("SELECT COUNT(*) FROM users WHERE role='recruiter'")->fetchColumn(),
    'companies'    => $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
    'jobs'         => $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'active_jobs'  => $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='active'")->fetchColumn(),
    'applications' => $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
    'resumes'      => $pdo->query("SELECT COUNT(*) FROM resumes")->fetchColumn(),
];

// ── Recent users ──
$recentUsers = $pdo->query("SELECT id, full_name, email, role, is_active, created_at
                            FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

// ── Recent jobs ──
$recentJobs = $pdo->query("SELECT j.id, j.title, j.status, j.created_at,c.company_name
                          FROM jobs j JOIN companies c ON j.company_id=c.id
                          ORDER BY j.created_at DESC LIMIT 6")->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" aria-label="Admin navigation">
    <div style="text-align:center;padding:16px 0 24px;">
      <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#FF6584,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:white;margin:0 auto 10px;">A</div>
      <div style="font-weight:600;font-size:0.9rem;"><?= e($_SESSION['user_name']) ?></div>
      <span class="badge badge-danger mt-1">Admin</span>
    </div>
    <a href="dashboard.php"        class="sidebar-link active"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="manage_users.php"     class="sidebar-link"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="manage_jobs.php"      class="sidebar-link"><i class="fas fa-briefcase"></i> Manage Jobs</a>
    <a href="manage_companies.php" class="sidebar-link"><i class="fas fa-building"></i> Manage Companies</a>
    <a href="../jobs/listing.php"  class="sidebar-link"><i class="fas fa-search"></i> View Job Board</a>
    <a href="../logout.php"        class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:28px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Admin Dashboard</h1>
      <p style="color:var(--text-muted);">Platform overview and site management.</p>
    </div>

    <!-- Stats Row 1 -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">
      <?php
      $statDefs = [
        ['val'=>$stats['users'],        'lbl'=>'Total Users',      'icon'=>'fa-users',          'cls'=>'purple'],
        ['val'=>$stats['candidates'],   'lbl'=>'Candidates',       'icon'=>'fa-user-graduate',  'cls'=>'blue'],
        ['val'=>$stats['recruiters'],   'lbl'=>'Recruiters',       'icon'=>'fa-user-tie',       'cls'=>'orange'],
        ['val'=>$stats['companies'],    'lbl'=>'Companies',        'icon'=>'fa-building',       'cls'=>'green'],
        ['val'=>$stats['active_jobs'],  'lbl'=>'Active Jobs',      'icon'=>'fa-briefcase',      'cls'=>'purple'],
        ['val'=>$stats['applications'], 'lbl'=>'Applications',     'icon'=>'fa-clipboard-list', 'cls'=>'blue'],
        ['val'=>$stats['resumes'],      'lbl'=>'Resumes Uploaded', 'icon'=>'fa-file-upload',    'cls'=>'green'],
        ['val'=>$stats['jobs'],         'lbl'=>'Total Jobs',       'icon'=>'fa-list',           'cls'=>'orange'],
      ];
      
      foreach ($statDefs as $sd): ?>
      <div class="stat-card">
        <div class="stat-icon <?= $sd['cls'] ?>"><i class="fas <?= $sd['icon'] ?>"></i></div>
        <div class="stat-info">
          <div class="stat-val"><?= number_format($sd['val']) ?></div>
          <div class="stat-lbl"><?= $sd['lbl'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div style="display:flex;gap:12px;margin-bottom:28px;flex-wrap:wrap;">
      <a href="manage_users.php"     class="btn btn-primary"><i class="fas fa-users-cog"></i> Manage Users</a>
      <a href="manage_jobs.php"      class="btn btn-secondary"><i class="fas fa-briefcase"></i> Manage Jobs</a>
      <a href="manage_companies.php" class="btn btn-secondary"><i class="fas fa-building"></i> Manage Companies</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="admin-grid">

      <!-- Recent Users -->
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-user-plus" style="color:var(--primary);"></i> Recent Users</h2>
          <a href="manage_users.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($recentUsers as $u): ?>
          <div style="display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid var(--border);">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:white;flex-shrink:0;">
              <?= strtoupper($u['full_name'][0]) ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($u['full_name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($u['email']) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
              <span class="badge <?= $u['role']==='admin' ? 'badge-danger' : ($u['role']==='recruiter' ? 'badge-info' : 'badge-primary') ?>">
                <?= ucfirst(e($u['role'])) ?>
              </span>
              <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-warning' ?>" style="font-size:0.65rem;">
                <?= $u['is_active'] ? 'Active' : 'Disabled' ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recent Jobs -->
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-briefcase" style="color:var(--primary);"></i> Recent Jobs</h2>
          <a href="manage_jobs.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($recentJobs as $job): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-bottom:10px;border-bottom:1px solid var(--border);">
            <div style="min-width:0;">
              <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($job['title']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($job['company_name']) ?></div>
            </div>
            <span class="badge <?= $job['status']==='active' ? 'badge-success' : 'badge-warning' ?>" style="flex-shrink:0;">
              <?= ucfirst(e($job['status'])) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<style>
@media(max-width:768px)
{ 
.admin-grid{grid-template-columns:1fr!important;} 
}
</style>
<?php include '../includes/footer.php'; ?>
