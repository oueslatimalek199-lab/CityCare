# CityCare 🏛️

<div align="center">

![CityCare Logo](LOGO.png)

**A comprehensive web-based platform for managing public services and citizen complaints**

[![License: LGPL v2.1](https://img.shields.io/badge/License-LGPL%20v2.1-blue.svg)](LICENSE)
![Language: PHP](https://img.shields.io/badge/Language-PHP-777BB4?logo=php)
![Status: Active](https://img.shields.io/badge/Status-Active-brightgreen)

</div>

---

## 📋 Overview

**CityCare** is a citizen engagement platform designed to bridge the gap between municipalities and residents. It enables citizens to submit complaints and service requests while providing municipal administrators and field agents with tools to efficiently manage, track, and resolve public service issues.

The platform supports multiple user roles with role-based access control, email notifications, message communication, and comprehensive analytics.

---

## ✨ Key Features

### 👥 Multi-Role User System
- **Citizens**: Submit complaints and service requests
- **Agents**: Handle and fulfill requests in the field
- **Administrators**: Manage users, agents, services, and system-wide operations
- **Public Access**: View public complaints and available services

### 🛠️ Service Management
- Create and categorize public services
- Submit and track service requests
- Update service status in real-time
- View service history and details

### 📢 Complaint Management
- File complaints about municipal services
- Attach descriptions and details
- Track complaint status from submission to resolution
- Public visibility of complaints for transparency
- Comments and discussions on complaints
- Assign complaints to agents for handling

### 📧 Communication System
- Private messaging between users
- Email notifications for important events
- Automated email alerts for status updates
- SMTP UTF-8 support for international characters

### 📊 Dashboard & Analytics
- User-friendly dashboards for all roles
- Real-time status tracking
- Performance metrics and statistics

### 🔐 Security Features
- Session management and authentication
- Password reset functionality
- OAuth token support
- Security commitments and guidelines

---

## 🏗️ Technical Stack

- **Backend**: PHP
- **Database**: MySQL/SQL (with comprehensive schema)
- **Frontend**: HTML5, CSS3
- **Email**: PHPMailer integration for SMTP
- **Version Control**: Git

---

## 📁 Project Structure

```
CityCare/
├── Classes_ServiceManager.php          # Service management logic
├── Classes_ServiceRequestManager.php    # Service request operations
├── CommentaireManager.php               # Comment management
├── EmailNotifications.php               # Email handling system
├── MessageManager.php                   # Private messaging system
├── servicerequestmanager.php            # Service request utilities
│
├── Dashboard Pages
├── dashboard.php                        # Main dashboard
├── dashboard_header.php                 # Dashboard header component
│
├── User Management
├── login.php                            # Login page
├── inscription.php                      # User registration
├── mon_profil.php                       # User profile
├── profil.php                           # Profile management
├── reset_password.php                   # Password recovery
│
├── Admin Pages
├── admin.php                            # Admin dashboard
├── admin_service_requests.php           # Service requests management
├── gestion_agents.php                   # Agent management
├── gestion_commentaires.php             # Comment moderation
├── gestion_reclamations.php             # Complaint management
├── gestion_services.php                 # Service administration
│
├── Agent Pages
├── agent.php                            # Agent dashboard
├── agent_services.php                   # Agent's assigned services
│
├── Citizen Pages
├── citoyen.php                          # Citizen dashboard
├── citoyen_services.php                 # Citizen's requested services
├── demander_service.php                 # Service request form
├── mes_demandes_services.php            # My service requests
│
├── Complaint System
├── reclamation.php                      # File new complaint
├── reclamations_publiques.php           # Public complaints listing
├── detail_reclamation.php               # Complaint details
├── gestion_reclamations.php             # Complaint management
├── traiter_reclamation.php              # Process complaint
├── transfert_reclamation.php            # Transfer complaint
├── assign_complaint.php                 # Assign to agent
├── reassign_complaint.php               # Reassign complaint
│
├── Messaging & Notifications
├── messages.php                         # Message inbox
├── message_detail.php                   # View message thread
├── send_message.php                     # Send message
├── nouvelle_conversation.php            # Start new conversation
├── get_message.php                      # Fetch message data
│
├── Utilities & Services
├── auth.php                             # Authentication handler
├── session.php                          # Session management
├── database.php                         # Database connection
├── autoload.php                         # Class autoloading
├── get_oauth_token.php                  # OAuth token generation
├── generate_qr.php                      # QR code generator
├── canceltokenhelper.php                # Token cancellation helper
│
├── Maintenance & Cron Jobs
├── cron_check_reclamations.php          # Automated complaint checks
├── cron_service_requests.php            # Automated request handling
├── update_status.php                    # Status update handler
├── toggle_agent_status.php              # Toggle agent availability
├── delete_inappropriate_comments.php    # Comment moderation
├── annuler_demande.php                  # Cancel request
├── annuler_reclamation.php              # Cancel complaint
├── annuler_via_email.php                # Email-based cancellation
├── ajouter_commentaire.php              # Add comment
│
├── Configuration & Data
├── composer.json                        # PHP dependencies
├── composer.lock                        # Dependency lock file
├── ma_base.sql                          # Database schema
├── VERSION                              # Version file
├── style1.css                           # Stylesheet
│
├── Documentation
├── LOGO.png                             # Project logo
├── LICENSE                              # LGPL v2.1 License
├── SECURITY.md                          # Security guidelines
├── COMMITMENT                           # Project commitment
└── SMTPUTF8.md                          # Email configuration guide
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 7.0 or higher
- MySQL 5.7 or higher
- Composer (for dependency management)
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/oueslatimalek199-lab/CityCare.git
   cd CityCare
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Setup the database**
   ```bash
   mysql -u root -p < ma_base.sql
   ```

4. **Configure database connection**
   - Edit `database.php` with your database credentials:
   ```php
   $host = 'localhost';
   $username = 'root';
   $password = 'your_password';
   $database = 'citycare';
   ```

5. **Configure email settings**
   - Update SMTP settings in configuration files for email notifications
   - See `SMTPUTF8.md` for detailed email setup

6. **Deploy to web server**
   - Copy all files to your web server's root directory
   - Ensure proper file permissions

7. **Access the application**
   - Navigate to `http://localhost/CityCare/` (or your configured URL)
   - Login with default credentials or register as a new citizen

---

## 👤 User Roles & Permissions

### Citizen
- Submit complaints and service requests
- Track request status
- View public complaints
- Send private messages
- Manage personal profile

### Agent
- View assigned service requests
- Update request status
- Receive and respond to complaints
- Communicate via messaging system
- Track performance metrics

### Administrator
- Manage all users and agents
- Create and manage services
- Moderate comments and complaints
- Configure system settings
- View comprehensive analytics

---

## 📧 Email Notifications

CityCare includes an automated email notification system:
- Service request confirmations
- Complaint status updates
- Agent assignments
- Deadline reminders
- System alerts

**Configuration**: See `SMTPUTF8.md` for SMTP setup with UTF-8 support for international characters.

---

## 🔒 Security & Compliance

- Session-based authentication
- Password reset functionality
- Token-based cancellation system
- User data protection
- LGPL v2.1 license compliance

For security guidelines and vulnerability reporting, see [SECURITY.md](SECURITY.md)

---

## 📚 Database Schema

The application uses a comprehensive MySQL database (`ma_base.sql`) with tables for:
- Users, agents, and administrators
- Services and service requests
- Complaints and comments
- Messages and notifications
- Activity logs and auditing

---

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Submit a pull request

See [COMMITMENT](COMMITMENT) for our project commitment and guidelines.

---

## 📄 License

This project is licensed under the **GNU Lesser General Public License v2.1** - see the [LICENSE](LICENSE) file for details.

---

## 📞 Support & Contact

For questions, issues, or feedback:
- Open an issue on GitHub
- Review security concerns in [SECURITY.md](SECURITY.md)
- Check [COMMITMENT](COMMITMENT) for project details

---

## 🗺️ Roadmap

- [ ] Mobile application
- [ ] Advanced analytics dashboard
- [ ] AI-powered complaint categorization
- [ ] Multi-language support enhancement
- [ ] Integration with third-party services

---

## ✅ Version History

Current Version: See [VERSION](VERSION) file

---

<div align="center">

**Made with ❤️ for better citizen-municipality engagement**

[⬆ back to top](#citycare-)

</div>
