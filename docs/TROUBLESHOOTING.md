# Troubleshooting Guide

Solutions to common issues when deploying with ZDT in GitHub Actions.

## Table of Contents

- [GitHub Actions Issues](#github-actions-issues)
- [SSH Connection Issues](#ssh-connection-issues)
- [Permission Issues](#permission-issues)
- [Git Issues](#git-issues)
- [Deployment Failures](#deployment-failures)
- [Rollback Issues](#rollback-issues)
- [Performance Issues](#performance-issues)

## GitHub Actions Issues

### Secrets Not Available

**Error:**
```
DEPLOY_HOST environment variable is not set
```

**Solution:**

Ensure secrets are properly configured:

1. Go to repository → **Settings** → **Secrets and variables** → **Actions**
2. Verify all required secrets exist:
   - `DEPLOY_HOST`
   - `DEPLOY_USERNAME`
   - `DEPLOY_SSH_KEY`
   - `DEPLOY_PATH`

3. Check secret names match exactly in workflow:
   ```yaml
   env:
     DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}  # Must match secret name
   ```

### SSH Key Format Issues

**Error:**
```
Load key "/tmp/deploy_key": invalid format
```

**Solution:**

Ensure the entire SSH key is in the secret:

```bash
# Copy entire key including headers
cat ~/.ssh/deploy_key
# Should include:
# -----BEGIN OPENSSH PRIVATE KEY-----
# ... key content ...
# -----END OPENSSH PRIVATE KEY-----
```

In workflow, ensure proper formatting:
```yaml
- name: Setup SSH Key
  run: |
    echo "${{ secrets.DEPLOY_SSH_KEY }}" > /tmp/deploy_key
    chmod 600 /tmp/deploy_key
```

## SSH Connection Issues

### Connection Refused

**Error:**
```
SSH authentication failed for deployer@your-server.com
```

**Solutions:**

1. **Check SSH key permissions:**
   ```bash
   chmod 600 ~/.ssh/your_deploy_key
   ```

2. **Test SSH connection manually:**
   ```bash
   ssh -i ~/.ssh/your_deploy_key deployer@your-server.com
   ```

3. **Verify key is added to server:**
   ```bash
   ssh-copy-id -i ~/.ssh/your_deploy_key.pub deployer@your-server.com
   ```

4. **Check server SSH config:**
   ```bash
   # On server
   sudo nano /etc/ssh/sshd_config

   # Ensure these are set:
   PubkeyAuthentication yes
   PasswordAuthentication no
   ```

### Host Key Verification Failed

**Error:**
```
Host key verification failed
```

**Solution:**

Add host to known_hosts:
```bash
ssh-keyscan -H your-server.com >> ~/.ssh/known_hosts
```

### Connection Timeout

**Error:**
```
Connection timed out after 300 seconds
```

**Solutions:**

1. **Increase timeout in workflow:**
   ```yaml
   env:
     DEPLOY_TIMEOUT: 600  # 10 minutes
   ```

2. **Check firewall rules:**
   ```bash
   # On server
   sudo ufw status
   sudo ufw allow 22/tcp
   ```

3. **Verify SSH service is running:**
   ```bash
   # On server
   sudo systemctl status sshd
   sudo systemctl restart sshd
   ```

## Permission Issues

### Cannot Write to Deployment Directory

**Error:**
```
Permission denied: /var/www/your-app
```

**Solutions:**

1. **Fix ownership:**
   ```bash
   sudo chown -R deployer:www-data /var/www/your-app
   sudo chmod -R 775 /var/www/your-app
   ```

2. **Add deployer to www-data group:**
   ```bash
   sudo usermod -a -G www-data deployer
   # Log out and back in
   ```

3. **Set proper directory permissions:**
   ```bash
   sudo chmod 775 /var/www/your-app
   sudo chmod 775 /var/www/your-app/releases
   sudo chmod 775 /var/www/your-app/shared
   ```

### Storage Not Writable

**Error:**
```
The stream or file "storage/logs/laravel.log" could not be opened
```

**Solutions:**

1. **Fix storage permissions:**
   ```bash
   ssh deployer@your-server.com
   cd /var/www/your-app/shared
   chmod -R 775 storage
   chown -R deployer:www-data storage
   ```

2. **Add to deployment hooks:**
   ```yaml
   env:
     DEPLOY_HOOKS_AFTER_ACTIVATE: |
       chmod -R 775 storage
       chmod -R 775 bootstrap/cache
       php artisan queue:restart
   ```

### Symlink Creation Failed

**Error:**
```
Failed to create symlink: /var/www/your-app/current
```

**Solutions:**

1. **Check parent directory permissions:**
   ```bash
   ls -la /var/www/your-app
   sudo chmod 775 /var/www/your-app
   ```

2. **Remove existing symlink if corrupted:**
   ```bash
   ssh deployer@your-server.com
   rm /var/www/your-app/current
   ```

## Git Issues

### Repository Clone Failed

**Error:**
```
fatal: Could not read from remote repository
```

**Solutions:**

1. **Add deploy key to GitHub:**
   - Generate key on server:
     ```bash
     ssh-keygen -t ed25519 -f ~/.ssh/github_deploy
     cat ~/.ssh/github_deploy.pub
     ```
   - Add public key to GitHub → Settings → Deploy keys

2. **Test Git SSH access:**
   ```bash
   ssh -T git@github.com
   ```

3. **Use HTTPS if SSH blocked:**
   ```yaml
   env:
     DEPLOY_REPO_URL: https://github.com/${{ github.repository }}.git
   ```

### Branch Does Not Exist

**Error:**
```
error: pathspec 'feature/xyz' did not match any file(s) known to git
```

**Solutions:**

1. **Verify branch exists:**
   ```bash
   git ls-remote --heads git@github.com:your-org/app.git
   ```

2. **Use correct branch name:**
   ```bash
   zdt deploy --config=deploy.php --branch=main
   ```

### Detached HEAD State

**Error:**
```
You are in 'detached HEAD' state
```

**Solution:**

This is normal! ZDT checks out specific commits. The deployment will work correctly.

## Deployment Failures

### Composer Install Failed

**Error:**
```
Your requirements could not be resolved to an installable set of packages
```

**Solutions:**

1. **Check Composer version on server:**
   ```bash
   ssh deployer@your-server.com
   composer --version
   # Update if needed:
   composer self-update
   ```

2. **Clear Composer cache:**
   ```yaml
   # Add to hooks:
   env:
     DEPLOY_HOOKS_BEFORE_ACTIVATE: |
       composer clear-cache
       composer install --no-dev --optimize-autoloader
       php artisan migrate --force
   ```

3. **Check PHP version compatibility:**
   ```bash
   ssh deployer@your-server.com
   php -v
   ```

### Migration Failed

**Error:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions:**

1. **Check .env file exists:**
   ```bash
   ssh deployer@your-server.com
   cat /var/www/your-app/shared/.env
   ```

2. **Verify database credentials:**
   ```env
   DB_HOST=localhost
   DB_DATABASE=your_database
   DB_USERNAME=your_user
   DB_PASSWORD=your_password
   ```

3. **Test database connection:**
   ```bash
   ssh deployer@your-server.com
   cd /var/www/your-app/current
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

4. **Disable migrations if not needed:**

   Remove migration commands from hooks:
   ```yaml
   env:
     DEPLOY_HOOKS_BEFORE_ACTIVATE: |
       php artisan config:cache
       php artisan route:cache
       php artisan view:cache
   ```

### NPM Build Failed

**Error:**
```
npm ERR! missing script: build
```

**Solutions:**

1. **Verify npm scripts in package.json:**
   ```json
   {
     "scripts": {
       "build": "vite build"
     }
   }
   ```

2. **Check Node version:**
   ```bash
   ssh deployer@your-server.com
   node -v
   npm -v
   ```

3. **Disable npm if not needed:**

   Remove npm commands from hooks:
   ```yaml
   env:
     DEPLOY_HOOKS_AFTER_CLONE: |
       composer install --no-dev --optimize-autoloader
     DEPLOY_HOOKS_BEFORE_ACTIVATE: |
       php artisan migrate --force
       php artisan config:cache
   ```

### Disk Space Full

**Error:**
```
No space left on device
```

**Solutions:**

1. **Check disk usage:**
   ```bash
   ssh deployer@your-server.com
   df -h
   ```

2. **Clean up old releases:**
   ```bash
   zdt releases:cleanup --config=deploy.php --keep=3
   ```

3. **Remove unused Docker images (if applicable):**
   ```bash
   ssh deployer@your-server.com
   docker system prune -a
   ```

4. **Clean package manager caches:**
   ```bash
   ssh deployer@your-server.com
   composer clear-cache
   npm cache clean --force
   ```

## Rollback Issues

### No Previous Release Found

**Error:**
```
No previous release found to rollback to
```

**Solutions:**

1. **List available releases:**
   ```bash
   zdt releases:list --config=deploy.php
   ```

2. **Specify release manually:**
   ```bash
   zdt rollback --config=deploy.php --release=20241127-120000
   ```

### Rollback Target Incomplete

**Error:**
```
Target release appears to be incomplete
```

**Solutions:**

1. **Clean up incomplete releases:**
   ```bash
   ssh deployer@your-server.com
   rm -rf /var/www/your-app/releases/20241127-incomplete
   ```

2. **Rollback to earlier release:**
   ```bash
   zdt rollback --config=deploy.php --release=20241127-110000
   ```

## Performance Issues

### Deployment Takes Too Long

**Solutions:**

1. **Increase SSH timeout:**
   ```yaml
   env:
     DEPLOY_TIMEOUT: 900  # 15 minutes
   ```

2. **Optimize Composer install:**
   ```yaml
   env:
     DEPLOY_HOOKS_AFTER_CLONE: |
       composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts
   ```

3. **Skip unnecessary steps:**

   Remove unused commands from hooks:
   ```yaml
   env:
     DEPLOY_HOOKS_AFTER_CLONE: |
       composer install --no-dev --optimize-autoloader
     DEPLOY_HOOKS_BEFORE_ACTIVATE: |
       php artisan config:cache
   ```

4. **Use Composer cache:**
   ```yaml
   env:
     DEPLOY_HOOKS_AFTER_CLONE: |
       composer install --no-dev --prefer-dist --apcu-autoloader
   ```

### Slow Asset Compilation

**Solutions:**

1. **Compile assets locally and commit:**
   ```bash
   npm run build
   git add public/build
   git commit -m "Compile assets"
   ```

2. **Use faster build tool:**
   ```json
   {
     "scripts": {
       "build": "vite build --mode production"
     }
   }
   ```

3. **Increase Node memory:**
   ```yaml
   env:
     DEPLOY_HOOKS_BEFORE_ACTIVATE: |
       NODE_OPTIONS=--max_old_space_size=4096 npm run build
       php artisan migrate --force
       php artisan config:cache
   ```

## Getting Help

### Enable Debug Mode

Add verbose logging to hooks:

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    set -x
    composer install --verbose --no-dev --optimize-autoloader
```

### Check Deployment Logs

View logs on server:

```bash
ssh deployer@your-server.com
tail -f /var/www/your-app/.zdt/deployment.log
```

### Test Commands Manually

SSH to server and run commands:

```bash
ssh deployer@your-server.com
cd /var/www/your-app/current
php artisan migrate --force
composer install
npm run build
```

### Common Checklist

Before opening an issue, verify:

- [ ] SSH key permissions are correct (600)
- [ ] Server has enough disk space
- [ ] PHP version meets requirements (8.2+)
- [ ] Composer is installed and up to date
- [ ] Git is installed
- [ ] .env file exists in shared directory
- [ ] Database credentials are correct
- [ ] Web server points to `current/public`
- [ ] Deployment directory has correct permissions

## Still Having Issues?

1. **Check existing issues:** https://github.com/veltix/zdt/issues
2. **Open new issue:** Include:
   - ZDT version
   - PHP version
   - Operating system
   - Full error message
   - Deployment configuration (redact secrets!)
   - Steps to reproduce

## Next Steps

- [GitHub Actions Integration](GITHUB_ACTIONS.md)
- [Configuration Reference](CONFIGURATION.md)
