# Faculty Activity Portal (XAMPP Setup Guide)

A modern web application for faculty members to assign, manage, track, and evaluate student activities (**Quiz**, **Poster Making**, **PPT Presentation**, **Case Study**, **Group Discussion**, and **Mini Projects**).

---

## Features
- **Main Dashboard (`index.php`)**: Quick stats, active assignment filters, and modal to assign new activities.
- **Dedicated Activity Pages**:
  - 📝 `quiz.php` - MCQ Question bank builder & quiz attempt scorer.
  - 🎨 `poster_making.php` - Image resolution guidelines, submission gallery & rubric evaluator.
  - 📊 `ppt.php` - Presentation deck upload tracker, slot schedule & defense viva grading.
  - 🔍 `case_study.php` - Case document briefing, prompt questions & report evaluation.
  - 💬 `gd.php` - Automatic/manual group allocation, time slots & live score sheet.
  - 🚀 `mini_project.php` - Milestone breakdown (Abstract -> Code -> Viva Demo) & team repository tracker.
- **Dual Mode**: Runs with MySQL/PDO database or with instant Session Fallback mode!

---

## How to Run on XAMPP

### Option 1: Running in XAMPP `htdocs` (Recommended)
1. Copy the `faculty-activity-portal` directory to your XAMPP `htdocs` folder:
   `C:\xampp\htdocs\faculty-activity-portal`
2. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open your browser and go to `http://localhost/phpmyadmin`.
4. Create a new database named `faculty_activity_db`.
5. Click **Import**, choose the file `schema.sql` from this folder, and click **Go**.
6. Access the application in your browser:
   👉 **`http://localhost/faculty-activity-portal`**

### Option 2: Quick Preview via PHP Built-in Server
You can also run it directly using PHP CLI without touching XAMPP:
```bash
cd C:\Users\prati\.gemini\antigravity\scratch\faculty-activity-portal
C:\xampp\php\php.exe -S localhost:8000
```
Then open: 👉 **`http://localhost:8000`**
