<div align="center">

# 💼 JobHub — Online Job Portal

### *Find Opportunities. Build Your Future.*

![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-InnoDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![CSS3](https://img.shields.io/badge/CSS3-Responsive-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![Apache](https://img.shields.io/badge/Server-WAMP%2FApache-D22128?style=for-the-badge&logo=apache&logoColor=white)

A **secure**, **role-based**, full-stack Online Job Portal System  
built from scratch as a **BCA 6th Semester Final Year Project**.

</div>

---

## 🚀 About The Project

**JobHub** is a complete web-based recruitment platform that bridges the gap  
between **Job Seekers (Candidates)** and **Employers (Recruiters)** through  
a centralized, smart, and secure online system.

---

## 📸 Screenshots

> Home Page · Job Listings · Recruiter Panel · Candidate Dashboard

*(Add your screenshots in `/screenshots` folder)*

---

## ✨ Key Features

### 👤 Candidate Module
- Register, login & build professional profile
- Search & filter jobs by **category, location & job type**
- Apply with **resume (PDF) upload** & cover letter
- Real-time application status tracking:
  `Pending → Reviewed → Shortlisted → Hired / Rejected`

### 🏢 Recruiter Module
- Register & set up **verified company profile**
- Post, edit & manage job listings with deadline
- View & evaluate all applicants per job
- **Auto Vacancy Management:**
  - Mark candidate as `Hired` → vacancies auto-decrement
  - Vacancies reach `0` → job auto-closes ✅
  - Un-hire → vacancies restore, job re-activates

### 🛡️ Admin Module
- Verify companies & manage all users
- Monitor all jobs & applications platform-wide
- Centralized system control & oversight

---

## 🔐 Security Features

| Security Layer | Implementation |
|:--------------|:--------------|
| SQL Injection | PDO Prepared Statements (all queries) |
| CSRF Attack | Token validation on every form submission |
| XSS Attack | `htmlspecialchars()` via custom `e()` helper |
| Passwords | `password_hash()` with `PASSWORD_BCRYPT` |
| Auth Guard | `requireRole()` on every protected page |
| Job Expiry | Deadline enforcement — server-side + UI |
| Duplicate Apply | Server-side duplicate application check |

---

## 🛠️ Technology Stack

```
Frontend   →  HTML5, CSS3 (Flexbox · Grid · CSS Variables), JavaScript
Backend    →  PHP 8.1 (OOP · PDO Singleton Architecture)
Database   →  MySQL (InnoDB · 3NF Normalized · Foreign Key Cascade)
Security   →  PDO · CSRF Tokens · Bcrypt · XSS Sanitization
Server     →  WAMP / Apache (Local Development)
```

---

## 🗄️ Database Schema

**Database Name:** `jobhub_db`  
**Total Tables:** 7

```
users
  ├── candidate_profiles  (1:1 → users)
  ├── resumes             (1:many → users)
  ├── companies           (1:1 → recruiter user)
  │     └── jobs          (1:many → companies)
  │           └── applications (many:many → users ↔ jobs)
  └── job_categories      (reference table)
```

---

## ⚙️ Local Setup & Installation

### Prerequisites
- WAMP Server (PHP 8.1, MySQL, Apache)
- Browser (Chrome recommended)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_USERNAME/jobhub-online-job-portal.git

# 2. Copy project to WAMP www folder
C:\wamp64\www\JobPortal_Project\
```

```sql
-- 3. Import Database
-- Open: http://localhost/phpmyadmin
-- Create Database: jobhub_db
-- Import file: sql/jobhub.sql
```

```php
// 4. Check DB config in config/db.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobhub_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

```
# 5. Open in browser
http://localhost/JobPortal_Project/
```

---

## 📁 Project Structure

```
JobPortal_Project/
│
├── admin/                  # Admin panel pages
│   ├── dashboard.php
│   ├── manage_users.php
│   ├── manage_jobs.php
│   └── manage_companies.php
│
├── candidate/              # Candidate portal
│   ├── dashboard.php
│   ├── my_applications.php
│   ├── profile.php
│   └── upload_resume.php
│
├── recruiter/              # Recruiter portal
│   ├── dashboard.php
│   ├── post_job.php
│   ├── manage_jobs.php
│   ├── applicants.php
│   └── company_profile.php
│
├── jobs/                   # Public job pages
│   ├── listing.php
│   ├── detail.php
│   ├── search.php
│   └── apply.php
│
├── includes/               # Shared components
│   ├── header.php
│   └── footer.php
│
├── config/
│   └── db.php              # DB connection + helpers + CSRF
│
├── assets/
│   ├── css/style.css       # All styles + CSS custom properties
│   └── js/main.js          # Frontend scripts
│
├── uploads/
│   ├── resumes/            # Candidate PDF resumes
│   └── logos/              # Company logos
│
├── sql/
│   └── jobhub.sql          # Complete DB schema + sample data
│
├── index.php               # Home / Landing page
├── login.php               # Login page
├── register.php            # Registration page
├── logout.php              # Logout handler
├── forgot_password.php     # Forgot password
├── verify_otp.php          # OTP verification
├── reset_password.php      # Password reset
└── README.md
```

---

## 🔮 Future Scope (Phase-2)

- [ ] AI-based Resume Matching & Job Recommendation System
- [ ] Save / Bookmark Jobs feature
- [ ] Real-time Email & SMS Notifications
- [ ] In-app Chat between Recruiter & Candidate
- [ ] Payment Gateway for Featured Job Listings
- [ ] Mobile Application (Flutter / React Native)

---

## 📊 Platform Stats (Sample Data)

```
72+ Registered Users
52+ Active Job Listings
25+ Verified Companies
64+ Applications Submitted
```

---

<div align="center">

**Built with ❤️ as BCA Final Year Capstone Project**

*JobHub — Find Opportunities. Build Your Future.*

⭐ **Star this repo if you found it helpful!**

</div>