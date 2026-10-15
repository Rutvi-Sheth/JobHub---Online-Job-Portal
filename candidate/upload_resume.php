<?php
/**
 * JobHub - Resume Upload
 * File: candidate/upload_resume.php
 * Description: Secure resume upload with:
 *              - Type validation (PDF, DOC, DOCX)
 *              - Size validation (max 2 MB)
 *              - File rename (hash-based) to prevent path traversal
 *              - CSRF protection
 *              - Stored file path in DB via PDO
 */

require_once '../config/db.php';
requireRole('candidate');

$pageTitle = 'Upload Resume – JobHub';
$userId    = (int)$_SESSION['user_id'];
$pdo       = getDB();
$errors    = [];
$success   = false;

// ── Fetch existing resumes ──
$stmt = $pdo->prepare("SELECT * FROM resumes WHERE candidate_id = ? ORDER BY is_primary DESC, uploaded_at DESC");
$stmt->execute([$userId]);
$resumes = $stmt->fetchAll();

// ── Process Upload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) 
    {
        $errors[] = 'Invalid CSRF token.';
    } 
    else 
    {

        // Check file was actually submitted
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) 
        {
            $errors[] = 'Please select a file to upload.';
        } 
        elseif ($_FILES['resume']['error'] !== UPLOAD_ERR_OK)
        {
            $errors[] = 'File upload error. Code: ' . (int)$_FILES['resume']['error'];
        }
        else
        {
            $file     = $_FILES['resume'];
            $origName = basename($file['name']);
            $tmpPath  = $file['tmp_name'];
            $fileSize = $file['size'];

            // 1. Size check (max 2 MB)
            if ($fileSize > MAX_FILE_SIZE)
            {
                $errors[] = 'File size exceeds the 2 MB limit. Please upload a smaller file.';
            }

            // 2. Extension check
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT, true))
            {
                $errors[] = 'Invalid file type. Only PDF, DOC, and DOCX files are allowed.';
            }

            // 3. MIME type check using finfo (prevents bypass via renamed extensions)
            if (empty($errors))
            {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);

                if (!in_array($mimeType, ALLOWED_TYPES, true)) 
                {
                    $errors[] = 'Invalid file content. Upload a genuine PDF, DOC, or DOCX.';
                }
            }

            // 4. Move file
            if (empty($errors)) 
            {
                // Create unique filename to avoid conflicts and path traversal
                $newFileName = 'resume_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destPath    = UPLOAD_PATH . $newFileName;

                if (!is_dir(UPLOAD_PATH)) 
                {
                    mkdir(UPLOAD_PATH, 0755, true);
                }

                if (!move_uploaded_file($tmpPath, $destPath)) 
                {
                    $errors[] = 'Failed to save the file. Please try again.';
                }
                else
                {
                    // Set all existing as non-primary
                    $pdo->prepare("UPDATE resumes SET is_primary=0 WHERE candidate_id=?")
                        ->execute([$userId]);

                    // Insert new resume record
                    $pdo->prepare("INSERT INTO resumes (candidate_id, file_name, file_path, file_size, file_type, is_primary)
                                  VALUES (?, ?, ?, ?, ?, 1)")->execute([$userId, $origName, 'uploads/resumes/' . $newFileName, $fileSize, $ext]);

                    $success = true;

                    // Refresh list
                    $stmt = $pdo->prepare("SELECT * FROM resumes WHERE candidate_id=? ORDER BY is_primary DESC, uploaded_at DESC");
                    $stmt->execute([$userId]);
                    $resumes = $stmt->fetchAll();
                }
            }
        }
    }
}

// Handle DELETE resume
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) 
  {
    if (validateCSRF($_GET['csrf'] ?? '')) 
      {
        $rid = (int)$_GET['delete'];
        $row = $pdo->prepare("SELECT file_path FROM resumes WHERE id=? AND candidate_id=?");
        $row->execute([$rid, $userId]);
        $row = $row->fetch();

        if ($row) 
        {
            @unlink('../' . $row['file_path']);
            $pdo->prepare("DELETE FROM resumes WHERE id=? AND candidate_id=?")->execute([$rid, $userId]);
            setFlash('success', 'Resume deleted.');
            header('Location: upload_resume.php'); exit;
        }
      }
  }

// Set primary resume
if (isset($_GET['set_primary']) && is_numeric($_GET['set_primary'])) 
{
    $rid = (int)$_GET['set_primary'];
    $pdo->prepare("UPDATE resumes SET is_primary=0 WHERE candidate_id=?")->execute([$userId]);
    $pdo->prepare("UPDATE resumes SET is_primary=1 WHERE id=? AND candidate_id=?")->execute([$rid,$userId]);
    setFlash('success', 'Primary resume updated.');
    header('Location: upload_resume.php'); exit;
}

$csrf = generateCSRF();
include '../includes/header.php';
?>

<div class="dashboard-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" aria-label="Candidate navigation">
    <a href="dashboard.php"       class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="../jobs/listing.php" class="sidebar-link"><i class="fas fa-search"></i> Browse Jobs</a>
    <a href="my_applications.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Applications</a>
    <a href="profile.php"         class="sidebar-link"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="upload_resume.php"   class="sidebar-link active"><i class="fas fa-file-upload"></i> Resume</a>
    <a href="../logout.php"       class="sidebar-link" style="color:var(--danger);margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <main class="main-content" id="main-content">
    <div style="margin-bottom:24px;">
      <h1 style="font-family:var(--font-heading);font-size:1.6rem;font-weight:800;">
        <i class="fas fa-file-upload" style="color:var(--primary);"></i> Upload Resume
      </h1>
      <p style="color:var(--text-muted);">Upload your resume to apply for jobs. Accepted: PDF, DOC, DOCX (max 2 MB).</p>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-hide="5000"><i class="fas fa-check-circle"></i> Resume uploaded successfully!</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin:0;padding-left:16px;list-style:disc;">
          <?php foreach ($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;" class="resume-grid">

      <!-- Upload Form -->
      <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px;">Upload New Resume</h2>

        <form action="upload_resume.php" method="POST" enctype="multipart/form-data" id="resumeForm">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <!-- Hidden PHP max post size -->
          <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_FILE_SIZE ?>">

          <!-- Drag & Drop Zone -->
          <div class="file-drop" data-input="resumeFile" id="dropZone" role="button" tabindex="0"
               aria-label="Drop resume here or click to browse">
            <div class="file-drop-icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></div>
            <div class="file-drop-text">Drag &amp; drop your resume here</div>
            <div class="file-drop-sub">or click to browse files</div>
            <div class="file-drop-sub" style="margin-top:8px;">PDF, DOC, DOCX · Max 2 MB</div>
          </div>

          <input type="file"
                 id="resumeFile"
                 name="resume"
                 accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                 class="hidden"
                 required>

          <div class="form-text mt-2">
            <i class="fas fa-shield-alt" style="color:var(--success);"></i>
            Your resume is stored securely. Only recruiters you apply to can view it.
          </div>

          <button type="submit" class="btn btn-primary btn-block mt-3" id="uploadBtn">
            <i class="fas fa-upload"></i> Upload Resume
          </button>
        </form>

        <!-- Guidelines -->
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
          <h3 style="font-size:0.82rem;font-weight:700;margin-bottom:10px;color:var(--text-secondary);">📋 TIPS FOR A GREAT RESUME</h3>
          <ul style="font-size:0.78rem;color:var(--text-muted);display:flex;flex-direction:column;gap:6px;">
            <li>✅ Use a clean, ATS-friendly format</li>
            <li>✅ Include your latest contact information</li>
            <li>✅ Highlight relevant skills and achievements</li>
            <li>✅ Keep it to 1-2 pages</li>
            <li>✅ Proofread carefully before uploading</li>
          </ul>
        </div>
      </div>

      <!-- Existing Resumes -->
      <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px;">My Resumes</h2>

        <?php if ($resumes): ?>
          <div style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($resumes as $res): ?>
              <div style="padding:14px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:var(--radius-md);<?= $res['is_primary'] ? 'border-color:var(--primary);background:rgba(108,99,255,0.06);' : '' ?>">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                  <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(239,68,68,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <i class="fas fa-file-<?= $res['file_type'] === 'pdf' ? 'pdf' : 'word' ?>" style="color:<?= $res['file_type'] === 'pdf' ? 'var(--danger)' : 'var(--info)' ?>;font-size:1.1rem;"></i>
                    </div>
                    <div style="min-width:0;">
                      <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($res['file_name']) ?></div>
                      <div style="font-size:0.72rem;color:var(--text-muted);">
                        <?= strtoupper($res['file_type']) ?> · <?= round($res['file_size']/1024, 1) ?> KB
                        · <?= date('d M Y', strtotime($res['uploaded_at'])) ?>
                      </div>
                    </div>
                  </div>
                  <?php if ($res['is_primary']): ?>
                    <span class="badge badge-success">Primary</span>
                  <?php endif; ?>
                </div>

                <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                  <?php if (!$res['is_primary']): ?>
                    <a href="upload_resume.php?set_primary=<?= $res['id'] ?>&csrf=<?= e($csrf) ?>"
                       class="btn btn-secondary btn-sm">
                      <i class="fas fa-star"></i> Set as Primary
                    </a>
                  <?php endif; ?>
                  <a href="../<?= e($res['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fas fa-eye"></i> View
                  </a>
                  <a href="upload_resume.php?delete=<?= $res['id'] ?>&csrf=<?= e($csrf) ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete this resume?')">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
        <?php else: ?>
          <div class="text-center" style="padding:40px 0;color:var(--text-muted);">
            <i class="fas fa-file-upload" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
            <p>No resumes uploaded yet.<br>Upload your first resume to start applying!</p>
          </div>
        <?php endif; ?>
      </div>
    </div><!-- /resume-grid -->
  </main>
</div>

<style>
@media(max-width:768px)
{ 
  .resume-grid{grid-template-columns:1fr!important;} 
}
</style>

<?php include '../includes/footer.php'; ?>
