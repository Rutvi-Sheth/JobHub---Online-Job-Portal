<?php
/**
 * JobHub - Job Listing Page
 * File: jobs/listing.php
 * Description: Browse all active jobs with category, location, and type filters & pagination.
 */

require_once '../config/db.php';

$pageTitle = 'Browse All Jobs – JobHub';
$metaDesc  = 'Browse active job listings across IT, Finance, HR, Marketing, Engineering, and more.';

$pdo = getDB();

// ── Pagination & Filtering ──────
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 9;
$offset   = ($page - 1) * $limit;

$category = sanitise($_GET['category'] ?? '');
$jobType  = sanitise($_GET['job_type'] ?? '');
$location = sanitise($_GET['location'] ?? '');
$keyword  = sanitise($_GET['q']        ?? '');

$where  = ["j.status = 'active'", "(j.deadline IS NULL OR j.deadline >= CURDATE())"];
$params = [];

if ($category !== '') 
{
    $where[]  = "j.category = ?";
    $params[] = $category;
}
if ($jobType !== '') 
{
    $where[]  = "j.job_type = ?";
    $params[] = $jobType;
}
if ($location !== '') 
{
    $where[]  = "j.location LIKE ?";
    $params[] = "%$location%";
}
if ($keyword !== '') 
{
    $where[]  = "(j.title LIKE ? OR j.description LIKE ? OR j.skills LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$whereClause = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) FROM jobs j JOIN companies c ON j.company_id = c.id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalJobs  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalJobs / $limit));

// Fetch jobs
$sql = "SELECT j.id, j.title, j.job_type, j.category, j.location, j.salary_min, j.salary_max,
               j.experience, j.deadline, j.is_featured, j.created_at,
               c.company_name, c.logo AS company_logo
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE $whereClause
        ORDER BY j.is_featured DESC, j.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Categories list for filter sidebar
$categories = $pdo->query("SELECT name FROM job_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="../index.php">Home</a> <span class="sep">/</span> <span>Browse Jobs</span>
    </div>
    <h1 class="page-title">Explore Job Opportunities</h1>
    <p class="page-sub">Discover thousands of verified job postings from top employers.</p>
  </div>
</div>

<div class="container mb-4">
  <div style="display:grid; grid-template-columns: 280px 1fr; gap: 28px;" class="listing-layout">

    <!-- Filters Sidebar -->
    <aside class="card" style="align-self: flex-start; position: sticky; top: 84px;">
      <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border);">
        <i class="fas fa-filter" style="color:var(--primary); margin-right:6px;"></i> Filter Jobs
      </h2>

      <form action="listing.php" method="GET">
        <!-- Search Keyword -->
        <div class="form-group">
          <label class="form-label" for="q">Keyword</label>
          <input type="text" id="q" name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Title, skills, company...">
        </div>

        <!-- Category -->
        <div class="form-group">
          <label class="form-label" for="category">Category</label>
          <select id="category" name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Job Type -->
        <div class="form-group">
          <label class="form-label" for="job_type">Job Type</label>
          <select id="job_type" name="job_type" class="form-control">
            <option value="">All Types</option>
            <?php foreach (['Full-Time','Part-Time','Contract','Internship','Remote'] as $type): ?>
              <option value="<?= $type ?>" <?= $jobType === $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Location -->
        <div class="form-group">
          <label class="form-label" for="location">Location</label>
          <input type="text" id="location" name="location" class="form-control" value="<?= e($location) ?>" placeholder="City or Remote">
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Apply Filters</button>
          <a href="listing.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </div>
      </form>
    </aside>

    <!-- Main Jobs Grid -->
    <main>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <div style="font-size:0.9rem; color:var(--text-muted);">
          Showing <strong style="color:var(--text-primary);"><?= count($jobs) ?></strong> of <strong style="color:var(--text-primary);"><?= $totalJobs ?></strong> active jobs
        </div>
      </div>

      <?php if ($jobs): ?>
        <div class="jobs-grid" role="list">
          <?php foreach ($jobs as $job): ?>
            <article class="job-card" role="listitem">
              <?php if ($job['is_featured']): ?>
                <div class="featured-ribbon">Featured</div>
              <?php endif; ?>

              <div class="job-card-header">
                <div class="company-logo">
                  <?php if ($job['company_logo']): ?>
                    <img src="../<?= e($job['company_logo']) ?>" alt="<?= e($job['company_name']) ?>">
                  <?php else: ?>
                    <i class="fas fa-building"></i>
                  <?php endif; ?>
                </div>
                <div class="job-card-info">
                  <div class="job-title"><?= e($job['title']) ?></div>
                  <div class="company-name"><?= e($job['company_name']) ?></div>
                </div>
              </div>

              <div class="job-meta">
                <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> <?= e($job['location'] ?: 'Remote') ?></span>
                <span class="job-meta-item"><i class="fas fa-clock"></i> <?= e($job['job_type']) ?></span>
                <?php if ($job['experience']): ?>
                  <span class="job-meta-item"><i class="fas fa-user-tie"></i> <?= e($job['experience']) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($job['category']): ?>
                <div><span class="badge badge-primary"><?= e($job['category']) ?></span></div>
              <?php endif; ?>

              <div class="job-card-footer">
                <div class="job-salary">
                  <?php if ($job['salary_min'] && $job['salary_max']): ?>
                    ₹<?= number_format($job['salary_min']/100000, 1) ?>L – ₹<?= number_format($job['salary_max']/100000, 1) ?>L/yr
                  <?php else: ?>
                    Salary: Negotiable
                  <?php endif; ?>
                </div>
                <a href="detail.php?id=<?= (int)$job['id'] ?>" class="btn btn-primary btn-sm">View Job</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <nav class="pagination" aria-label="Page navigation">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page-1 ?>&q=<?= urlencode($keyword) ?>&category=<?= urlencode($category) ?>&job_type=<?= urlencode($jobType) ?>&location=<?= urlencode($location) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&category=<?= urlencode($category) ?>&job_type=<?= urlencode($jobType) ?>&location=<?= urlencode($location) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page+1 ?>&q=<?= urlencode($keyword) ?>&category=<?= urlencode($category) ?>&job_type=<?= urlencode($jobType) ?>&location=<?= urlencode($location) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>

      <?php else: ?>
        <div class="card text-center" style="padding:60px 20px;">
          <i class="fas fa-search" style="font-size:3rem; color:var(--text-muted); opacity:0.3; margin-bottom:16px;"></i>
          <h2 style="font-size:1.3rem; font-weight:700; margin-bottom:8px;">No Jobs Found</h2>
          <p style="color:var(--text-muted); margin-bottom:20px;">Try adjusting your filter parameters or search terms.</p>
          <a href="listing.php" class="btn btn-primary">Clear Filters</a>
        </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<style>

@media(max-width: 860px) 
{
  .listing-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
