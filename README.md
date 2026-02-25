## Template api

# composer install

# composer require laravel/sanctum

# php artisan key:generate

# php artisan migrate

# php artisan db:seed

# php artisan storage:link

> **Note:** Broadcasting channels must be manually defined in `routes/channels.php`.
> By default the following channels are registered:
>
> - `App.Models.User.{id}` — private, user matches ID
> - `users` — private, super-admin and admin only
> - `notifications.{id}` — private, user matches ID
>
> When adding new private or presence channels, always add an authorization callback in `channels.php` or Laravel will return a 403
> error when clients attempt to subscribe to the channel. For example, if you have a private channel named `orders.{orderId}`, you should add the following code to `channels.php`:
