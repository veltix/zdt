# ZDT Deployment Configuration Examples

This directory contains example configurations for deploying with ZDT. Choose the approach that best fits your workflow.

## Available Examples

### 1. Basic GitHub Actions Deployment
**File:** `deploy.yml`

Simple workflow that deploys on push to main branch. Perfect for getting started quickly.

**Features:**
- ✅ Deploy on push to main
- ✅ Basic deployment hooks
- ✅ Simple configuration

**Setup:**
1. Copy to `.github/workflows/deploy.yml`
2. Add GitHub Secrets (DEPLOY_HOST, DEPLOY_USERNAME, DEPLOY_SSH_KEY, DEPLOY_PATH)
3. Push to main branch

---

### 2. Advanced Multi-Environment Workflow
**File:** `test-deploy.yml`

Comprehensive workflow with testing, multiple environments, health checks, and automatic rollback.

**Features:**
- ✅ Run tests before deploying
- ✅ Multiple environments (staging/production)
- ✅ Health check validation
- ✅ Slack notifications
- ✅ Automatic rollback on failure
- ✅ Manual deployment trigger

**Setup:**
1. Copy to `.github/workflows/deploy.yml`
2. Configure GitHub Secrets for each environment
3. Set up GitHub Environments with protection rules
4. Push to main (production) or develop (staging)

---

### 3. Server Configuration File
**File:** `.env.deployment.example`

Store deployment configuration on your server. Ideal for teams and multiple environments.

**Features:**
- ✅ Configuration persists across deployments
- ✅ Deploy from anywhere (CI/CD or locally)
- ✅ Server-specific settings
- ✅ No project files needed

**Setup:**
1. Copy to your server: `/var/www/your-app/shared/.env.deployment`
2. Edit values for your environment
3. Deploy with: `zdt deploy` (no env vars needed!)

---

### 4. Project Configuration File
**File:** `deploy-project-env.php`

Commit deployment configuration to your repository. Great for versioning and team collaboration.

**Features:**
- ✅ Versioned with your code
- ✅ Custom PHP logic support
- ✅ Team-friendly
- ✅ Override with environment variables

**Setup:**
1. Copy to your project root as `deploy.php`
2. Customize for your needs
3. Commit to repository
4. Deploy with: `zdt deploy`

---

## Configuration Priority

When multiple configuration sources exist, ZDT uses this priority order:

1. **Environment Variables** (highest priority)
2. **Server's `.env.deployment`** file
3. **Project's `deploy.php`** file
4. **ZDT defaults** (lowest priority)

This allows you to:
- Use `.env.deployment` for server-specific defaults
- Override with environment variables for CI/CD
- Version configuration in `deploy.php` for team use

---

## Quick Reference

### Required Configuration

| Setting | Description | Example |
|---------|-------------|---------|
| `DEPLOY_HOST` | Server hostname | `production.example.com` |
| `DEPLOY_USERNAME` | SSH username | `deploy` |
| `DEPLOY_KEY_PATH` | SSH private key path | `~/.ssh/id_rsa` |
| `DEPLOY_REPO_URL` | Git repository URL | `git@github.com:user/repo.git` |
| `DEPLOY_PATH` | Deployment directory | `/var/www/your-app` |

### Optional Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| `DEPLOY_BRANCH` | `main` | Git branch to deploy |
| `DEPLOY_PORT` | `22` | SSH port |
| `DEPLOY_TIMEOUT` | `300` | SSH timeout (seconds) |
| `DEPLOY_KEEP_RELEASES` | `5` | Number of releases to keep |

---

## Next Steps

- [Full Documentation](../README.md)
- [Configuration Reference](../docs/CONFIGURATION.md)
- [GitHub Actions Guide](../docs/GITHUB_ACTIONS.md)
- [Troubleshooting](../docs/TROUBLESHOOTING.md)
