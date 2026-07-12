# WebCraft Website Builder

WebCraft is a full, commercial-grade, open-source visual website builder developed with highly modular, secure, low-code paradigms. Compatible with **PHP 7.4 to 8.3+** and **MySQL/MariaDB**, WebCraft is engineered for performance, security, and developer extensibility.

It features a robust authentication gate, dynamic drag-and-drop workspace, properties customizer, live low-code HTML compilation, instant caching delivery wrappers, and a feature-rich admin dashboard tracking form submissions, chatbot interaction, and SMTP notification dispatch simulations.

---

## 🚀 Features

- **Modern Visual Builder Engine:**
  - Dynamic drag-and-drop live-editing workspace.
  - Sidebar style manager (paddings, margins, flex spacing, custom typography).
  - Real-time custom raw HTML block editor.
- **Pre-Built Themes & Templates:**
  - One-click template onboarding for SaaS Product Landers, Creative Portfolios, and Corporate Pages.
- **Dynamic Live Interactive Components:**
  - **Floating AI Chatbot Simulation:** Fully interactive floating chat assistant with customizable conversational patterns and responsive sub-second replies.
  - **Contact & Inquiry Dynamic Forms:** Secure client-side form submissions posting AJAX payloads to verified PHP API handlers.
- **Production Static HTML Compiler:**
  - Compiles pages to fast static HTML caches.
  - Direct rendering via clean slug delivery wrappers with complete assets injection.
- **Comprehensive Administration Panel:**
  - Statistical insights and diagnostics.
  - Multi-tab controls: Website instances, theme templates, secure AJAX dynamic submissions, SMTP delivery simulation logs, user accounts table, and real-time server health metrics.
- **High-Security Standards:**
  - Strong password hashing with Bcrypt.
  - Protection against Session Fixation using automated session ID regeneration.
  - Comprehensive Cross-Site Request Forgery (CSRF) protection tokens on all POST/mutation routes.
  - Robust XSS (Cross-Site Scripting) output sanitization filters.
  - Session-based IP login rate-limiting defenses against brute-force attacks.

---

## 🛠️ Tech Stack

- **Backend:** PHP 7.4+ (fully tested through PHP 8.3.6), PDO (MySQL/MariaDB).
- **Frontend:** HTML5, CSS3, ES6 JavaScript, Tailwind CSS CSS framework (CDN-backed), FontAwesome 6 icons.
- **Database:** MySQL 5.7+ / MariaDB 10.4+.
- **Testing:** Playwright automation testing suite (Python Sync API).

---

## 📦 Installation & Setup

### Prerequisites

- A web server running PHP 7.4+ (such as Apache, Nginx, or PHP Built-in Server).
- A MySQL or MariaDB instance.
- The PHP `zip` extension enabled (needed for the ZIP export feature).

### Step-by-Step Installation

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/yourusername/webcraft-site-builder.git
   cd webcraft-site-builder
   ```

2. **Initialize the Database Schema:**
   - Log into your MySQL/MariaDB database:
     ```bash
     mariadb -u root -p
     ```
   - Run the database initialization and import the pre-configured `schema.sql`:
     ```sql
     CREATE DATABASE site_builder;
     CREATE USER 'builder_user'@'localhost' IDENTIFIED BY 'builder_pass';
     GRANT ALL PRIVILEGES ON site_builder.* TO 'builder_user'@'localhost';
     FLUSH PRIVILEGES;
     USE site_builder;
     SOURCE schema.sql;
     ```

3. **Configure Environment Variables:**
   - Create a `.env` file in the root directory to customize your database and environment settings.
     ```ini
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=site_builder
     DB_USER=builder_user
     DB_PASS=builder_pass
     APP_ENV=production
     ```
   - Note: If no `.env` file is present, `config.php` will safely fallback to the pre-configured system default credentials (`builder_user`/`builder_pass`).

4. **Run the Installer / Seeding Script:**
   - Open your browser or run via terminal the `install.php` setup utility to automatically create the database tables (if they don't exist) and seed default developer templates and the initial secure administrative user:
     ```bash
     php install.php
     ```
   - This creates the default admin user:
     - **Username:** `admin`
     - **Password:** `admin123` *(Be sure to change this upon first login!)*

5. **Start the PHP Server:**
   - Start the built-in PHP development server in the repository root directory:
     ```bash
     php -S 127.0.0.1:8000
     ```
   - Open `http://127.0.0.1:8000/index.php` in your favorite web browser to explore.

---

## 📁 Repository Structure

```
.
├── admin.php             # Comprehensive statistical and tracking dashboard
├── api.php               # High-security visual builder REST API endpoints & ZIP exporter
├── auth.php              # Secure registration, rate-limited login, and logout controller
├── builder.php           # Visual low-code visual drag-and-drop workspace
├── config.php            # Modular environment, dynamic .env loader, CSRF & XSS filters
├── index.php             # Core landing portal and secure auth entry point
├── install.php           # Automated installer and DB schema initializer
├── render.php            # Static HTML delivery wrapper with client-side script injection
├── schema.sql            # MariaDB database structure schema
├── submit_form.php       # Secure receiver for contact form dynamic submissions
├── verify_builder.py     # Playwright sync verification and end-to-end automation test suite
├── assets/
│   └── js/
│       ├── builder.js    # Visual drag-and-drop workspace controller
│       └── components.js # Interactive client-side Chatbot and AJAX form submit handlers
```

---

## 🧪 Testing & Verification

We provide an automated, end-to-end user journey test suite written in **Playwright (Python)**. It performs:
1. Automated registration of a new developer account.
2. Login validation.
3. Choosing a pre-configured SaaS template.
4. Editing heading properties in the visual workspace canvas.
5. Saving the draft and compiling the static HTML output.
6. Verifying the compiled production rendering wrapper.
7. Simulating dynamic floating AI Chatbot conversations.
8. Injecting and posting secure public contact inquiries via dynamic forms.
9. Verifying that form records and SMTP logs populate inside the admin dashboard.

To run the verification suite:

```bash
# Install Python Playwright dependencies
pip3 install playwright
playwright install chromium

# Start your PHP server on port 8000
php -S 127.0.0.1:8000 &

# Run the automation script
python3 verify_builder.py
```

Screenshots and videos of the flow will be captured in the `/home/jules/verification/` directory automatically.

---

## 🔒 Security Architecture

1. **CSRF Prevention:** All state-modifying POST endpoints require verification of standard cryptographic tokens generated per-session using `random_bytes()`.
2. **XSS Protection:** Rigorous parsing and sanitization using custom `sanitize_output` filters and secure script stripping during template saving.
3. **Password Security:** Safe hashing via standard `password_hash()` utilizing the bcrypt algorithm.
4. **Login Protection:** Session and IP tracking-based brute force rate-limiter built into the login router.
5. **Session Security:** Session cookie parameters are hardened with `httponly` and conditional `secure` flags, with mandatory session ID regeneration upon login.

---

## 📄 License

This project is open-source software licensed under the [MIT License](LICENSE).
