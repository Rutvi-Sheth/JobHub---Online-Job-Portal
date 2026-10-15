<?php
/**
 * JobHub - Company Profile (Recruiter)
 * File: recruiter/company_profile.php
 * Description: Recruiter creates/updates their company profile.
 *              Full validation + CSRF + PDO prepared statements.
 */

require_once '../config/db.php';
requireRole('recruiter');

$pageTitle = 'Company Profile – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
$errors    = [];
$success   = false;

// ── Fetch existing company ─────────────────────────────────────
$company = $pdo->prepare("SELECT * FROM companies WHERE recruiter_id = ? LIMIT 1");
$company->execute([$userId]);
$company = $company->fetch();

// ── Process POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $companyName = sanitise($_POST['company_name'] ?? '');
        $industry    = sanitise($_POST['industry']     ?? '');
        $size        = sanitise($_POST['company_size'] ?? '');
        $website     = sanitise($_POST['website']      ?? '');
        $location    = sanitise($_POST['location']     ?? '');
        $description = sanitise($_POST['description']  ?? '');
        $established = sanitise($_POST['established']  ?? '');

        // Validate
        if (empty($companyName))
            $errors[] = 'Company name is required.';
        elseif (strlen($companyName) < 2 || strlen($companyName) > 150)
            $errors[] = 'Company name must be 2-150 characters.';

        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL))
            $errors[] = 'Invalid website URL.';

        if (!empty($established) && (!is_numeric($established) || $established < 1800 || $established > (int)date('Y')))
            $errors[] = 'Enter a valid establishment year.';

        if (empty($errors)) {
            if ($company) {
                // Update
                $pdo->prepare("
                    UPDATE companies
                    SET company_name=?, industry=?, company_size=?, website=?,
                        location=?, description=?, established=?
                    WHERE recruiter_id=?
                ")->execute([$companyName, $industry, $size, $website ?: null,
                              $location, $description, $established ?: null, $userId]);
            } else {
                // Insert
                $pdo->prepare("
                    INSERT INTO companies (recruiter_id, company_name, industry, company_size, website, location, description, established)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([$userId, $companyName, $industry, $size, $website ?: null,
                              $location, $description, $established ?: null]);
            }

            $success = true;
            $company = $pdo->prepare("SELECT * FROM companies WHERE recruiter_id=? LIMIT 1");
            $company->execute([$userId]); $company = $company->fetch();
        }
    }
}

$csrf = generateCSRF();
include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <aside class="sidebar" aria-label="Recruiter navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="post_job.php"        class="sidebar-link"><i class="fas fa-plus-circle"></i> Post a Job</a>
    <a href="manage_jobs.php"     class="sidebar-link"><i class="fas fa-list"></i> Manage Jobs</a>
    <a href="applicants.php"      class="sidebar-link"><i class="fas fa-users"></i> Applicants</a>
    <a href="company_profile.php" class="sidebar-link active"><i class="fas fa-building"></i> Company Profile</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">Company Profile</h1>
      <p style="color:var(--text-muted);">Complete your company profile to attract the best candidates.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-hide="4000"><i class="fas fa-check-circle"></i> Company profile saved!</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin:0;padding-left:16px;list-style:disc;">
          <?php foreach ($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($company && $company['is_verified']): ?>
      <div class="alert alert-success mb-3"><i class="fas fa-shield-check"></i> Your company is verified by admin.</div>
    <?php endif; ?>

    <form action="company_profile.php" method="POST" data-validate-form novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <div class="card mb-3">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
          <i class="fas fa-building" style="color:var(--primary);margin-right:8px;"></i>Basic Information
        </h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="company_name">Company Name <span class="req">*</span></label>
            <input type="text" id="company_name" name="company_name" class="form-control"
                   value="<?= e($company['company_name'] ?? '') ?>"
                   data-validate="required|minlen:2" maxlength="150"
                   placeholder="e.g. TechCorp Pvt. Ltd.">
            <div class="invalid-feedback"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="industry">Industry</label>
            <select id="industry" name="industry" class="form-control">
              <option value="">Select Industry</option>
              <?php foreach (['Information Technology','Finance & Accounting','Marketing & Sales','Human Resources','Healthcare','Education','Engineering','Design & Creative','Manufacturing','Retail','E-commerce','Logistics','Legal','Media & Entertainment','Real Estate'] as $ind): ?>
                <option value="<?= $ind ?>" <?= ($company['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= $ind ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="company_size">Company Size</label>
            <select id="company_size" name="company_size" class="form-control">
              <option value="">Select Size</option>
              <?php foreach (['1-10','11-50','51-200','201-500','501-1000','1000+'] as $sz): ?>
                <option value="<?= $sz ?>" <?= ($company['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?> employees</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="website">Company Website</label>
            <input type="url" id="website" name="website" class="form-control"
                   value="<?= e($company['website'] ?? '') ?>"
                   placeholder="https://company.com" data-validate="url">
            <div class="invalid-feedback"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="established">Year Established</label>
            <input type="number" id="established" name="established" class="form-control"
                   value="<?= e($company['established'] ?? '') ?>"
                   min="1800" max="<?= date('Y') ?>" placeholder="e.g. 2015">
          </div>

          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="location">Location / Headquarters</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?= e($company['location'] ?? '') ?>"
                   placeholder="e.g. Bangalore, India">
          </div>

          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="description">Company Description</label>
            <textarea id="description" name="description" class="form-control" rows="5"
                      maxlength="2000"
                      placeholder="Tell candidates about your company, culture, mission, and what makes you unique..."><?= e($company['description'] ?? '') ?></textarea>
            <div class="form-text">Max 2000 characters.</div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> <?= $company ? 'Update Company' : 'Create Company Profile' ?>
        </button>
      </div>
    </form>
  </main>
</div>

<style>
@media(max-width:768px){ form [style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important;} }
</style>
<?php include '../includes/footer.php'; ?>
