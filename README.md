# leads-service

Microservicio independiente para gestión de leads y seguimiento comercial.

## Objetivo

Centralizar el ciclo de vida de un lead: captura, pipeline comercial, seguimiento,
notas, activity log y soporte multi-tenant, con panel interno y API autenticada.

## Stack

| Tecnología | Versión | Rol |
|---|---|---|
| Laravel | 13.7.0 | Framework backend |
| Inertia.js | ^2.0 | Bridge SPA para el panel interno |
| Vue 3 | ^3.4 | Framework frontend |
| Tailwind CSS | ^3.2 | Estilos utilitarios |
| Vite | ^8.0 | Build tool frontend |
| MySQL | 8.0.44 | Base de datos |
| Laravel Breeze | ^2.4 | Autenticación web (panel interno) |
| Laravel Sanctum | ^4.0 | Autenticación API (tokens) |

## Estado actual

**Fase 6.1 — Fundación técnica completada**

- Stack instalado y verificado
- Base de datos conectada (`leads_service`)
- Migraciones base ejecutadas
- Login funcional en panel interno
- Documentación inicial generada

Pendiente: dominio, modelos, API endpoints, pipeline, multi-tenant.

Ver [docs/roadmap.md](docs/roadmap.md) para el detalle completo.

## Estructura del proyecto

```
app/
├── Domain/          ← lógica de negocio (propuesta, pendiente de implementar)
│   ├── Leads/
│   ├── Pipeline/
│   └── Organizations/
├── Services/        ← orquestadores de casos de uso
├── DTOs/            ← objetos de transferencia de datos
├── Actions/         ← acciones atómicas
├── Http/
│   ├── Controllers/
│   │   ├── Auth/    ← autenticación web (Breeze)
│   │   └── Api/     ← API endpoints (pendiente)
│   └── Requests/
├── Models/
└── Providers/
docs/
├── brief-microservicio-leads.md
├── architecture.md
├── api-contracts-v1.md
├── roadmap.md
└── technical-notes.md
resources/js/
├── Pages/           ← vistas Inertia
├── Components/      ← componentes Vue
└── Layouts/
```

## Levantar el proyecto localmente

### Requisitos previos

- PHP 8.3
- Composer
- Node.js 22+
- MySQL 8.0 corriendo en puerto 3308

### Pasos

```bash
# 1. Instalar dependencias
composer install
npm install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Ejecutar migraciones
php artisan migrate

# 4. Levantar servidores de desarrollo
# Terminal 1: PHP
php artisan serve

# Terminal 2: Vite (frontend)
npm run dev
```

La aplicación estará disponible en `http://localhost:8000`.

### Atajo (comando compuesto)

```bash
composer dev
```

Levanta en paralelo: PHP server, queue worker, log viewer y Vite.

### Primer usuario de prueba

```bash
php artisan tinker
# En tinker:
\App\Models\User::factory()->create(['email' => 'admin@test.com', 'password' => bcrypt('password')]);
```

## Documentación

| Documento | Descripción |
|---|---|
| [docs/brief-microservicio-leads.md](docs/brief-microservicio-leads.md) | Descripción general y alcance |
| [docs/architecture.md](docs/architecture.md) | Arquitectura técnica y capas |
| [docs/api-contracts-v1.md](docs/api-contracts-v1.md) | Contratos de API (borrador) |
| [docs/roadmap.md](docs/roadmap.md) | Fases e ítems pendientes |
| [docs/technical-notes.md](docs/technical-notes.md) | Decisiones, riesgos y observaciones |

## Variables de entorno relevantes

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=leads_service

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```
