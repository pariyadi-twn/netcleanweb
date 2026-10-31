# NetClean Laundry SaaS

Laravel modular monolith untuk operasional laundry multi-tenant. Spesifikasi dan status implementasi berada pada [backlog](docs/todo.md), [PRD](docs/prd.md), dan [TRD](docs/trd.md).

## Toolchain

- PHP 8.4.27 dengan `pdo_pgsql`, Composer 2.9.3; dependency pasti mengikuti `composer.lock`.
- Node 26.5.0 dan npm 12.0.1; dependency web mengikuti `package-lock.json`.
- PostgreSQL 18, Redis queue/cache terpisah, dan object storage S3-compatible untuk lingkungan lokal/test melalui Compose; image dipin ke digest.
- Flutter stable 3.44.8/Dart 3.12.2 dipin untuk proyek staf yang akan dibuat di `mobile/` pada T074. Root Laravel tetap di direktori ini.

## Menyiapkan lingkungan lokal

```bash
composer install
npm ci --ignore-scripts
php artisan project:prepare --no-interaction
docker compose --env-file .env.infrastructure up -d --wait
```

Command persiapan membuat `.env.infrastructure`, `.env.local`, `.env.testing`, dan konfigurasi S3 lokal dengan kredensial acak. File yang sudah ada dipertahankan. `.env` pengguna tidak diubah; pilih `--env=local` secara eksplisit untuk konfigurasi proyek yang dihasilkan. Pada host yang akses Docker-nya memerlukan hak tambahan, gunakan `sudo` untuk command Docker.

Command memeriksa permission aktual sebelum menulis kredensial dan memerlukan mode file 0600 serta direktori infrastruktur 0700. Filesystem yang mengabaikan `chmod`, seperti NTFS yang belum dipasang dengan opsi `permissions`, menghasilkan exit code gagal tanpa menulis secret baru. Perbaiki mount serta ownership file setup bila diperlukan, lalu jalankan kembali command. Hash isi environment yang sudah ada tetap dipertahankan pada pengulangan setup.

Layanan hanya dipublikasikan ke loopback: PostgreSQL `15432`, Redis cache `16379`, Redis queue `16380`, dan S3 `18333`. Database `netclean` untuk lokal dan `netclean_test` untuk pengujian terpisah. Data object storage lokal berada di `storage/app/infrastructure/objects` pada partisi proyek. Seluruh file kredensial dan data runtime tersebut diabaikan Git.

Role `netclean_runtime` tidak mempunyai hak DDL, superuser, atau BYPASSRLS. Jalankan migration dengan koneksi khusus:

```bash
php artisan migrate --database=pgsql_migration --env=local --no-interaction
php artisan serve --env=local
npm run dev
```

## Pengujian dan CI

```bash
composer check
npm audit
npm run lint:api
npm run build
vendor/bin/pint --dirty --format agent
```

`composer check` memvalidasi manifest, menjalankan PHPUnit pada PostgreSQL test, lalu mengaudit dependency. Test infrastruktur memeriksa PostgreSQL 18/role runtime, pemisahan Redis, serta penulisan/pembacaan S3 dan penolakan akses anonim. Test database tidak diarahkan ke database lokal aplikasi. Workflow `.github/workflows/ci.yml` menjalankan pemeriksaan backend dan build web pada runner terpisah.

Kontrak rancangan [OpenAPI](docs/openapi.yaml) divalidasi melalui `npm run lint:api`. Daftar endpoint di kontrak bukan bukti endpoint telah diimplementasikan; fase dan task modul tetap menjadi acuan.

## Proses runtime

```bash
php artisan horizon --env=local
php artisan schedule:work --env=local
```

Horizon memisahkan kelas payments, operations, notifications, dan exports. Pekerjaan pendek menggunakan reservation 90 detik dan worker timeout 35 detik; exports memakai 360/305 detik. Dashboard deny by default sampai izin operator platform ditetapkan. Scheduler cukup satu pelaksana; job inti tetap memerlukan idempotensi.

Dockerfile menyediakan target `app` (PHP-FPM/OPcache) dan `web` (Nginx). API, worker, dan scheduler memakai image app yang sama dengan command berbeda:

```bash
docker compose --env-file .env.infrastructure --profile runtime up -d --build
```

Profile runtime adalah konfigurasi lokal. Staging/production menginjeksi secret sendiri, menggunakan TLS, debug nonaktif, dan migration terpisah; jangan menyalin kredensial lokal. `/up` hanya probe boot Laravel. Bukti upgrade/rollout/rollback serta kesiapan produksi berada pada T079, bukan disimpulkan dari tersedianya Dockerfile.

---

## Laravel framework

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
