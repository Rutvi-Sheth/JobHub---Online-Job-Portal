<?php
/**
 * JobHub - Candidate Dashboard
 * File: candidate/dashboard.php
 * Description: Personalized dashboard showing applied jobs,
 *              resume status, profile completeness, and recent activity.
 */

require_once '../config/db.php';
requireRole('candidate');

$pageTitle = 'My Dashboard – JobHub';
$metaDesc  = 'Manage your job applications, resume, and profile on JobHub.';

$userId = (int)$_SESSION['user_id'];
$pdo    = getDB();

// ── Fetch summary stats ──
$totalApps   = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ?");
$totalApps->execute([$userId]);
$totalApps   = (int)$totalApps->fetchColumn();

$pendingApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ? AND status = 'pending'");
$pendingApps->execute([$userId]);
$pendingApps = (int)$pendingApps->fetchColumn();

$shortlisted = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ? AND status = 'shortlisted'");
$shortlisted->execute([$userId]);
$shortlisted = (int)$shortlisted->fetchColumn();

$hired = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ? AND status = 'hired'");
$hired->execute([$userId]);
$hired = (int)$hired->fetchColumn();

// ── Fetch recent applications ──
$stmt = $pdo->prepare("SELECT a.id, a.status, a.applied_at,j.title 
                      AS job_title, j.location,c.company_name
                      FROM applications a
                      JOIN jobs j ON a.job_id = j.id
                      JOIN companies c ON j.company_id = c.id
                      WHERE a.candidate_id = ?
                      ORDER BY a.applied_at DESC
                      LIMIT 5");
$stmt->execute([$userId]);
$recentApps = $stmt->fetchAll();

// ── Profile completeness ───
$profile = $pdo->prepare("SELECT * FROM candidate_profiles WHERE user_id = ?");
$profile->execute([$userId]);
$profile = $profile->fetch();

$user = $pdo->prepare("SELECT full_name, email, phone, profile_pic FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// Calculate completeness %
$fields = ['full_name'=>$user['full_name'],'email'=>$user['email'],'phone'=>$user['phone']];

if ($profile) 
{
    $fields += ['headline'=>$profile['headline'],'about'=>$profile['about'],'skills'=>$profile['skills'],'education'=>$profile['education']];
}

$filled = count(array_filter($fields, fn($v) => !empty($v)));
$total  = count($fields);
$completeness = (int)(($filled / $total) * 100);

// ── Check if resume uploaded ──
$hasResume = (bool)$pdo->prepare("SELECT id FROM resumes WHERE candidate_id = ? LIMIT 1")->execute([$userId]);
$resumeRow = $pdo->prepare("SELECT id FROM resumes WHERE candidate_id = ? ORDER BY is_primary DESC, uploaded_at DESC LIMIT 1");
$resumeRow->execute([$userId]);
$hasResume = (bool)$resumeRow->fetchColumn();

// ── Recommended jobs (simple: by latest active) ──
$recommended = $pdo->query("SELECT j.id, j.title, j.location, j.job_type, c.company_name
                            FROM jobs j JOIN companies c ON j.company_id = c.id
                            WHERE j.status = 'active'
                            ORDER BY j.created_at DESC LIMIT 4")->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-wrapper">

  <!-- ── Sidebar ── -->
  <aside class="sidebar" aria-label="Candidate navigation">
    <div style="text-align:center; padding:16px 0 24px;">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:white;margin:0 auto 10px;">
        <?= strtoupper(($user['full_name'][0] ?? 'U')) ?>
      </div>

      <div style="font-weight:600; font-size:0.9rem;"><?= e($user['full_name']) ?></div>
      <div class="badge badge-primary mt-1">Candidate</div>
    </div>

    <span class="sidebar-section-title">Main</span>
    <a href="dashboard.php"       class="sidebar-link active"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="../jobs/listing.php" class="sidebar-link"><i class="fas fa-search"></i> Browse Jobs</a>
    <a href="my_applications.php" class="sidebar-link">
      <i class="fas fa-clipboard-list"></i> Applications
      <?php if ($pendingApps): ?><span class="badge badge-warning"><?= $pendingApps ?></span><?php endif; ?>
    </a>

    <span class="sidebar-section-title">Profile</span>
    <a href="profile.php"       class="sidebar-link"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="upload_resume.php" class="sidebar-link"><i class="fas fa-file-upload"></i> Resume</a>

    <span class="sidebar-section-title">Account</span>
    <a href="../logout.php" class="sidebar-link" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <!-- ── Main Content ── -->
  <main class="main-content" id="main-content">

    <!-- Page Header -->
    <div style="margin-bottom:28px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">
        Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?>! 👋
      </h1>
      <p style="color:var(--text-muted); margin-top:4px;">Here's what's happening with your job search.</p>
    </div>

    <!-- ── Stats Grid ── -->
    <div class="stats-grid" role="list">
      <div class="stat-card" role="listitem">
        <div class="stat-icon purple"><i class="fas fa-paper-plane"></i></div>
        <div class="stat-info">
          <div class="stat-val"><?= $totalApps ?></div>
          <div class="stat-lbl">Total Applied</div>
        </div>
      </div>
      
      <div class="stat-card" role="listitem">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
          <div class="stat-val"><?= $pendingApps ?></div>
          <div class="stat-lbl">Pending Review</div>
        </div>
      </div>
      
      <div class="stat-card" role="listitem">
        <div class="stat-icon blue"><i class="fas fa-star"></i></div>
        <div class="stat-info">
          <div class="stat-val"><?= $shortlisted ?></div>
          <div class="stat-lbl">Shortlisted</div>
        </div>
      </div>
      
      <div class="stat-card" role="listitem">
        <div class="stat-icon green"><i class="fas fa-trophy"></i></div>
        <div class="stat-info">
          <div class="stat-val"><?= $hired ?></div>
          <div class="stat-lbl">Hired</div>
        </div>
      </div>
    </div>

    <!-- ── Profile Completeness + Resume ── -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;" class="responsive-2col">

      <!-- Profile Card -->
      <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;"><i class="fas fa-user-circle" style="color:var(--primary);"></i> Profile Completeness</h2>
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:12px;">
          <div style="flex:1; background:var(--border); border-radius:4px; height:8px; overflow:hidden;">
            <div style="width:<?= $completeness ?>%; height:100%; background:<?= $completeness >= 80 ? 'var(--success)' : ($completeness >= 50 ? 'var(--warning)' : 'var(--danger)') ?>; border-radius:4px; transition:width 1s ease;"></div>
          </div>
          <span style="font-weight:700; font-size:0.9rem;"><?= $completeness ?>%</span>
        </div>
        
        <p style="font-size:0.82rem;color:var(--text-muted);">
          <?php if ($completeness < 100): ?>
            Complete your profile to attract recruiters and increase visibility.
          <?php else: ?>
            Your profile is complete! 🎉
          <?php endif; ?>
        </p>
        
        <a href="profile.php" class="btn btn-outline btn-sm mt-2">
          <i class="fas fa-edit"></i> Edit Profile
        </a>
      </div>

      <!-- Resume Card -->
      <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;"><i class="fas fa-file-pdf" style="color:var(--danger);"></i> My Resume</h2>
        <?php if ($hasResume): ?>
          
          <div class="alert alert-success" style="margin-bottom:12px;">
            <i class="fas fa-check-circle"></i> Resume uploaded successfully!
          </div>
          
          <a href="upload_resume.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-sync-alt"></i> Update Resume
          </a>
        
          <?php else: ?>
          <div class="alert alert-warning" style="margin-bottom:12px;">
            <i class="fas fa-exclamation-triangle"></i> No resume uploaded yet.
          </div>
          
          <a href="upload_resume.php" class="btn btn-primary btn-sm">
            <i class="fas fa-upload"></i> Upload Resume
          </a>
        
          <?php endif; ?>
      </div>
    </div>

    <!-- ── Recent Applications ── -->
    <div class="card" style="margin-bottom:28px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-list-check" style="color:var(--primary);"></i> Recent Applications</h2>
        <a href="my_applications.php" class="btn btn-secondary btn-sm">View All</a>
      </div>

      <?php if ($recentApps): ?>
      <div class="table-wrapper">
        <table class="table" role="table" aria-label="Recent job applications">
          <thead>
            <tr>
              <th scope="col">Job Title</th>
              <th scope="col">Company</th>
              <th scope="col">Location</th>
              <th scope="col">Status</th>
              <th scope="col">Applied On</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentApps as $app): ?>
            <tr>
              <td><strong style="color:var(--text-primary);"><?= e($app['job_title']) ?></strong></td>
              <td><?= e($app['company_name']) ?></td>
              <td><?= e($app['location'] ?: '–') ?></td>
              <td>
                <?php
                  $statusMap = [
                    'pending'     => 'badge-warning',
                    'reviewed'    => 'badge-info',
                    'shortlisted' => 'badge-accent',
                    'rejected'    => 'badge-danger',
                    'hired'       => 'badge-success',];
                  $cls = $statusMap[$app['status']] ?? 'badge-primary';
                ?>
                <span class="badge <?= $cls ?>"><?= ucfirst(e($app['status'])) ?></span>
              </td>
              <td><?= date('d M Y', strtotime($app['applied_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <?php else: ?>
        <div class="text-center" style="padding:36px; color:var(--text-muted);">
          <i class="fas fa-inbox" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
          <p>You haven't applied to any jobs yet.</p>
          <a href="../jobs/listing.php" class="btn btn-primary mt-2"><i class="fas fa-search"></i> Browse Jobs</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Recommended Jobs ── -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 style="font-size:1rem;font-weight:700;"><i class="fas fa-bolt" style="color:var(--warning);"></i> Recommended Jobs</h2>
        <a href="../jobs/listing.php" class="btn btn-secondary btn-sm">Browse All</a>
      </div>

      <?php if ($recommended): ?>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($recommended as $job): ?>
        
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);">
          
          <div>
            <div style="font-weight:600;color:var(--text-primary);font-size:0.9rem;"><?= e($job['title']) ?></div>
            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e($job['company_name']) ?> · <?= e($job['location'] ?: 'Remote') ?></div>
          </div>

          <div style="display:flex;align-items:center;gap:8px;">
            <span class="badge badge-primary"><?= e($job['job_type']) ?></span>
            <a href="../jobs/detail.php?id=<?= (int)$job['id'] ?>" class="btn btn-primary btn-sm">Apply</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      
      <?php else: ?>
        <p class="text-muted">No jobs available right now. Check back later!</p>
      <?php endif; ?>
    </div>

  </main>
</div>

<style>
@media (max-width:768px) 
{
  .responsive-2col { grid-template-columns:1fr !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
