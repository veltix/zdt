# Configuration Reference

Complete reference for ZDT deployment configuration using GitHub Actions environment variables.

## Overview

ZDT is designed for GitHub Actions deployments using environment variables. Configuration is done through:
- **GitHub Secrets** - Sensitive data (SSH keys, credentials)
- **GitHub Workflow YAML** - Environment variables and deployment hooks
- **Config File (optional)** - For advanced customization

## Required Environment Variables

Configure these as GitHub Secrets (Settings → Secrets and variables → Actions):

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `DEPLOY_HOST` | Yes | Server hostname or IP | `production.example.com` |
| `DEPLOY_USERNAME` | Yes | SSH username | `deployer` |
| `DEPLOY_SSH_KEY` | Yes | Private SSH key content | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `DEPLOY_PATH` | Yes | Deployment root directory | `/var/www/your-app` |

**Auto-detected variables** (no configuration needed):
- `DEPLOY_REPO_URL` - Automatically set from `${{ github.repository }}`
- `DEPLOY_BRANCH` - Automatically set from `${{ github.ref_name }}`

## Optional Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DEPLOY_PORT` | `22` | SSH port number |
| `DEPLOY_TIMEOUT` | `300` | SSH connection timeout (seconds) |
| `DEPLOY_KEEP_RELEASES` | `5` | Number of releases to keep |

### Example Workflow Configuration

```yaml
env:
  DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
  DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
  DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
  DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
  DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
  DEPLOY_BRANCH: ${{ github.ref_name }}
  DEPLOY_PORT: 22
  DEPLOY_TIMEOUT: 600
  DEPLOY_KEEP_RELEASES: 5
```

## Directory Structure

ZDT creates this structure on your server:

```
/var/www/your-app/
├── releases/                    # Timestamped releases
│   ├── 20250129-120000/
│   │   ├── .env                ← Copied from shared
│   │   ├── storage/            → Symlink to shared/storage
│   │   └── resources/lang/     → Symlink to shared/lang (if configured)
│   ├── 20250129-130000/
│   └── 20250129-140000/
├── shared/                      # Persistent files shared across releases
│   ├── .env                    ← Master environment file
│   ├── storage/                ← Logs, cache, uploads
│   └── lang/                   ← Custom shared directory (if configured)
├── current → releases/20250129-140000  # Active release symlink
└── .zdt/                        # Deployment metadata
    ├── deployment.log
    └── releases.json
```

## Deployment Hooks

Hooks execute custom commands at different deployment stages using YAML multiline syntax.

### Available Hook Stages

| Hook | Execution Time | Common Use Cases |
|------|----------------|------------------|
| `DEPLOY_HOOKS_BEFORE_CLONE` | Before repository clone | Pre-deployment validation |
| `DEPLOY_HOOKS_AFTER_CLONE` | After repository clone | Install dependencies |
| `DEPLOY_HOOKS_BEFORE_ACTIVATE` | Before symlink switch | Migrations, cache warming |
| `DEPLOY_HOOKS_AFTER_ACTIVATE` | After symlink switch | Queue restart, notifications |
| `DEPLOY_HOOKS_AFTER_ROLLBACK` | After rollback complete | Cleanup, alerts |

### Hook Execution Rules

- Hooks execute in the **release directory** context
- Commands run sequentially in order
- Hook failure **aborts deployment** and triggers rollback
- Each command must exit with code `0` for success
- Empty lines are automatically filtered out

### Basic Laravel Deployment

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --optimize-autoloader
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  DEPLOY_HOOKS_AFTER_ACTIVATE: |
    php artisan queue:restart
```

### Laravel with Frontend Assets

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --optimize-autoloader
    npm ci --production
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    npm run build
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  DEPLOY_HOOKS_AFTER_ACTIVATE: |
    php artisan queue:restart
    php artisan optimize
```

### Laravel with Horizon

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --optimize-autoloader
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan horizon:assets
  DEPLOY_HOOKS_AFTER_ACTIVATE: |
    php artisan horizon:terminate
    php artisan queue:restart
```

### Laravel with Octane

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --optimize-autoloader
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
  DEPLOY_HOOKS_AFTER_ACTIVATE: |
    php artisan octane:reload
```

### Custom Deployment Scripts

```yaml
env:
  DEPLOY_HOOKS_BEFORE_CLONE: |
    echo "Starting deployment at $(date)"
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --optimize-autoloader
    bash scripts/generate-sitemap.sh
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    php artisan migrate --force
    php artisan app:cache-warm
    php artisan app:generate-reports
  DEPLOY_HOOKS_AFTER_ACTIVATE: |
    php artisan queue:restart
    curl -X POST https://api.example.com/webhooks/deploy
  DEPLOY_HOOKS_AFTER_ROLLBACK: |
    php artisan cache:clear
    php artisan queue:restart
    curl -X POST https://api.example.com/webhooks/rollback
```

### Default Hooks

If no hooks are specified, ZDT uses these defaults:

```yaml
# DEPLOY_HOOKS_AFTER_CLONE (default)
composer install --no-dev --optimize-autoloader

# DEPLOY_HOOKS_BEFORE_ACTIVATE (default)
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# DEPLOY_HOOKS_AFTER_ACTIVATE (default)
php artisan queue:restart
```

## Custom Shared Paths

Share additional files or directories across releases beyond the default `.env` and `storage/`.

### Configuration in config/deploy.php

```php
'shared_paths' => [
    'resources/lang' => 'lang',           // Translations
    'public/uploads' => 'uploads',        // User uploads
    'config/custom.php' => 'custom.php',  // Custom config
],
```

Format: `'path/in/release' => 'path/in/shared'`

### How It Works

1. ZDT creates `shared/{path}` if it doesn't exist
2. Removes `releases/{timestamp}/{release-path}` if it exists
3. Creates symlink: `shared/{path}` → `releases/{timestamp}/{release-path}`

### Example: Shared Translations

**Config:**
```php
'shared_paths' => [
    'resources/lang' => 'lang',
],
```

**Result:**
```
shared/lang/ → releases/20250129-120000/resources/lang/
```

### Example: Shared User Uploads

**Config:**
```php
'shared_paths' => [
    'public/uploads' => 'uploads',
],
```

**Result:**
```
shared/uploads/ → releases/20250129-120000/public/uploads/
```

### Example: Multiple Shared Paths

```php
'shared_paths' => [
    'resources/lang' => 'lang',
    'public/uploads' => 'uploads',
    'public/media' => 'media',
    'config/custom.php' => 'config/custom.php',
    'storage/app/certificates' => 'certificates',
],
```

### Default Shared Resources

These are **automatically shared** without configuration:

- ✅ `.env` - **Copied** to each release (not symlinked)
- ✅ `storage/` - **Symlinked** from `shared/storage`

## Deployment Options

Configure deployment behavior through environment variables.

### Keep Releases

Control how many releases to retain:

```yaml
env:
  DEPLOY_KEEP_RELEASES: 5  # Production (more rollback options)
  # or
  DEPLOY_KEEP_RELEASES: 3  # Staging (less disk usage)
```

Override per command:
```bash
php zdt cleanup --keep=3
```

### Composer Dependencies

Enabled by default. Runs `composer install` during deployment.

To customize:
```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    composer install --no-dev --prefer-dist --optimize-autoloader --apcu-autoloader
```

### Database Migrations

Enabled by default. Runs `php artisan migrate --force` before activation.

To disable:
```yaml
env:
  DEPLOY_HOOKS_BEFORE_ACTIVATE: |
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
```

## Health Checks

Validate deployments with HTTP health checks.

### Example Health Check Endpoint

**Laravel Route:**
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
    ]);
});
```

**With Database Check:**
```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'connected';
    } catch (\Exception $e) {
        $database = 'disconnected';
        return response()->json([
            'status' => 'unhealthy',
            'database' => $database,
        ], 503);
    }

    return response()->json([
        'status' => 'healthy',
        'database' => $database,
    ]);
});
```

## Multi-Environment Deployments

Deploy to different environments using GitHub workflow inputs.

### Example: Staging and Production

```yaml
name: Deploy

on:
  workflow_dispatch:
    inputs:
      environment:
        type: choice
        options:
          - staging
          - production

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: ${{ github.event.inputs.environment }}

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy to ${{ inputs.environment }}
        env:
          DEPLOY_HOST: ${{ secrets[format('DEPLOY_HOST_{0}', inputs.environment)] }}
          DEPLOY_USERNAME: ${{ secrets[format('DEPLOY_USERNAME_{0}', inputs.environment)] }}
          DEPLOY_KEY_PATH: ${{ secrets[format('DEPLOY_SSH_KEY_{0}', inputs.environment)] }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets[format('DEPLOY_PATH_{0}', inputs.environment)] }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
          DEPLOY_KEEP_RELEASES: ${{ inputs.environment == 'production' && 5 || 3 }}
          DEPLOY_HOOKS_AFTER_CLONE: |
            composer install --no-dev --optimize-autoloader
          DEPLOY_HOOKS_BEFORE_ACTIVATE: |
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
          DEPLOY_HOOKS_AFTER_ACTIVATE: |
            php artisan queue:restart
        run: |
          echo "$DEPLOY_KEY_PATH" > /tmp/deploy_key
          chmod 600 /tmp/deploy_key
          export DEPLOY_KEY_PATH=/tmp/deploy_key
          php zdt deploy
```

**Required Secrets:**
- `DEPLOY_HOST_staging`
- `DEPLOY_HOST_production`
- `DEPLOY_USERNAME_staging`
- `DEPLOY_USERNAME_production`
- `DEPLOY_SSH_KEY_staging`
- `DEPLOY_SSH_KEY_production`
- `DEPLOY_PATH_staging`
- `DEPLOY_PATH_production`

## Validation

Configuration is validated before deployment:

- **Required**: `DEPLOY_HOST`, `DEPLOY_USERNAME`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`
- **SSH key**: Must be valid and accessible
- **Port**: Must be between 1-65535
- **Timeout**: Must be at least 1 second
- **Keep releases**: Must be at least 1

## Next Steps

- [GitHub Actions Integration](GITHUB_ACTIONS.md)
- [Troubleshooting](TROUBLESHOOTING.md)
