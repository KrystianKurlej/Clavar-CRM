# Clavar CRM

A simple, self-hosted CRM (Customer Relationship Management) system for project management and time tracking. The system is designed with simplicity, ease of installation, and minimal technical requirements in mind.


## Features

### Project Management
- Create, edit, and delete projects
- Archive projects
- Real-time work time tracking (start/stop timer)
- Manual base time setting for projects
- Automatic work time summation

### Reports
- Generate time reports for selected projects
- Cost calculation based on hourly rate
- Export data in JSON format
- History of all reports

### User System
- Multi-user support - each user has their own SQLite database
- Secure authentication with password hashing (bcrypt)
- CSRF protection for all forms
- Sessions with configurable security parameters

## Technical
- Lightweight, serverless architecture (SQLite)
- Responsive user interface (Bootstrap 5)
- Latte template system
- AJAX for dynamic operations
- REST API for external integrations
- Ready to run in a container

## Code Structure

- **PSR-4 lite**: Classes are loaded manually in [public/index.php](public/index.php#L16-L21)
- **MVC pattern**: Controllers, repositories, views
- **Repository pattern**: Data access through dedicated classes
- **Template engine**: Latte 3.x for views