/**
 * JobHub - Global JavaScript
 * File: assets/js/main.js
 * Description: Navigation, validation helpers, CSRF, form handling,
 *              file-upload preview, toast notifications, and utilities.
 */

'use strict';

/* ── DOM Ready ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initPasswordToggles();
  initFileDropZones();
  initFormValidation();
  initDropdowns();
  autoHideAlerts();
});

/* ──────────────────────────────────────────────────────────── */
/* NAVBAR                                                       */
/* ──────────────────────────────────────────────────────────── */
function initNavbar() {
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('open');
      mobileMenu.classList.toggle('open');
      document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
    });
  }

  // Close mobile menu when a link is clicked
  document.querySelectorAll('.mobile-menu .nav-link').forEach(link => {
    link.addEventListener('click', () => {
      hamburger?.classList.remove('open');
      mobileMenu?.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  // Navbar scroll shadow
  window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
      navbar.style.boxShadow = window.scrollY > 10
        ? '0 4px 24px rgba(0,0,0,0.4)'
        : 'none';
    }
  });
}

/* ──────────────────────────────────────────────────────────── */
/* DROPDOWNS (user menu)                                        */
/* ──────────────────────────────────────────────────────────── */
function initDropdowns() {
  document.querySelectorAll('[data-dropdown-toggle]').forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const targetId = trigger.getAttribute('data-dropdown-toggle');
      const menu = document.getElementById(targetId);
      if (!menu) return;
      const isOpen = menu.classList.contains('open');
      // close all
      document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
      if (!isOpen) menu.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
  });
}

/* ──────────────────────────────────────────────────────────── */
/* PASSWORD TOGGLE (show/hide)                                  */
/* ──────────────────────────────────────────────────────────── */
function initPasswordToggles() {
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const wrapper = btn.closest('.password-wrapper');
      const input   = wrapper.querySelector('input');
      const icon    = btn.querySelector('i');
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
  });
}

/* ──────────────────────────────────────────────────────────── */
/* VALIDATION HELPERS                                           */
/* ──────────────────────────────────────────────────────────── */
const Validate = {
  /** Required: not empty */
  required: (v) => v.trim().length > 0,

  /** Name: letters and spaces only, min 2 chars */
  name: (v) => /^[A-Za-z\s'-]{2,100}$/.test(v.trim()),

  /** Email: RFC-compatible basic */
  email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()),

  /** Phone: exactly 10 digits */
  phone: (v) => /^\d{10}$/.test(v.trim()),

  /** Password: min 8 chars, upper, lower, digit, special */
  password: (v) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^])[A-Za-z\d@$!%*#?&^]{8,}$/.test(v),

  /** URL (optional) */
  url: (v) => v === '' || /^https?:\/\/.+/.test(v.trim()),

  /** Minimum length */
  minLen: (v, n) => v.trim().length >= n,

  /** Maximum length */
  maxLen: (v, n) => v.trim().length <= n,

  /** File: extension check */
  fileExt: (file, exts) => exts.includes(file.name.split('.').pop().toLowerCase()),

  /** File: size check (bytes) */
  fileSize: (file, max) => file.size <= max,
};

/**
 * Show or clear a field's validation state
 * @param {HTMLElement} field - input element
 * @param {boolean} valid
 * @param {string} [msg] - error message to show
 */
function setFieldState(field, valid, msg = '') {
  field.classList.toggle('is-invalid', !valid);
  field.classList.toggle('is-valid', valid);
  const feedback = field.parentElement?.querySelector('.invalid-feedback')
                ?? field.closest('.form-group')?.querySelector('.invalid-feedback');
  if (feedback) {
    feedback.textContent = msg;
    feedback.classList.toggle('show', !valid);
  }
}

/* ──────────────────────────────────────────────────────────── */
/* FORM VALIDATION (auto-attach via data attributes)           */
/* ──────────────────────────────────────────────────────────── */
function initFormValidation() {
  // Live validation on blur
  document.querySelectorAll('[data-validate]').forEach(field => {
    field.addEventListener('blur', () => validateField(field));
    field.addEventListener('input', () => {
      if (field.classList.contains('is-invalid')) validateField(field);
    });
  });

  // Form submit validation
  document.querySelectorAll('form[data-validate-form]').forEach(form => {
    form.addEventListener('submit', (e) => {
      const fields = form.querySelectorAll('[data-validate]');
      let allValid = true;
      fields.forEach(f => { if (!validateField(f)) allValid = false; });
      if (!allValid) {
        e.preventDefault();
        showToast('Please fix the errors before submitting.', 'danger');
        // Scroll to first error
        const first = form.querySelector('.is-invalid');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });
}

function validateField(field) {
  const rules = field.getAttribute('data-validate').split('|');
  const val   = field.value;
  let valid    = true;
  let msg      = '';

  for (const rule of rules) {
    const [name, param] = rule.split(':');
    switch (name) {
      case 'required':
        if (!Validate.required(val)) { valid = false; msg = 'This field is required.'; }
        break;
      case 'name':
        if (val && !Validate.name(val)) { valid = false; msg = 'Only letters, spaces, hyphens, and apostrophes are allowed.'; }
        break;
      case 'email':
        if (val && !Validate.email(val)) { valid = false; msg = 'Please enter a valid email address.'; }
        break;
      case 'phone':
        if (val && !Validate.phone(val)) { valid = false; msg = 'Phone number must be exactly 10 digits.'; }
        break;
      case 'password':
        if (val && !Validate.password(val)) {
          valid = false;
          msg = 'Min 8 chars with uppercase, lowercase, number & special character.';
        }
        break;
      case 'confirm':
        const target = document.getElementById(param);
        if (target && val !== target.value) { valid = false; msg = 'Passwords do not match.'; }
        break;
      case 'minlen':
        if (val && !Validate.minLen(val, parseInt(param))) { valid = false; msg = `Minimum ${param} characters required.`; }
        break;
      case 'maxlen':
        if (val && !Validate.maxLen(val, parseInt(param))) { valid = false; msg = `Maximum ${param} characters allowed.`; }
        break;
      case 'url':
        if (val && !Validate.url(val)) { valid = false; msg = 'Enter a valid URL starting with http:// or https://'; }
        break;
    }
    if (!valid) break;
  }

  setFieldState(field, valid, msg);
  return valid;
}

/* ──────────────────────────────────────────────────────────── */
/* FILE DROP ZONES                                              */
/* ──────────────────────────────────────────────────────────── */
function initFileDropZones() {
  document.querySelectorAll('.file-drop').forEach(zone => {
    const inputId = zone.getAttribute('data-input');
    const input   = document.getElementById(inputId);
    if (!input) return;

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        handleFilePreview(input, zone);
      }
    });

    input.addEventListener('change', () => handleFilePreview(input, zone));
  });
}

function handleFilePreview(input, zone) {
  const file    = input.files[0];
  const maxSize = 2 * 1024 * 1024;           // 2 MB
  const allowed = ['pdf', 'doc', 'docx'];
  const preview = zone.querySelector('.file-preview') || createEl('p', 'file-preview');
  zone.appendChild(preview);

  if (!file) return;

  const ext = file.name.split('.').pop().toLowerCase();
  if (!allowed.includes(ext)) {
    preview.textContent = '⚠ Invalid file type. Only PDF, DOC, DOCX allowed.';
    preview.style.color = '#EF4444';
    input.value = '';
    return;
  }
  if (file.size > maxSize) {
    preview.textContent = '⚠ File is too large. Max size is 2 MB.';
    preview.style.color = '#EF4444';
    input.value = '';
    return;
  }

  preview.textContent = `✔ ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
  preview.style.color = '#10B981';
}

function createEl(tag, cls) {
  const el = document.createElement(tag);
  el.className = cls;
  return el;
}

/* ──────────────────────────────────────────────────────────── */
/* TOAST NOTIFICATIONS                                          */
/* ──────────────────────────────────────────────────────────── */
function showToast(message, type = 'info', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    Object.assign(container.style, {
      position: 'fixed', bottom: '24px', right: '24px',
      zIndex: '9999', display: 'flex', flexDirection: 'column', gap: '10px',
    });
    document.body.appendChild(container);
  }

  const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
  const colors = { success: '#10B981', danger: '#EF4444', warning: '#F59E0B', info: '#3B82F6' };

  const toast = document.createElement('div');
  Object.assign(toast.style, {
    background: '#1A1A2E', border: `1px solid ${colors[type] || colors.info}`,
    borderLeft: `4px solid ${colors[type] || colors.info}`,
    color: '#fff', padding: '12px 18px', borderRadius: '10px',
    fontSize: '0.875rem', display: 'flex', alignItems: 'center', gap: '10px',
    boxShadow: '0 8px 24px rgba(0,0,0,0.5)', maxWidth: '340px',
    animation: 'fadeUp 0.3s ease', cursor: 'pointer',
  });
  toast.innerHTML = `<i class="fas ${icons[type] || icons.info}" style="color:${colors[type]};"></i>${message}`;
  toast.addEventListener('click', () => toast.remove());
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ──────────────────────────────────────────────────────────── */
/* AUTO-HIDE PHP ALERTS                                         */
/* ──────────────────────────────────────────────────────────── */
function autoHideAlerts() {
  document.querySelectorAll('.alert[data-auto-hide]').forEach(alert => {
    const delay = parseInt(alert.getAttribute('data-auto-hide')) || 5000;
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, delay);
  });
}

/* ──────────────────────────────────────────────────────────── */
/* MODAL HELPERS                                                */
/* ──────────────────────────────────────────────────────────── */
function openModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}
function closeModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }
}
// Close modal when clicking overlay
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

/* ──────────────────────────────────────────────────────────── */
/* ROLE SELECTOR (Registration page)                           */
/* ──────────────────────────────────────────────────────────── */
function initRoleSelector() {
  const btns = document.querySelectorAll('.role-btn');
  const input = document.getElementById('roleInput');
  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (input) input.value = btn.getAttribute('data-role');
    });
  });
}
document.addEventListener('DOMContentLoaded', initRoleSelector);

/* ──────────────────────────────────────────────────────────── */
/* SEARCH PAGE — live category filter                           */
/* ──────────────────────────────────────────────────────────── */
function filterJobCards(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('.job-card[data-title]').forEach(card => {
    const title = card.getAttribute('data-title').toLowerCase();
    const loc   = (card.getAttribute('data-location') || '').toLowerCase();
    const cat   = (card.getAttribute('data-category') || '').toLowerCase();
    card.style.display = (title.includes(q) || loc.includes(q) || cat.includes(q)) ? '' : 'none';
  });
}

/* ──────────────────────────────────────────────────────────── */
/* UTILITY: Confirm dialog                                      */
/* ──────────────────────────────────────────────────────────── */
function confirmAction(message, callback) {
  if (window.confirm(message)) callback();
}

/* ──────────────────────────────────────────────────────────── */
/* UTILITY: Character counter for textareas                     */
/* ──────────────────────────────────────────────────────────── */
document.querySelectorAll('textarea[maxlength]').forEach(ta => {
  const max     = parseInt(ta.getAttribute('maxlength'));
  const counter = document.createElement('small');
  counter.className = 'form-text';
  counter.textContent = `0 / ${max} characters`;
  ta.parentNode.insertBefore(counter, ta.nextSibling);
  ta.addEventListener('input', () => {
    counter.textContent = `${ta.value.length} / ${max} characters`;
    counter.style.color = ta.value.length > max * 0.9 ? '#F59E0B' : '';
  });
});
