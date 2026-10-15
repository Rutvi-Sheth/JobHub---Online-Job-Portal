<?php
/**
 * JobHub - Home Page
 * File: index.php
 * Description: Landing page with hero search, featured jobs,
 *              job categories, stats, and how-it-works section.
 */

require_once 'config/db.php';

$pageTitle = 'JobHub – Dream. Apply. Succeed.';
$metaDesc  = 'JobHub is a modern job portal connecting talented candidates with top companies across India.';

// ── Fetch stats ──
$pdo = getDB();

$totalJobs  = $pdo->query("SELECT COUNT(*) FROM jobs   WHERE status = 'active' AND (deadline IS NULL OR deadline >= CURDATE())")->fetchColumn();
$totalComp  = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users  WHERE role = 'candidate'")->fetchColumn();

// ── Fetch featured jobs (most recent active and not expired) ──
$stmt = $pdo->prepare("SELECT j.id, j.title, j.job_type, j.location, j.salary_min, j.salary_max,j.experience, j.deadline, j.is_featured, j.category,c.company_name, c.logo AS company_logo
                      FROM jobs j
                      JOIN companies c ON j.company_id = c.id
                      WHERE j.status = 'active' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                      ORDER BY j.is_featured DESC, j.created_at DESC
                      LIMIT 6");
$stmt->execute();
$featuredJobs = $stmt->fetchAll();

// ── Fetch categories with job counts ──
$cats = $pdo->query("SELECT cat.name, cat.icon,
                    COUNT(j.id) AS job_count
                    FROM job_categories cat
                    LEFT JOIN jobs j ON j.category = cat.name AND j.status = 'active' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                    GROUP BY cat.id
                    ORDER BY job_count DESC
                    LIMIT 8")->fetchAll();

include 'includes/header.php';
?>
<!-- ════════════════════════════════════════════════════════════
     HERO SECTION
═════════════════════════════════════════════════════════════ -->
<section class="hero" id="hero" aria-label="Hero section">
  <div class="container">
    <div class="hero-center animate-fade-up">

        <!-- Eyebrow -->
        <div class="hero-eyebrow" role="text">
          <i class="fas fa-bolt" aria-hidden="true"></i>
          #1 Job Portal in India
        </div>

        <!-- Title -->
        <h1 class="hero-title">
          Find Your <span class="highlight">Dream Job</span><br>
          &amp; Build Your Future
        </h1>

        <!-- Subtitle -->
        <p class="hero-sub">
          Discover thousands of opportunities across top companies. Whether you're a fresher or an experienced professional, JobHub has the right role for you.
        </p>

        <!-- Search Form -->
        <form class="hero-search" action="jobs/search.php" method="GET" role="search" aria-label="Job search">
          <label class="sr-only" for="hero-search-input">Search for jobs</label>
          <input type="text" id="hero-search-input" name="q"
                 placeholder="Job title, skills, or company..."
                 value="<?= e($_GET['q'] ?? '') ?>"
                 autocomplete="off">

          <label class="sr-only" for="hero-category">Category</label>
          <select name="category" id="hero-category">
            <option value="">All Categories</option>
            <option value="Information Technology">IT / Software</option>
            <option value="Finance & Accounting">Finance</option>
            <option value="Marketing & Sales">Marketing</option>
            <option value="Human Resources">HR</option>
            <option value="Engineering">Engineering</option>
            <option value="Healthcare">Healthcare</option>
            <option value="Education">Education</option>
            <option value="Design & Creative">Design</option>
          </select>

          <button type="submit" class="search-btn" id="hero-search-btn" aria-label="Search jobs">
            <i class="fas fa-search" aria-hidden="true"></i> Search Jobs
          </button>
        </form>

        <!-- Popular Searches -->
        <div class="hero-popular">
          <span>Popular:</span>
          <a href="jobs/search.php?q=PHP+Developer">PHP Developer</a>
          <a href="jobs/search.php?q=React">React</a>
          <a href="jobs/search.php?q=Data+Analyst">Data Analyst</a>
          <a href="jobs/search.php?q=UI+Designer">UI Designer</a>
          <a href="jobs/search.php?q=Java">Java</a>
        </div>

        <!-- Stats -->
        <div class="hero-stats" role="list">
          <div class="stat-item" role="listitem">
            <div class="stat-num"><?= number_format($totalJobs) ?>+</div>
            <div class="stat-label">Active Jobs</div>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-item" role="listitem">
            <div class="stat-num"><?= number_format($totalComp) ?>+</div>
            <div class="stat-label">Companies</div>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-item" role="listitem">
            <div class="stat-num"><?= number_format($totalUsers) ?>+</div>
            <div class="stat-label">Candidates</div>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-item" role="listitem">
            <div class="stat-num">95%</div>
            <div class="stat-label">Success Rate</div>
          </div>
        </div><!-- /hero-stats -->

    </div><!-- /hero-center -->
  </div><!-- /container -->
</section>


<!-- ════════════════════════════════════════════════════════════
     JOB CATEGORIES
═════════════════════════════════════════════════════════════ -->
<section class="section" id="categories" aria-label="Job categories">
  <div class="container">
    <div class="section-header">
      
      <span class="section-tag">Explore</span>
      <h2 class="section-title">Browse by Category</h2>
      <p class="section-sub">Find jobs in your area of expertise from hundreds of job categories.</p>
    </div>

    <div class="categories-grid" role="list">
      <?php if ($cats): ?>
        <?php foreach ($cats as $cat): ?>
          <a href="jobs/search.php?category=<?= urlencode($cat['name']) ?>"
             class="category-card"
             role="listitem"
             aria-label="<?= e($cat['name']) ?> - <?= (int)$cat['job_count'] ?> jobs">
            <div class="category-icon" aria-hidden="true">
              <i class="<?= e($cat['icon'] ?: 'fas fa-briefcase') ?>"></i>
            </div>
            <div class="category-name"><?= e($cat['name']) ?></div>
            <div class="category-count"><?= (int)$cat['job_count'] ?> Jobs</div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted text-center" style="grid-column:1/-1;">No categories found yet.</p>
      <?php endif; ?>
    </div>
      
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FEATURED JOBS
═════════════════════════════════════════════════════════════ -->
<section class="section" style="padding-top:0;" id="featured-jobs" aria-label="Featured jobs">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Hot Picks</span>
      <h2 class="section-title">Featured Job Openings</h2>
      <p class="section-sub">Hand-picked opportunities from top employers — apply before they're gone!</p>
    </div>

    <?php if ($featuredJobs): ?>
    <div class="jobs-grid" role="list">
      <?php foreach ($featuredJobs as $job): ?>
        <article class="job-card animate-fade-up"
                 role="listitem"
                 data-title="<?= e($job['title']) ?>"
                 data-location="<?= e($job['location']) ?>"
                 data-category="<?= e($job['category']) ?>">

          <?php if ($job['is_featured']): ?>
            <div class="featured-ribbon" aria-label="Featured">Featured</div>
          <?php endif; ?>

          <!-- Header -->
          <div class="job-card-header">
            <div class="company-logo" aria-hidden="true">
              <?php if ($job['company_logo']): ?>
                <img src="<?= e($job['company_logo']) ?>" alt="<?= e($job['company_name']) ?> logo">
              <?php else: ?>
                <i class="fas fa-building"></i>
              <?php endif; ?>
            </div>
            <div class="job-card-info">
              <div class="job-title"><?= e($job['title']) ?></div>
              <div class="company-name"><?= e($job['company_name']) ?></div>
            </div>
          </div>

          <!-- Badges -->
          <div class="job-meta" role="list">
            <span class="job-meta-item" role="listitem">
              <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
              <?= e($job['location'] ?: 'Remote') ?>
            </span>
            <span class="job-meta-item" role="listitem">
              <i class="fas fa-clock" aria-hidden="true"></i>
              <?= e($job['job_type']) ?>
            </span>
            <?php if ($job['experience']): ?>
            <span class="job-meta-item" role="listitem">
              <i class="fas fa-user-tie" aria-hidden="true"></i>
              <?= e($job['experience']) ?>
            </span>
            <?php endif; ?>
            <?php if ($job['deadline']): ?>
            <span class="job-meta-item" role="listitem">
              <i class="fas fa-calendar-alt" aria-hidden="true"></i>
              Deadline: <?= date('d M Y', strtotime($job['deadline'])) ?>
            </span>
            <?php endif; ?>
          </div>

          <!-- Category badge -->
          <?php if ($job['category']): ?>
          <div><span class="badge badge-primary"><?= e($job['category']) ?></span></div>
          <?php endif; ?>

          <!-- Footer -->
          <div class="job-card-footer">
            <div class="job-salary">
              <?php if ($job['salary_min'] && $job['salary_max']): ?>
                ₹<?= number_format($job['salary_min']/100000, 1) ?>L – ₹<?= number_format($job['salary_max']/100000, 1) ?>L/yr
              <?php else: ?>
                Salary: Negotiable
              <?php endif; ?>
            </div>
            <a href="jobs/detail.php?id=<?= (int)$job['id'] ?>"
               class="btn btn-primary btn-sm"
               aria-label="View details for <?= e($job['title']) ?>">
              View Job
            </a>
          </div>

        </article>
      <?php endforeach; ?>
    </div><!-- /jobs-grid -->
    <?php else: ?>
      <div class="text-center" style="padding:60px 0; color:var(--text-muted);">
        <i class="fas fa-briefcase" style="font-size:3rem; opacity:0.3; display:block; margin-bottom:16px;"></i>
        <p>No featured jobs yet. <a href="register.php">Register as a Recruiter</a> to post jobs.</p>
      </div>
    <?php endif; ?>

    <div class="text-center mt-4">
      <a href="jobs/listing.php" class="btn btn-outline btn-lg">
        <i class="fas fa-th-list"></i> View All Jobs
      </a>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     HOW IT WORKS
═════════════════════════════════════════════════════════════ -->
<section class="section" style="background:rgba(255,255,255,0.01); border-top:1px solid var(--border); border-bottom:1px solid var(--border);" id="how-it-works" aria-label="How it works">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Simple Steps</span>
      <h2 class="section-title">How JobHub Works</h2>
      <p class="section-sub">Three simple steps to land your dream job.</p>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:28px;">

      <?php
      $steps = [
        ['icon'=>'fa-user-plus',   'num'=>'01', 'title'=>'Create Account',   'desc'=>'Sign up as a Candidate or Recruiter in under 2 minutes with email verification.'],
        ['icon'=>'fa-search',       'num'=>'02', 'title'=>'Find or Post Jobs', 'desc'=>'Browse thousands of jobs by category, location, or salary — or post your openings.'],
        ['icon'=>'fa-paper-plane',  'num'=>'03', 'title'=>'Apply & Get Hired', 'desc'=>'Submit your resume and cover letter, track your applications, and land your dream job.'],
      ];
      foreach ($steps as $s): ?>
      <div class="card" style="text-align:center; padding:36px 28px;">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(108,99,255,0.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.6rem;color:var(--primary);">
          <i class="fas <?= $s['icon'] ?>" aria-hidden="true"></i>
        </div>
        <div style="font-size:0.7rem;font-weight:800;letter-spacing:2px;color:var(--primary);text-transform:uppercase;margin-bottom:8px;"><?= $s['num'] ?></div>
        <h3 style="font-family:var(--font-heading);font-size:1.1rem;font-weight:700;margin-bottom:10px;"><?= $s['title'] ?></h3>
        <p style="font-size:0.875rem;color:var(--text-muted);line-height:1.7;"><?= $s['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     CTA BANNER
═════════════════════════════════════════════════════════════ -->
<section class="section" id="cta" aria-label="Call to action">
  <div class="container">
    <div class="card" style="text-align:center; padding:60px; background: linear-gradient(135deg, rgba(108,99,255,0.2) 0%, rgba(56,178,255,0.1) 100%); border-color:rgba(108,99,255,0.3);">
      <h2 class="section-title" style="margin-bottom:12px;">Ready to Take the Next Step?</h2>
      <p style="color:var(--text-muted); margin-bottom:28px; font-size:1rem;">
        Join thousands of professionals who found their dream jobs through JobHub.
      </p>
      <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <a href="register.php" class="btn btn-primary btn-lg">
          <i class="fas fa-user-plus"></i> Sign Up as Candidate
        </a>
        <a href="register.php?role=recruiter" class="btn btn-secondary btn-lg">
          <i class="fas fa-building"></i> Post a Job
        </a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
