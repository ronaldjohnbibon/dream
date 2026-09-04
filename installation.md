# DigitalOcean App Platform deployment

This guide replaces manual Droplet administration. DigitalOcean App Platform builds
the Laravel app from Git and runs the web service, queue worker, and scheduled
job. Use a DigitalOcean Managed MySQL database and Cloudflare R2 for
persistent customer uploads.

## Before you begin

You need:

- a DigitalOcean account;
- a GitHub, GitLab, or Bitbucket repository containing this project;
- a Cloudflare-managed domain;
- a production mail provider and credentials; and
- a credit card or balance for App Platform, Managed MySQL, and R2.

Do not use App Platform's dev database for this application: it is PostgreSQL
only. The application is configured for MySQL, so use Managed MySQL.

## 1. Prepare the repository

The repository must contain:

- composer.json and composer.lock;
- package.json and package-lock.json;
- Laravel's public/ directory; and
- the PHP requirement in composer.json.

This project already meets those requirements. Commit and push all production
changes before creating the App Platform app.

Do not commit .env, production passwords, APP_KEY, database credentials, or
R2 keys.

## 2. Add an App Platform configuration

Create .do/app.yaml in the repository. This makes the required PHP build and
web-server command explicit:

~~~yaml
name: <app-name>
region: sgp
services:
  - name: web
    environment_slug: php
    github:
      repo: <github-owner>/<repository>
      branch: main
      deploy_on_push: true
    build_command: composer install --no-interaction --prefer-dist --optimize-autoloader && npm ci && npm run build
    run_command: heroku-php-apache2 public/
    http_port: 8080
    instance_count: 1
    instance_size_slug: apps-s-1vcpu-1gb
    source_dir: /
~~~

Replace the placeholders, commit the file, and push it. If using GitLab,
Bitbucket, or a generic Git URL, create the app in the control panel and use
the equivalent source settings instead.

The build command is important: Laravel needs Composer dependencies and this
Inertia/Vue application needs Vite assets built before the web service starts.

## 3. Create the App Platform app

1. In DigitalOcean, open **Apps** and select **Create App**.
2. Connect the Git provider and authorize access to the repository.
3. Select the production branch, normally main.
4. Confirm that App Platform detects a PHP web service.
5. Select **Singapore** for the region.
6. Check the detected configuration or upload/select .do/app.yaml.
7. Start with one web-service container. Choose the smallest plan that meets
   the app's memory needs, then resize based on metrics.
8. Enable automatic deployment only for a protected production branch.
9. Launch the app and inspect the build log.

A successful first build should run Composer, npm ci, npm run build, and then
start Apache through heroku-php-apache2 public/.

## 4. Create and attach Managed MySQL

1. In **Databases**, create a **Managed MySQL** cluster in Singapore.
2. Choose a production-appropriate plan; do not expose the database publicly.
3. In the App Platform app, choose **Add components → Create or attach database**.
4. Attach the MySQL cluster and add the app as a trusted source.
5. Create the application's database and user in the cluster as needed.
6. Prefer App Platform's database bindable variables for the hostname, port,
   database, user, and password.

The web service, migration job, worker, and scheduled job all need database
environment variables. Add them to each component, or use component scopes that
make them available where needed.

Managed databases often require TLS. Set the Laravel/PDO database SSL or CA
options that DigitalOcean provides for the attached database; do not disable
certificate validation just to make a connection work.

## 5. Configure environment variables and secrets

In the App's **Settings → Environment Variables**, define production settings.
Use **encrypted/secret** values for every credential and private key.

~~~dotenv
APP_NAME="<application-name>"
APP_ENV=production
APP_KEY=<generate-with-php-artisan-key-generate-locally>
APP_DEBUG=false
APP_URL=https://<app-default-domain>

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<managed-database-host-or-bindable-variable>
DB_PORT=<managed-database-port-or-bindable-variable>
DB_DATABASE=<database-name-or-bindable-variable>
DB_USERNAME=<database-user-or-bindable-variable>
DB_PASSWORD=<database-password-or-bindable-variable>

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=<provider>
MAIL_HOST=<host>
MAIL_PORT=<port>
MAIL_USERNAME=<username>
MAIL_PASSWORD=<password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<from-address>
MAIL_FROM_NAME="<application-name>"
~~~

Generate APP_KEY once in a trusted local environment, then copy its complete
base64 value into App Platform as an encrypted secret. Keep it unchanged after
the first production deployment; changing it invalidates encrypted values and
sessions.

Set APP_URL to the temporary App Platform domain first. Update it to the custom
domain after the domain is verified, then deploy again.

## 6. Make uploads persistent before launch

App Platform's local filesystem is temporary. Any payment screenshot saved to
storage/ can disappear at deployment or instance replacement.

Before accepting GCash screenshots or other uploads:

1. Create two Cloudflare R2 buckets: one for public media and one for private uploads.
2. Create an R2 API token with Object Read & Write access limited to both buckets.
3. Connect the public-media bucket to a production custom domain, such as
   `media.example.com`, and disable its `r2.dev` development URL.
4. Do not configure a public custom domain or public-development URL for the
   private-upload bucket.
5. Add encrypted App Platform variables:

~~~dotenv
FILESYSTEM_DISK=r2-private
R2_ACCESS_KEY_ID=<r2-access-key-id>
R2_SECRET_ACCESS_KEY=<r2-secret-access-key>
R2_REGION=auto
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_PUBLIC_BUCKET=<public-media-bucket>
R2_PRIVATE_BUCKET=<private-upload-bucket>
R2_PUBLIC_URL=https://media.example.com
R2_USE_PATH_STYLE_ENDPOINT=false
~~~

6. Test a public logo/QR upload and replacement, a private screenshot upload,
   authorized retrieval, unauthorized denial, and a redeploy. Existing local
   upload files are left untouched and are not copied to R2.

Do not make the private R2 bucket public.

## 7. Add the pre-deploy migration job

In **App → Settings → Add components**, create a job from the same repository.

Configure it as:

| Setting | Value |
| --- | --- |
| Component type | Job |
| Trigger | Before every deploy |
| Command | php artisan migrate --force |
| Source branch | Same production branch as web |
| Environment | Same production database variables |
| Instance size | Same as or smaller than web, as migration needs allow |

A pre-deploy job must succeed before the new web release is launched. Keep
migrations backwards-compatible with the prior release where possible. Take a
database backup before destructive migrations.

## 8. Add the queue worker

Create a second component from the same source repository.

| Setting | Value |
| --- | --- |
| Component type | Worker |
| Name | laravel-worker |
| Command | php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 |
| Source branch | Same production branch as web |
| Environment | Same application and database variables |
| Initial scale | One worker |

The worker is not publicly routable. View its logs in App Platform and routinely
review the failed_jobs table. If queue delay grows, give the worker more memory
or add worker instances.

## 9. Add the installment-reminder scheduled job

App Platform scheduled jobs run no more frequently than every 15 minutes. The
existing Laravel schedule runs the reminder daily at 08:00 Asia/Manila, so call
the command directly rather than using schedule:run.

Create another Job component from the same repository:

| Setting | Value |
| --- | --- |
| Component type | Job |
| Trigger | On a schedule |
| Command | php artisan notifications:send-installment-reminders |
| Cron | 0 8 * * * |
| Time zone | Asia/Manila |
| Environment | Same application and database variables |

After deployment, inspect the job invocation log at 08:00 and confirm the
expected notifications are created.

## 10. Add the Cloudflare domain

1. In App Platform, add example.com as the primary custom domain and www.example.com as an alias if needed.
2. Copy the DNS records DigitalOcean requests.
3. In Cloudflare DNS, create the records exactly as requested. Keep Cloudflare
   proxying disabled until App Platform finishes domain verification if its
   instructions require DNS-only records.
4. After verification and certificate issuance, enable Cloudflare proxying if
   desired.
5. Enable Cloudflare **Always Use HTTPS** and DNSSEC.
6. Update APP_URL to https://example.com, then redeploy.

App Platform owns origin TLS. Do not create a Droplet, install Nginx, or install
a Cloudflare Origin Certificate for this deployment model.

## 11. Set monitoring, backups, and alerts

Configure:

- App Platform alerts for deployment and domain failures;
- an external HTTPS uptime check;
- Managed MySQL alerts and backup retention;
- alerts or regular checks for worker errors, failed jobs, and scheduled job
  failures;
- R2 access/error monitoring where available; and
- a production email/channel for all alerts.

Test how to restore a managed-database backup and how to validate private R2
uploads. A redeploy is not a backup.

## 12. First-release verification

After the pre-deploy migration succeeds and the app is live:

- [ ] Open the temporary App Platform domain and verify a 200 response.
- [ ] Confirm the web-service logs contain no startup errors.
- [ ] Confirm APP_DEBUG is false and no secret is committed to Git.
- [ ] Test login, an admin flow, and a customer order.
- [ ] Dispatch and process a test queued job; confirm the worker log shows it.
- [ ] Confirm the daily reminder job is scheduled for 08:00 Asia/Manila.
- [ ] Verify the public-media bucket is available only through its custom domain and its `r2.dev` URL is disabled.
- [ ] Test a private payment screenshot upload and authorized retrieval after a redeploy.
- [ ] Verify the custom domain, HTTPS, and Cloudflare DNSSEC.
- [ ] Confirm that alerts are received by a real owner.

## Everyday deployment

Use a protected production branch:

1. Develop and test on a feature branch.
2. Open and review a pull request.
3. Merge to the production branch.
4. App Platform runs the pre-deploy migration, builds the app, and deploys the
   web service and worker.
5. Review the deployment log, web log, worker log, and health check.

If a release must be rolled back, roll back application code through App
Platform. Plan database recovery separately; migrations are not automatically
safe to reverse.
