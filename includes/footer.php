<?php
/**
 * JobHub - Footer Include
 * File: includes/footer.php
 * Description: Professional footer with links, social icons,
 *              and global JS script tags.
 */
?>
<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">

      <!-- Brand Column -->
      <div class="footer-brand">
        <div class="brand" style="display:flex; align-items:center; gap:10px;">
          <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="JobHub Logo" style="height:28px; width:28px; border-radius:6px; object-fit:cover;">
          <span>Job<span style="color:var(--primary);">Hub</span></span>
        </div>
        <p class="footer-desc">
          Connecting talented professionals with great companies. Find your dream job or discover top talent — all in one place.
        </p>
        <div class="social-links mt-3">
          <a href="#" class="social-link" aria-label="Facebook">   <i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-link" aria-label="Twitter">    <i class="fab fa-twitter"></i></a>
          <a href="#" class="social-link" aria-label="LinkedIn">   <i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link" aria-label="Instagram">  <i class="fab fa-instagram"></i></a>
        </div>
      </div>

      <!-- For Candidates -->
      <div class="footer-col">
        <h4>For Candidates</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/jobs/listing.php">Browse Jobs</a></li>
          <li><a href="<?= BASE_URL ?>/jobs/search.php">Search Jobs</a></li>
          <li><a href="<?= BASE_URL ?>/register.php">Create Account</a></li>
          <li><a href="<?= BASE_URL ?>/candidate/upload_resume.php">Upload Resume</a></li>
          <li><a href="<?= BASE_URL ?>/candidate/my_applications.php">My Applications</a></li>
        </ul>
      </div>

      <!-- For Recruiters -->
      <div class="footer-col">
        <h4>For Recruiters</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/register.php">Post a Job</a></li>
          <li><a href="<?= BASE_URL ?>/recruiter/dashboard.php">Recruiter Dashboard</a></li>
          <li><a href="<?= BASE_URL ?>/recruiter/company_profile.php">Company Profile</a></li>
          <li><a href="<?= BASE_URL ?>/recruiter/manage_jobs.php">Manage Listings</a></li>
        </ul>
      </div>

      <!-- Company -->
      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><a href="#">About Us</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">Contact Us</a></li>
          <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Admin Panel</a></li>
        </ul>
      </div>
    </div><!-- /footer-grid -->

    <!-- Bottom Bar -->
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> JobHub. All rights reserved. Built with ❤ for BCA Project.</span>
      <div style="display:flex; gap:16px; font-size:0.8rem;">
        <a href="#" style="color:var(--text-muted);">Privacy</a>
        <a href="#" style="color:var(--text-muted);">Terms</a>
        <a href="#" style="color:var(--text-muted);">Sitemap</a>
      </div>
    </div>
  </div>
</footer>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<!-- Font Awesome (already in head, CDN handles caching) -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>

</body>
</html>
