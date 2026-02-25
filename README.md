## React Template API

### Setup

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

### Running the App

```bash
# Terminal 1 — Reverb WebSocket server
php artisan reverb:start

# Terminal 2 — Queue worker (--sleep=0 for faster dev, use --sleep=3 in production)
php artisan queue:work --sleep=0
```

### Broadcasting

All events use `ShouldBroadcast` and are processed through the queue.

> **Note:** `ShouldBroadcast` requires the queue worker to be running.
> The delay you see in development is the queue polling interval.
> Use `--sleep=0` in dev for near-instant broadcasting.
> In production use `--sleep=3` with Supervisor.

> **Note:** Broadcasting channels must be manually defined in `routes/channels.php`.
> When adding new private or presence channels, always add an authorization
> callback or Laravel will return a 403 when clients attempt to subscribe.

Currently registered channels:

| Channel                | Type    | Access             |
| ---------------------- | ------- | ------------------ |
| `App.Models.User.{id}` | Private | User matches ID    |
| `users`                | Private | super-admin, admin |
| `notifications.{id}`   | Private | User matches ID    |

Example — adding a new private channel:

```php
// routes/channels.php
Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return $user->hasRole('admin');
});
```

### Production (Linux)

Use Supervisor to keep Reverb and the queue worker running:
in queue:work, the `--sleep` option controls how often the worker checks for new jobs.
In development, use `--sleep=0` for near-instant processing.
In production, use `--sleep=3` to reduce CPU usage or `--sleep=1` for near-instant processing.

```ini
[program:reverb]
command=php /var/www/html/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/reverb.log

[program:queue]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/queue.log
```

Update `.env` for production:

```env
QUEUE_CONNECTION=database
REVERB_HOST=mydomain.com
REVERB_PORT=443
REVERB_SCHEME=https
```
