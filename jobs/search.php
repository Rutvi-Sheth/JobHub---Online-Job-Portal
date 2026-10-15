<?php
/**
 * JobHub - Job Search Page
 * File: jobs/search.php
 * Description: Dedicated search page with instant keyword, location, and category filtering.
 */

require_once '../config/db.php';

$pageTitle = 'Search Jobs – JobHub';
$metaDesc  = 'Advanced job search engine on JobHub. Filter by skills, title, salary, or location.';

$query    = sanitise($_GET['q']        ?? '');
$location = sanitise($_GET['location'] ?? '');
$category = sanitise($_GET['category'] ?? '');

$pdo = getDB();

// Fulltext or LIKE search query
$where = ["j.status = 'active'", "(j.deadline IS NULL OR j.deadline >= CURDATE())"];
$params = [];

if (!empty($query)) {
    $where[] = "(j.title LIKE ? OR j.description LIKE ? OR j.skills LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
    $params[] = "%$query%";
    $params[] = "%$query%";
}

if (!empty($location)) {
    $where[] = "j.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($category)) {
    $where[] = "j.category = ?";
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT j.id, j.title, j.job_type, j.category, j.location, j.salary_min, j.salary_max,
           j.experience, j.deadline, j.is_featured, j.created_at,
           c.company_name, c.logo AS company_logo
    FROM jobs j
    JOIN companies c ON j.company_id = c.id
    WHERE $whereClause
    ORDER BY j.is_featured DESC, j.created_at DESC
    LIMIT 30
");
$stmt->execute($params);
$searchResults = $stmt->fetchAll();

$categories = $pdo->query("SELECT name FROM job_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="../index.php">Home</a> <span class="sep">/</span> <span>Search Jobs</span>
    </div>
    <h1 class="page-title">Find Jobs Matching Your Skills</h1>
    <p class="page-sub">Search across thousands of active job opportunities nationwide.</p>
  </div>
</div>

<div class="container mb-4">

  <!-- Search Bar -->
  <div class="card mb-4" style="padding: 28px;">
    <form action="search.php" method="GET" style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 14px;" class="search-form-grid">
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="q">Job Title or Skills</label>
        <input type="text" id="q" name="q" class="form-control" value="<?= e($query) ?>" placeholder="e.g. PHP Developer, React, SQL">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" for="location">Location</label>
        <input type="text" id="location" name="location" class="form-control" value="<?= e($location) ?>" placeholder="City or Remote">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" for="category">Category</label>
        <select id="category" name="category" class="form-control">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin:0; align-self: flex-end;">
        <button type="submit" class="btn btn-primary btn-lg" style="height:46px; width:100%;">
          <i class="fas fa-search"></i> Search
        </button>
      </div>
    </form>
  </div>

  <!-- Results Count -->
  <div style="margin-bottom:20px; font-size:0.9rem; color:var(--text-muted);">
    Found <strong style="color:var(--text-primary);"><?= count($searchResults) ?></strong> job results
    <?php if ($query): ?> for "<strong style="color:var(--primary-light);"><?= e($query) ?></strong>"<?php endif; ?>
  </div>

  <!-- Results Grid -->
  <?php if ($searchResults): ?>
    <div class="jobs-grid" role="list">
      <?php foreach ($searchResults as $job): ?>
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
  <?php else: ?>
    <div class="card text-center" style="padding:60px 20px;">
      <i class="fas fa-search" style="font-size:3rem; color:var(--text-muted); opacity:0.3; margin-bottom:16px;"></i>
      <h2 style="font-size:1.3rem; font-weight:700; margin-bottom:8px;">No Matching Jobs Found</h2>
      <p style="color:var(--text-muted); margin-bottom:20px;">Try searching with different keywords or clearing filters.</p>
      <a href="search.php" class="btn btn-primary">Reset Search</a>
    </div>
  <?php endif; ?>

</div>

<style>
@media(max-width: 768px) {
  .search-form-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
