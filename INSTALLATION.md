# Backlinks Manager - Installation Guide

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
3. [Configuration](#configuration)
4. [Database Setup](#database-setup)
5. [Security Considerations](#security-considerations)
6. [Troubleshooting](#troubleshooting)

## System Requirements

### Server Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)
- SSL certificate (recommended for production)

### PHP Extensions

- PDO
- PDO_MySQL
- cURL
- JSON
- mbstring
- OpenSSL

## Installation Steps

1. **Download and Extract**

   - Download the `backlink_manager.zip` file
   - Extract the contents to your web server's root directory (e.g., `/var/www/html/` or your preferred location)

2. **File Permissions**

   ```bash
   chmod 755 -R /path/to/backlinks_manager
   chmod 777 -R /path/to/backlinks_manager/config/proxies.json
   ```

3. **Web Server Configuration**
   - Ensure your web server is configured to use the application's root directory
   - Make sure mod_rewrite is enabled if using Apache
   - Configure your web server to use PHP

## Database Setup

1. **Create Database**

   - Create a new MySQL database
   - Run the installation script by visiting:
     ```
     http://your-domain.com/install/
     ```

2. **Initial Setup**
   - After installation, visit the login page
   - Use the default credentials:
     - Username: admin@example.com
     - Password: 12345678
   - **Important**: Create a strong password

## Security Considerations

1. **File Permissions**

   - Ensure sensitive files are not publicly accessible
   - Keep configuration files outside the web root if possible
   - Regularly update file permissions

2. **SSL Configuration**

   - Install SSL certificate
   - Force HTTPS in your configuration
   - Update all URLs to use HTTPS

3. **Regular Maintenance**
   - Keep PHP and MySQL updated
   - Regularly backup your database
   - Monitor error logs

## Troubleshooting

### Common Issues

1. **Database Connection Error**

   - Verify database credentials
   - Check if MySQL service is running
   - Ensure database user has proper permissions

2. **404 Errors**

   - Check mod_rewrite configuration
   - Verify .htaccess file exists
   - Check file permissions

3. **Permission Issues**
   - Verify file ownership
   - Check directory permissions
   - Ensure web server user has proper access

### Getting Help

If you encounter any issues during installation:

1. Check the error logs
2. Contact support with:
   - Error messages
   - Server configuration details
   - Steps to reproduce the issue

---

**Note**: This installation guide assumes a standard LAMP/LEMP stack setup. For specific hosting environments, additional configuration might be required.
