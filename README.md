# Ahorro Semanal

Aplicación web para administrar grupos de ahorro semanal entre amigos. El backend es una API REST en Laravel 11 y el frontend es una SPA en Quasar / Vue 3.

## Arquitectura

- `api/`: API Laravel 11, MySQL, Sanctum (tokens Bearer), políticas y Form Requests.
- `app/`: interfaz Quasar con Vue 3 Composition API, Pinia, Vue Router y Axios.

Modelo principal:

- `people`: datos personales.
- `users` + `roles`: cuentas de acceso (`admin` o `socio`) vinculadas a una persona.
- `savings_cycles`: ciclo de ahorro y su estado (`abierto`, `en_proceso`, `cerrado`, `finalizado`).
- `savings_cycle_members`: participación, orden de entrega y cuota pactada.
- `savings_turns`: calendario de N turnos para N socios.
- `savings_contributions` + `savings_contribution_payments`: obligación semanal y abonos.
- `savings_disbursements`: entregas del fondo.
- `audit_logs`: operaciones sensibles.

El monto bruto de cada turno es `cuota_semanal × número_de_socios`. No hay préstamos, intereses, multas ni cobros automáticos.

## Requisitos

- PHP 8.2+ con extensión PDO MySQL.
- Composer 2.
- MySQL 8 en `127.0.0.1:3306`.
- Node.js 22+ y npm.
- Quasar CLI no es obligatorio; el proyecto usa el CLI local.

## Configuración de MySQL

Usuario local usado en desarrollo: `root` / `root`, puerto `3306`.

Cree las bases de datos:

```sql
CREATE DATABASE ahorro_semanal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ahorro_semanal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Backend (`api/`)

```bash
cd api
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Variables importantes en `.env`:

- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=ahorro_semanal`
- `DB_USERNAME=root`
- `DB_PASSWORD=root`
- `FRONTEND_URL=http://localhost:9000`
- `ADMIN_EMAIL`, `ADMIN_PASSWORD` y datos de la persona administradora inicial

Credenciales de desarrollo por defecto (no usar en producción):

- Correo: `admin@ahorro-semanal.test`
- Contraseña: `password`

Si `SEED_DEMO=true` y el entorno no es `production`, también se crean cinco socios de ejemplo (`socio1@ahorro-semanal.test` … `socio5@ahorro-semanal.test`, contraseña `password`) y un ciclo abierto llamado `Ciclo demo 2026`.

## Frontend (`app/`)

```bash
cd app
copy .env.example .env
npm install
npm run dev
```

La URL de la API se configura en `app/.env`:

```env
API_URL=http://127.0.0.1:8000
```

La interfaz queda en `http://localhost:9000`.

## Pruebas

```bash
cd api
php artisan test
```

Las pruebas usan la base `ahorro_semanal_test`.

## Flujo básico de un ciclo

1. El administrador inicia sesión.
2. Crea las personas de los socios y, si van a entrar al sistema, les asigna una cuenta con rol `socio`.
3. Crea un ciclo en estado `abierto`.
4. Asocia socios, define el orden de entrega y revisa la vista previa del calendario.
5. Inicia el ciclo. A partir de ese momento no se pueden cambiar socios, cuota ni orden.
6. Cada semana registra los aportes y, con una acción explícita, la entrega del fondo al beneficiario del turno.
7. Cuando todos los turnos y entregas están completos, finaliza el ciclo. Un cierre anticipado conserva el historial.

El socio solo consulta su panel, sus ciclos, su cuota, su posición y las fechas que le corresponden.


API: http://127.0.0.1:8000
Frontend: http://localhost:9002
Entra con admin@ahorro-semanal.test / password.