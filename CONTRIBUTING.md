# Contributing to WebCraft Website Builder

Thank you for your interest in contributing to WebCraft! We are building a robust, open-source, secure, visual website builder, and your contributions make this platform better for everyone.

Below are guidelines and standards to help you get started with contributing.

---

## 🛠️ Code of Conduct

By participating in this project, you agree to keep the community welcoming, constructive, and highly respectful. Be helpful, open to constructive feedback, and prioritize safe coding practices.

## 🚀 How to Contribute

### 1. Reporting Bugs & Enhancements
- Search existing issues to verify the bug has not been reported.
- Open a new issue with a descriptive title and follow our template:
  - **Prerequisites:** OS, PHP Version, Browser version.
  - **Steps to reproduce:** Detail sequential steps.
  - **Expected vs. Actual results:** What did you expect to happen versus what did?
  - **Screenshots / Video recordings:** Visual proof of the issue.

### 2. Suggesting New Features
- We love new ideas! Explain the feature, why it is beneficial for commercial users, and how you propose implementing it.

### 3. Pull Requests (PRs)
- Fork the repository.
- Create a feature branch with a descriptive name (e.g. `feat/add-undo-redo` or `fix/session-timeout`).
- Implement changes following our code standards.
- Write or update tests in `verify_builder.py` or unit test suites.
- Verify that E2E Playwright verification script passes without failures.
- Submit a PR with a comprehensive description of the changes.

---

## 💻 Coding Standards

To maintain a secure and clean codebase, please adhere to these parameters:

### PHP Coding Guidelines
- **Compatible Syntax:** Maintain backward compatibility with **PHP 7.4+** while taking advantage of modern paradigms (like class constants and PDO exceptions).
- **Security First:**
  - **Prepared Statements:** Never concatenate variables inside SQL queries. Use PDO placeholders `?` or named parameters.
  - **Output Sanitization:** Always sanitize user output on dynamic pages with `sanitize_output()` (htmlspecialchars) to eliminate XSS.
  - **CSRF Tokens:** All state-modifying requests (`POST`/`PUT`/`DELETE`) must require a validated `csrf_token`.
- **Formating:** Follow standard code practices (PSR-12 equivalent). Maintain readable nesting and descriptive function naming.

### Frontend Javascript
- Keep visual builder scripts modular and clear.
- Document function signatures and maintain sub-second response times for interactions.
- Avoid inline scripts; reference external script wrappers (like `assets/js/components.js`) instead.

### Styling
- Use responsive, clean Tailwind utility classes.
- Ensure all color designs support high readability contrast.
