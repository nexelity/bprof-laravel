# BProf - Laravel

[![Build Status](https://travis-ci.com/nexelity/bprof-laravel.svg?branch=main)](https://travis-ci.com/nexelity/bprof-laravel)
[![codecov](https://codecov.io/gh/nexelity/bprof-laravel/branch/main/graph/badge.svg?token=ZQZQZQZQZQ)](https://codecov.io/gh/nexelity/bprof-laravel)

[![Latest Stable Version](https://poser.pugx.org/nexelity/bprof-laravel/v)](//packagist.org/packages/nexelity/bprof-laravel)
[![Total Downloads](https://poser.pugx.org/nexelity/bprof-laravel/downloads)](//packagist.org/packages/nexelity/bprof-laravel)
[![License](https://poser.pugx.org/nexelity/bprof-laravel/license)](//packagist.org/packages/nexelity/bprof-laravel)

## 📚 Description
A Laravel wrapper package for the [BProf](https://github.com/nexelity/bprof-ext) PHP profiler.

Uncover bottlenecks, memory hogs, and performance insights in your Laravel PHP code with BProf! A heavy adaptation of the renowned [XHProf](https://github.com/phacility/xhprof) library, fine-tuned for modern PHP applications.

## 🌟 Features

- 🔍 Detailed function-level insights
- 📈 Real-time application performance monitoring
- 📊 Easy-to-visualize data
- ⚙️ Easy integration
- 🚀 Speed up your PHP applications!

## ⚙️ Pre-requisites
1. PHP `>=8.0`
2. Laravel `>=8.0`
3. Linux or macOS (Windows is not supported)
4. `bprof-ext` php extension installed. See [here](https://github.com/nexelity/bprof-ext).
5. `bprof-viewer` installed and running. See [here](https://github.com/nexelity/bprof-viewer/).
6. Eloquent compatible database (MySQL, PostgreSQL, SQLite, SQL Server)


##  🚀 Installation

1. Install the wrapper
```bash
composer require nexelity/bprof-laravel
```

2. Publish the config file
```bash
php artisan vendor:publish --provider="Nexelity\Bprof\BprofLaravelServiceProvider"
````

3. Add the following to your .env file and modify as needed
```
BPROF_ENABLED=true
BPROF_VIEWER_URL=http://localhost:8080
BPROF_SERVER_NAME=My App
BPROF_DB_CONNECTION=mysql
BPROF_DB_TABLE=bprof_traces
```

4. Run migrations, this will create the traces table.
```bash
php artisan migrate
```

5. Clear config cache
```
php artisan config:clear
```

6. Add the middleware to your app/Http/Kernel.php
```php
protected $middleware = [
    ...
    \Nexelity\Bprof\Http\Middleware\BprofMiddleware::class,
];
```

7. Start profiling!

## 🖥️ Artisan commands
```bash
# Truncate the traces table
php artisan bprof:truncate

# Trim traces older than X hours
php artisan bprof:trim {ageInHours}
```
