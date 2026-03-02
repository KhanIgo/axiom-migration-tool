# Axiom WP Migrate

Safe and controllable migration of WordPress database between environments (local/stage/prod).

## Description

Axiom WP Migrate is a free, open-source WordPress plugin that enables developers to safely migrate databases between different WordPress environments. It handles serialized data correctly, supports chunked transfers for large databases, and includes comprehensive backup and rollback capabilities.

## Features

- **Push/Pull Migrations**: Sync databases between local, staging, and production environments
- **Export/Import**: Generate SQL dumps and import SQL files
- **Serialized Data Support**: Safe handling of PHP serialized data during URL/path replacements
- **Dry-Run Mode**: Preview migration changes before applying them
- **Automatic Backups**: Create backups before any destructive operation
- **Table Filtering**: Include/exclude specific tables or use presets (content-only, no-users)
- **Job Tracking**: Monitor migration progress with detailed logs
- **WP-CLI Support**: Full command-line interface for automation
- **Secure Authentication**: HMAC-signed API requests between environments
- **Chunked Transfers**: Handle large databases without memory issues

## Requirements

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Download the plugin or clone the repository
2. Upload the `axiom-migration-tool` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Axiom Migrate in the admin menu to configure

## Quick Start

### Setting Up a Connection

1. Go to **Axiom Migrate → Connections**
2. Click **Add New Connection**
3. Enter:
   - **Name**: A friendly name (e.g., "Production")
   - **URL**: Full URL of the remote WordPress site
   - **Access Key**: Shared secret for authentication
4. Click **Save Connection**
5. Click **Test** to verify the connection

### Pushing Database to Remote

1. Go to **Axiom Migrate → Migrations**
2. In the **Push Database** section:
   - Select destination connection
   - Choose table filter (All/Content-only/No-users/Custom)
   - Optionally enable Dry Run
3. Click **Push Database**

### Pulling Database from Remote

1. Go to **Axiom Migrate → Migrations**
2. In the **Pull Database** section:
   - Select source connection
   - Choose table filter
   - Optionally enable Dry Run
3. Click **Pull Database**

### Creating a Backup

1. Go to **Axiom Migrate → Backups**
2. Enter a backup name (or use auto-generated)
3. Click **Create Backup**

### Using WP-CLI

```bash
# Export database
wp awm export --file=/path/to/backup.sql

# Import database
wp awm import --file=/path/to/backup.sql

# Push to remote
wp awm migrate push --connection=production

# Pull from remote
wp awm migrate pull --connection=staging

# Dry run
wp awm migrate push --connection=production --dry-run

# Check job status
wp awm jobs status

# Create backup
wp awm backup create --name=pre-deployment

# List backups
wp awm backup list

# Restore backup
wp awm backup restore --name=backup_1234567890.sql
```

## Configuration

### Settings

Go to **Axiom Migrate → Settings** to configure:

- **Chunk Size**: Size of data chunks for transfer (default: 5MB)
- **Max Retries**: Number of retry attempts for failed operations
- **Backup Retention (Days)**: How long to keep backups
- **Backup Retention (Count)**: Minimum number of recent backups to keep
- **Safety Options**:
  - Require backup before migration (recommended)
  - Enforce HTTPS for remote connections

### Remote API Authentication

The plugin uses HMAC-SHA256 signed requests for secure communication:

1. Both sites must have the plugin installed
2. Generate a shared secret key
3. Configure the connection with the same key on both ends
4. Requests include:
   - `X-AWM-Key-Id`: Key identifier
   - `X-AWM-Timestamp`: Request timestamp
   - `X-AWM-Nonce`: Unique request identifier
   - `X-AWM-Signature`: HMAC signature

## Architecture

### Plugin Structure

```
axiom-migration-tool/
├── axiom-wp-migrate.php      # Main plugin file
├── src/
│   ├── Admin/
│   │   └── AdminPage.php     # Admin menu handler
│   ├── Application/
│   │   ├── MigrationEngine.php  # Migration orchestration
│   │   └── ReplaceEngine.php    # Serialized-safe replacement
│   ├── Domain/
│   │   └── BackupService.php    # Backup operations
│   ├── Infrastructure/
│   │   ├── JobStore.php         # Job persistence
│   │   └── AuditLogger.php      # Structured logging
│   ├── Transport/
│   │   ├── TransportClient.php  # Outgoing requests
│   │   └── TransportServer.php  # Incoming requests
│   └── CLI/
│       └── MigrationCommand.php # WP-CLI commands
├── templates/
│   └── admin/                   # Admin UI templates
└── assets/
    ├── css/                     # Admin styles
    └── js/                      # Admin scripts
```

### Database Tables

- `wp_awm_jobs`: Migration job records
- `wp_awm_job_steps`: Job step tracking
- `wp_awm_logs`: Audit logs

### Job States

- `created`: Job initialized
- `running`: Job in progress
- `paused`: Job temporarily stopped
- `failed`: Job failed
- `completed`: Job finished successfully

## Security

- **Capability Checks**: All operations require `manage_options`
- **Nonce Verification**: CSRF protection for all actions
- **Signed Requests**: HMAC authentication for remote operations
- **Input Sanitization**: All user input is sanitized
- **Output Escaping**: All output is properly escaped
- **HTTPS Enforcement**: Optional HTTPS-only mode

## Troubleshooting

### Connection Fails

1. Verify both sites have the plugin installed and activated
2. Check that the Access Key matches on both ends
3. Ensure the remote URL is accessible
4. Check server firewall rules
5. Verify SSL certificates are valid

### Migration Timeout

1. Reduce chunk size in settings
2. Increase PHP `max_execution_time`
3. Increase PHP `memory_limit`
4. Use table filters to migrate smaller subsets

### Serialized Data Issues

The plugin automatically handles serialized data. If you encounter issues:

1. Enable dry-run mode first
2. Check logs for specific errors
3. Verify the serialized data format

## Development

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit tests/unit/

# Integration tests
vendor/bin/phpunit tests/integration/
```

### Code Standards

```bash
# Check coding standards
vendor/bin/phpcs --standard=WordPress

# Fix issues
vendor/bin/phpcbf --standard=WordPress
```

## Changelog

### 1.0.0

- Initial release
- Push/pull migrations
- Export/import functionality
- Serialized-safe replacements
- Backup and restore
- WP-CLI commands
- Admin UI
- Audit logging

## License

GPL v2 or later

## Credits

Developed by the Axiom Team.

## Support

- Documentation: [Link to docs]
- Issues: [GitHub Issues]
- Community: [Support forum]
