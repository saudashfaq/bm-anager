# Backlinks Manager - Installation Guide

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
3. [Configuration](#configuration)
4. [Database Setup](#database-setup)
5. [Cron Jobs Setup](#cron-jobs-setup)
6. [Security Considerations](#security-considerations)
7. [Troubleshooting](#troubleshooting)

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
   - **Important**: Create a strong password or update password by visiting the "Profile" link

## Cron Jobs Setup

The application requires two automated tasks to run periodically:

1. **Backlink Verification** (default every 30 minutes)
2. **Proxy Scraping and Validation** (default every 6 hours)

These jobs will run automaticall when you setup the cron job as follows.

### Setting Up the Cron Job

1. **Access your server's crontab**

   ```bash
   crontab -e
   ```

2. **Add the following line to run the cron manager every 5 minutes**

   ```bash
   */5 * * * * php /path/to/your/backlinks_manager/jobs/cron_manager.php >> /path/to/your/backlinks_manager/logs/cron.log 2>&1
   ```

   Replace `/path/to/your/backlinks_manager` with your actual installation path.

3. **Create the logs directory**

Even if you don't create the following file it should get created automatically. You might need to set the permissions only.

```bash
mkdir -p /path/to/your/backlinks_manager/logs
chmod 755 /path/to/your/backlinks_manager/logs
```

4. **Verify the cron job is running**
   - Check the cron log file:
     ```bash
     tail -f /path/to/your/backlinks_manager/logs/cron.log
     ```
   - You should see messages indicating the cron manager is running and jobs are being executed

### Cron Job Configuration

The cron jobs are managed by `cron_manager.php`, which:

- Tracks when each job was last run
- Determines when each job should run next
- Executes jobs only when they are due
- Logs all job executions and any errors

You can modify the job intervals by editing the `$jobs` array in `cron_manager.php`:

```php
$jobs = [
    'backlink_verifier' => [
        'interval' => 30, // minutes
        'enabled' => true
    ],
    'proxy_scraper_validator' => [
        'interval' => 360, // minutes (6 hours)
        'enabled' => true
    ]
];
```

### Troubleshooting Cron Jobs

If the cron jobs are not running:

1. **Check cron service status**

   ```bash
   systemctl status cron
   ```

2. **Verify file permissions**

   ```bash
   chmod 755 /path/to/your/backlinks_manager/jobs/cron_manager.php
   chmod 755 /path/to/your/backlinks_manager/jobs/BacklinkVerifier.php
   chmod 755 /path/to/your/backlinks_manager/jobs/ProxyScraperValidator.php
   ```

3. **Check PHP CLI installation**

   ```bash
   php -v
   ```

4. **Review cron logs**
   ```bash
   grep CRON /var/log/syslog
   ```

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
