# 🚨 Incident Report Monitoring System (IRMS)

A **comprehensive** web-based application built for real-time tracking, reporting, and management of local incidents. This system provides a seamless interface for citizens, emergency responders, and administrators to coordinate effectively.

## 🚀 Key Features

* **Responsive UI:** Fully mobile-friendly layouts using bootstrap.
* **Citizen Reporting:** Quick incident submission with file uploads and category tagging.
* **Admin Control Center:** Centralized management of users, incident status, and categories.
* **Responder Dashboard:** Dedicated view for assigned responders to update incident progress.
* **AJAX Integration:** Dynamic data fetching for maps and status updates without page reloads.

## 🛠️ Tech Stack

* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5 (Grid & Components)
* **Backend:** PHP (Procedural/OOP)
* **Database:** MySQL (via XAMPP)
* **API/Library:** jQuery AJAX, OpenStreetMap/Google Maps (for `get_incidents_map.php`)

## 📁 Project Structure

* `ajax/` - Backend handlers for dynamic UI updates.
* `controllers/` - `ReportController.php` for core application logic.
* `models/` - `Incident.php` and `User.php` for database interactions.
* `views/` - Modular UI folders (Admin, Citizen, Responder).
* `libs/` - Core libraries and utilities
