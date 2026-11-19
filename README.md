# Helpdesk UAGRM - Backend API (Laravel)

API RESTful y lógica de negocio para la plataforma de Mesa de Ayuda y Soporte Técnico del Campus Virtual (UAGRM). Desarrollado con **Laravel 8**, **PostgreSQL** y autenticación por tokens vía **Laravel Sanctum**.

---

## 📋 Requisitos Previos

Asegúrate de contar con las siguientes herramientas instaladas en tu entorno de desarrollo:

* **PHP** >= 8.0 (con extensiones habilitadas: `pdo`, `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`, `curl`, `json`)
* **Composer** >= 2.0
* **PostgreSQL** >= 13 (puerto por defecto: `5432`)
* Servidor local o gestor de base de datos (pgAdmin, DBeaver o CLI `psql`)

---

## 🚀 Guía Paso a Paso para Levantar el Backend

### 1. Ubicarse en el directorio del Backend
Abre una terminal y navega hasta la carpeta del proyecto:
```bash
cd backend-helpdesk
```

### 2. Instalar dependencias de Composer
Ejecuta la instalación de paquetes de PHP:
```bash
composer install
```

### 3. Configurar las Variables de Entorno (`.env`)
Si no existe el archivo `.env`, créalo a partir de la plantilla:
```bash
cp .env.example .env
```
*(En Windows PowerShell: `Copy-Item .env.example .env`)*

Genera la clave de cifrado de la aplicación:
```bash
php artisan key:generate
```

Asegúrate de configurar la conexión a PostgreSQL en tu `.env`:
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=helpdesk_db
DB_USERNAME=postgres
DB_PASSWORD=tu_password_aqui
```

> [!NOTE]
> Crea previamente la base de datos `helpdesk_db` en tu servidor PostgreSQL antes de ejecutar las migraciones.

### 4. Ejecutar Migraciones y Poblado de Datos (Seeders)
Ejecuta las migraciones de base de datos y genera los usuarios, roles, prioridades SLA, categorías y tickets de prueba:
```bash
php artisan migrate --seed
```

O si deseas ejecutar los seeders individualmente:
```bash
php artisan db:seed
```

### 5. Iniciar el Servidor de Desarrollo
Inicia el servidor local de Laravel:
```bash
php artisan serve
```
Por defecto, la API estará disponible y respondiendo en:  
👉 **`http://127.0.0.1:8000`** (o `http://localhost:8000`)

---

## 👥 Cuentas de Acceso Preconfiguradas (Semillas)

La base de datos incluye credenciales por defecto para realizar pruebas en todos los niveles del sistema.  
La contraseña para todas las cuentas es: **`password`**

| Rol Institucional | Nombre | Correo Electrónico | Contraseña | Permisos y Alcance |
| :--- | :--- | :--- | :---: | :--- |
| **Director / Admin** | Admin Helpdesk | `admin@helpdesk.com` | `password` | Acceso total: Dashboard, Usuarios, Categorías, Prioridades SLAs, Políticas de Notificación, Autorización de Reasignaciones. |
| **Técnico Soporte** | Juan Soporte | `juan@helpdesk.com` | `password` | Operaciones: Gestión de Tickets, Historial Bot IA, Reportes & KPIs, Solicitudes de Reasignación. |
| **Técnica Soporte** | María Soporte | `maria@helpdesk.com` | `password` | Operaciones: Atención de incidencias, cambio de estados, solicitudes de derivación. |
| **Docente** | Carlos Méndez | `carlos.mendez@moodle.com` | `password` | Solicitante: Apertura y seguimiento de incidencias docentes. |
| **Estudiante** | Estudiante Demo | `estudiante@moodle.com` | `password` | Solicitante: Consultas y requerimientos académicos en el campus virtual. |

---

## 🛠️ Comandos y Procesos Adicionales

* **Ejecutar proceso de recordatorios SLA (Scheduler manual)**:
  Evalúa tickets en espera y sin respuesta para enviar alertas y generar el resumen digest diario:
  ```bash
  php artisan tickets:enviar-recordatorios
  ```
* **Limpiar memoria caché y configuración**:
  ```bash
  php artisan config:clear
  php artisan cache:clear
  php artisan route:clear
  ```
* **Consola Interactiva (Tinker)**:
  ```bash
  php artisan tinker
  ```

---

## 📌 Principales Módulos del Backend

* **Gestión de Tickets & SLAs**: Cálculo de horas de espera, tiempos de primera respuesta (FRT) y resolución (MTTR).
* **Historial de Asignaciones (`ticket_asignaciones`)**: Modelo simplificado que audita la vida del ticket con una sola clave foránea hacia usuarios (`tecnico_id`), control de asignación activa (`activo: bool`) y solicitudes de traspaso (`solicitud_pendiente: bool`).
* **Políticas Globales de Notificación**: Configuraciones centralizadas administradas por la Dirección en la tabla `configuraciones` para el despacho por correo SMTP y bandeja web in-app.
