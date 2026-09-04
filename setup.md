# Production setup: DigitalOcean App Platform

This application will run on DigitalOcean App Platform instead of a self-managed Droplet. App Platform builds and deploys the Laravel service from Git, terminates HTTPS, serves the web application, captures logs, and replaces unhealthy instances. There is no server to configure with Nginx, PHP-FPM, Supervisor, or Cron.

## Architecture

~~~text
Users
  |
  v
Cloudflare
  - Registrar, authoritative DNS, DNSSEC, CDN, edge security
  |
  v
DigitalOcean App Platform
  - Laravel web service
  - Laravel queue worker
  - Scheduled reminder job
  |
  +--> DigitalOcean Managed MySQL
  |
  +--> Cloudflare R2
       - Public media bucket for logos and GCash QR codes
       - Private bucket for GCash payment screenshots
~~~

## Components

| Component | Responsibility | Required |
| --- | --- | --- |
| App Platform web service | Laravel HTTP application | Yes |
| App Platform worker | php artisan queue:work database | Yes, while using database queues |
| App Platform scheduled job | Daily installment reminders | Yes |
| Managed MySQL | Production database, backups, and recovery | Yes |
| Cloudflare R2 | Durable public media and private customer uploads | Yes before accepting uploads |
| Cloudflare | Domain, DNS, DNSSEC, and edge controls | Recommended |

Use a **managed MySQL database**, not App Platform's dev database. App Platform dev databases support PostgreSQL only; managed databases are the appropriate production option for MySQL.

## Critical platform constraint: local files are temporary

App Platform instances have no persistent local filesystem. Deployments and instance replacement discard files written to storage/. Therefore the current FILESYSTEM_DISK=local setting cannot be used for GCash screenshots or any other customer upload in production.

Before launching uploads, configure Laravel to use two Cloudflare R2 buckets through Laravel's S3-compatible filesystem driver. This requires:

1. Installing Laravel's S3 filesystem dependency if it is not already present.
2. Creating one public-media bucket and one private-upload bucket.
3. Creating an R2 API token with Object Read & Write access limited to those two buckets.
4. Connecting only the public-media bucket to a production custom domain and disabling its `r2.dev` development URL.
5. Keeping the private-upload bucket without a public domain or public-development URL.
6. Setting the R2 environment variables in App Platform as encrypted secrets.
7. Serving payment screenshots through the existing authorized Laravel response route, never through a bucket URL.
8. Testing public media, authorized screenshot access, denied screenshot access, and a redeploy before accepting customer payments.

## Deployment model

The source of truth is the Git repository. A tested commit merged into the production branch triggers an App Platform deployment.

App Platform's Laravel build configuration must:

- install Composer dependencies;
- install Node dependencies;
- build Vite assets; and
- start Laravel with heroku-php-apache2 public/.

Database migrations must be run as a **pre-deploy job**, so a web service is never serving code that expects a schema that is not yet present. Design migrations to be backwards-compatible with the previously deployed release when possible.

## Queue and scheduler

The current application uses Laravel's database queue. Configure a dedicated App Platform Worker to run:

~~~text
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
~~~

After each deployment, App Platform will replace the worker with the new release. Review worker logs and failed jobs routinely.

Do not use the Droplet cron pattern schedule:run every minute: App Platform scheduled jobs have a minimum 15-minute interval. This app only needs a daily reminder, so configure a scheduled job that calls the command directly:

~~~text
php artisan notifications:send-installment-reminders
~~~

Schedule it for 08:00 in the Asia/Manila time zone.

## Cloudflare and custom domain

1. Keep Cloudflare as registrar and authoritative DNS.
2. Add the custom domain in App Platform and follow its DNS verification instructions.
3. Create the required Cloudflare DNS records.
4. Let App Platform manage the origin TLS certificate; do not install a Cloudflare Origin Certificate or configure Nginx.
5. Enable Cloudflare DNSSEC and Always Use HTTPS.
6. Configure Cloudflare rate limiting / WAF rules for login and password-reset routes where your plan permits.

Avoid requiring Full (strict) with a Cloudflare Origin Certificate for this architecture. App Platform handles the HTTPS origin; follow its custom-domain and TLS flow instead.

## Production environment variables

Set environment variables in **App Platform Settings**, not in a committed .env file. Mark credentials, private keys, APP_KEY, mail passwords, R2 keys, and payment-provider secrets as encrypted/secret values.

At a minimum, set:

~~~dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<managed-mysql-host>
DB_PORT=<managed-mysql-port>
DB_DATABASE=<database-name>
DB_USERNAME=<database-user>
DB_PASSWORD=<database-password>

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

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

Use DigitalOcean's database bindable environment variables where available instead of copying connection values. Set the database CA/certificate options required by the managed database connection.

## Security responsibilities

App Platform removes operating-system maintenance, but the application remains responsible for:

- keeping Laravel, Composer dependencies, and front-end dependencies updated;
- using APP_DEBUG=false and preventing secret commits;
- authorization for customer data and payment screenshots;
- strong admin accounts and account recovery;
- rate limits on authentication-sensitive routes;
- reviewing deployment, application, worker, and database logs;
- testing backup recovery; and
- limiting database and R2 credentials to the least access needed.

## Backups and recovery

Managed MySQL and R2 are persistent services, but configure and test recovery rather than assuming a deployment is a backup.

- Enable and review Managed Database backups and retention.
- Export a tested database backup before destructive migrations or data work.
- Keep private uploads in R2 and verify their retention/versioning policy.
- Record how to restore the database, reconfigure App Platform secrets, and verify admin/customer workflows.
- Rehearse a database and upload restore at least quarterly.

## Monitoring and alerts

Configure App Platform alerts for deployment failures and application incidents. Monitor:

- App Platform deployment status and web-service logs;
- worker health, queue failures, and delayed notifications;
- managed MySQL capacity, connections, and backup status;
- R2 storage and access failures;
- external HTTPS uptime; and
- Cloudflare security events.

Route alerts to an address or channel someone actively monitors.

## Scaling path

Start with one web-service instance and one worker. Scale based on evidence:

1. Increase web-service resources or instances if response times, CPU, or memory show sustained pressure.
2. Increase worker resources or count if queue delay grows.
3. Add Redis as the queue/cache backend if database queues become a bottleneck.
4. Increase managed MySQL capacity or add replicas only when database metrics demonstrate the need.
5. Keep uploads in R2 from the beginning; do not rely on container storage.

## Launch checklist

- [ ] App Platform web service deploys the selected production branch
- [ ] Vite assets are built during the App Platform build
- [ ] Pre-deploy migration job succeeds before web service rollout
- [ ] Managed MySQL is attached as a trusted source and connection tested
- [ ] Queue worker processes a test job
- [ ] 08:00 Asia/Manila reminder job completes successfully
- [ ] Public R2 media and private screenshot access are tested across a redeploy
- [ ] All sensitive App Platform variables are encrypted and APP_DEBUG=false
- [ ] Custom domain, HTTPS, Cloudflare proxying, and DNSSEC are verified
- [ ] Deployment-failure, uptime, database, and queue alerts reach an owner
- [ ] Database and R2 recovery procedure has been tested
