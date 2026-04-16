# 🚨 QC-ALERTO: Incident Report & Monitoring System (IRMS)

[![PHP](https://img.shields.io/badge/PHP-8.2-777bb4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1.svg?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![Leaflet](https://img.shields.io/badge/Leaflet-Maps-199903.svg?style=flat-square&logo=leaflet)](https://leafletjs.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

A **comprehensive, secure, and real-time** incident management ecosystem designed specifically for **Quezon City**. QC-ALERTO bridges the gap between citizens and emergency responders, providing a state-of-the-art platform for reporting, tracking, and resolving local incidents.

---

## 🚀 Key Modules & Features

### 👤 Citizen & Public Portal
*   **Anonymous Reporting:** Secure incident submission for whistleblowers with unique tracking numbers.
*   **Authenticated Citizen Dashboard:** Personal history of reports, real-time status tracking, and satisfaction ratings.
*   **Evidence Hub:** Multipart support for **High-Resolution Photos** and **MP4 Videos** with inline playback.
*   **PWA Ready:** Installable on Android/iOS with offline caching support.

### 🛡️ Admin & Responder Control Center
*   **Intelligent Dashboard:** Live geographic visualization of active incidents across Quezon City.
*   **SLA Monitoring:** Automated escalation and deadline tracking based on incident severity.
*   **Resource Allocation:** Direct assignment of units (PNP, BFP, Rescue) to specific verified reports.
*   **PDF Generation:** Instant professional report exports for official documentation.

### 📍 Advanced Mapping & Geofencing
*   **QC-Only Boundary Enforcement:** Strictly validated geographic reporting using **GeoJSON & Ray-Casting** algorithms.
*   **Interactive Maps:** Real-time location pinning with Leaflet.js and optimized CDN asset management.

---

## 🔒 Security & Moderation
*   **Anti-Troll System (Ban Hammer):** Permanent IP Banning and account suspension for malicious users.
*   **Evidence Auto-Purge:** Automatic deletion of media files from banned users to save server bandwidth and storage.
*   **Brute Force Protection:** Login attempt throttling and automated account lockouts.
*   **Data Integrity:** Full PDO Prepared Statements, CSRF tokens, and secure password hashing (BCrypt).

---

## 🛠️ Technical Stack
*   **Backend:** PHP 8.x (Logic), MySQL (Persistence)
*   **Frontend:** HTML5, Modern Vanilla CSS (Dark Slate Theme), JavaScript (ES6+)
*   **APIs & Libraries:**
    *   **Leaflet.js:** Mapping & Geolocation.
    *   **Dompdf:** Professional PDF Exporting.
    *   **PHPMailer:** Notification Dispatching.
    *   **PWA:** Service Workers & Web Manifest.

---

## 👨‍💻 Developer
**Ronald E. Llamo (SynTuxz)**  
*Fullstack Web Developer | Capstone Project Creator*

---

## 📄 Documentation
Official System Manual and Future Roadmap are available as PDF exports within the root directory:
- [System Documentation](QC-ALERTO_Documentation.pdf)
- [Future Roadmap](QC-ALERTO_Future_Roadmap.pdf)

---
*Developed with ❤️ for Quezon City.*
