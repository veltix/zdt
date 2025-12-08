# GitHub Actions Integration

Complete guide for integrating ZDT with GitHub Actions for automated deployments.

## Table of Contents

- [Quick Start](#quick-start)
- [Required Secrets](#required-secrets)
- [Basic Workflows](#basic-workflows)
- [Advanced Workflows](#advanced-workflows)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Quick Start

### 1. Add GitHub Secrets

Go to your repository → **Settings → Secrets and variables → Actions** and add:

| Secret | Description | Example |
|--------|-------------|---------|
| `DEPLOY_HOST` | Server hostname | `production.example.com` |
| `DEPLOY_USERNAME` | SSH username | `deployer` |
| `DEPLOY_SSH_KEY` | Private SSH key | `-----BEGIN OPENSSH...` |
| `DEPLOY_PATH` | Deployment directory | `/var/www/your-app` |

### 2. Create Workflow File

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

### 3. Push and Deploy

Push to your `main` branch and watch the deployment in the **Actions** tab!

## Required Secrets

### Generate SSH Key

Generate a dedicated SSH key for deployments:

```bash
# Generate key
ssh-keygen -t ed25519 -C "github-actions@your-app" -f ./github_deploy_key

# Add public key to server
ssh-copy-id -i ./github_deploy_key.pub deployer@your-server.com

# Copy private key content for GitHub Secret
cat ./github_deploy_key
```

⚠️ **Security**: Delete the local private key after adding to GitHub Secrets.

### Add Secrets to GitHub

1. Go to repository → **Settings**
2. Navigate to **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add each secret:
   - `DEPLOY_HOST` - Your server hostname
   - `DEPLOY_USERNAME` - SSH username
   - `DEPLOY_SSH_KEY` - **Entire private key contents** (including headers)
   - `DEPLOY_PATH` - Deployment directory path

## Basic Workflows

### Deploy on Push to Main

```yaml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

### Manual Deployment

Trigger deployments manually from Actions tab:

```yaml
name: Manual Deploy

on:
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

### Deploy with Tests

Run tests before deploying:

```yaml
name: Test and Deploy

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Dependencies
        run: composer install

      - name: Run Tests
        run: vendor/bin/phpunit

  deploy:
    needs: test
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

## Advanced Workflows

### Multi-Environment Deployment

Deploy to staging or production:

```yaml
name: Deploy

on:
  workflow_dispatch:
    inputs:
      environment:
        description: 'Environment to deploy to'
        required: true
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
- `DEPLOY_HOST_staging`, `DEPLOY_HOST_production`
- `DEPLOY_USERNAME_staging`, `DEPLOY_USERNAME_production`
- `DEPLOY_SSH_KEY_staging`, `DEPLOY_SSH_KEY_production`
- `DEPLOY_PATH_staging`, `DEPLOY_PATH_production`

### Deploy with Frontend Assets

```yaml
name: Deploy with Assets

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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
        run: |
          echo "$DEPLOY_KEY_PATH" > /tmp/deploy_key
          chmod 600 /tmp/deploy_key
          export DEPLOY_KEY_PATH=/tmp/deploy_key
          php zdt deploy
```

### Deploy with Automatic Rollback

```yaml
name: Deploy with Rollback

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        id: deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

      - name: Rollback on Failure
        if: failure() && steps.deploy.outcome == 'failure'
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
        run: |
          echo "$DEPLOY_KEY_PATH" > /tmp/deploy_key
          chmod 600 /tmp/deploy_key
          export DEPLOY_KEY_PATH=/tmp/deploy_key
          php zdt rollback
```

### Deploy with Slack Notifications

```yaml
name: Deploy with Notifications

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Notify Deployment Start
        uses: 8398a7/action-slack@v3
        with:
          status: custom
          custom_payload: |
            {
              text: "🚀 Deployment started for ${{ github.repository }}",
              attachments: [{
                color: 'warning',
                text: 'Branch: ${{ github.ref_name }}\nCommit: ${{ github.sha }}'
              }]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

      - name: Notify Success
        if: success()
        uses: 8398a7/action-slack@v3
        with:
          status: custom
          custom_payload: |
            {
              text: "✅ Deployment succeeded!",
              attachments: [{
                color: 'good',
                text: 'Branch: ${{ github.ref_name }}\nCommit: ${{ github.sha }}'
              }]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}

      - name: Notify Failure
        if: failure()
        uses: 8398a7/action-slack@v3
        with:
          status: custom
          custom_payload: |
            {
              text: "❌ Deployment failed!",
              attachments: [{
                color: 'danger',
                text: 'Branch: ${{ github.ref_name }}\nCommit: ${{ github.sha }}'
              }]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### Conditional Deployment

Deploy only if specific files changed:

```yaml
name: Conditional Deploy

on:
  push:
    branches: [main]

jobs:
  check-changes:
    runs-on: ubuntu-latest
    outputs:
      should_deploy: ${{ steps.changes.outputs.src }}
    steps:
      - uses: actions/checkout@v4

      - uses: dorny/paths-filter@v2
        id: changes
        with:
          filters: |
            src:
              - 'app/**'
              - 'config/**'
              - 'database/**'
              - 'routes/**'
              - 'resources/**'

  deploy:
    needs: check-changes
    if: needs.check-changes.outputs.should_deploy == 'true'
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install ZDT
        run: composer global require veltix/zdt

      - name: Deploy
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USERNAME: ${{ secrets.DEPLOY_USERNAME }}
          DEPLOY_KEY_PATH: ${{ secrets.DEPLOY_SSH_KEY }}
          DEPLOY_REPO_URL: git@github.com:${{ github.repository }}.git
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
          DEPLOY_BRANCH: ${{ github.ref_name }}
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

## Best Practices

### 1. Use Dedicated Deploy Keys

Generate separate SSH keys for each environment:

```bash
ssh-keygen -t ed25519 -C "github-staging" -f ./staging_key
ssh-keygen -t ed25519 -C "github-production" -f ./production_key
```

### 2. Use GitHub Environments

Leverage environments for:
- Required reviewers before deployment
- Wait timers
- Environment-specific secrets

```yaml
jobs:
  deploy:
    environment:
      name: production
      url: https://your-app.com
```

### 3. Never Commit Secrets

- Never commit private keys
- Never commit passwords
- Always use GitHub Secrets

### 4. Test in Staging First

Always test deployments in staging before production:

```yaml
on:
  push:
    branches:
      - main         # Production
      - develop      # Staging
```

### 5. Monitor Deployment Duration

Track how long deployments take:

```yaml
- name: Deploy
  id: deploy
  run: |
    START_TIME=$(date +%s)
    # ... deployment commands ...
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    echo "duration=$DURATION" >> $GITHUB_OUTPUT

- name: Report Duration
  run: echo "Deployment took ${{ steps.deploy.outputs.duration }} seconds"
```

### 6. Save Deployment Logs

Store logs as artifacts:

```yaml
- name: Deploy
  run: php zdt deploy 2>&1 | tee deployment.log

- name: Upload Logs
  if: always()
  uses: actions/upload-artifact@v3
  with:
    name: deployment-logs
    path: deployment.log
```

## Troubleshooting

### SSH Key Issues

Debug SSH connection:

```yaml
- name: Debug SSH
  run: |
    echo "${{ secrets.DEPLOY_SSH_KEY }}" > /tmp/deploy_key
    chmod 600 /tmp/deploy_key
    ssh -vvv -i /tmp/deploy_key deployer@${{ secrets.DEPLOY_HOST }} "echo Connection successful"
```

### Host Key Verification

Add host key verification:

```yaml
- name: Setup SSH
  run: |
    mkdir -p ~/.ssh
    chmod 700 ~/.ssh
    ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts
    chmod 644 ~/.ssh/known_hosts
```

### Permission Issues

Ensure proper SSH key permissions:

```yaml
- name: Setup SSH Key
  run: |
    mkdir -p ~/.ssh
    echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/deploy_key
    chmod 600 ~/.ssh/deploy_key
    ls -la ~/.ssh/deploy_key
```

### Deployment Failures

Enable verbose logging:

```yaml
env:
  DEPLOY_HOOKS_AFTER_CLONE: |
    set -x
    composer install --verbose --no-dev --optimize-autoloader
```

## Next Steps

- [Configuration Reference](CONFIGURATION.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
