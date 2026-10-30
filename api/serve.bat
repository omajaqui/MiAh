@echo off
cd /d "%~dp0public"
php -d upload_max_filesize=16M -d post_max_size=16M -S 127.0.0.1:8000 "%~dp0vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
