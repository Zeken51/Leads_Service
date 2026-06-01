# Arquitectura: leads-service

## Stack

| Capa | Tecnología | Versión |
|---|---|---|
| Framework | Laravel | 13.7.0 |
| Frontend SPA | Inertia.js + Vue 3 | ^2.0 / ^3.4 |
| Estilos | Tailwind CSS | ^3.2 |
| Build tool | Vite | ^8.0 |
| Base de datos | MySQL | 8.0.44 |
| Auth web | Laravel Breeze | ^2.4 |
| Auth API | Laravel Sanctum | ^4.0 |
| HTTP client | Axios | incluido en Breeze |

---

## Principio de independencia

Este microservicio **no depende de ningún otro repositorio**. No accede a bases
de datos externas ni importa código de otros proyectos. Se comunica con sistemas
externos únicamente a través de sus propios endpoints API (contrato de entrada)
o de webhooks (contrato de salida, en fases futuras).

---

## Capas de la aplicación

```
leads-service/
├── app/
│   ├── Domain/              ← lógica de negocio por módulo
│   │   ├── Leads/           ← entidad Lead, reglas, eventos
│   │   ├── Pipeline/        ← PipelineStage, reglas de transición
│   │   └── Tenants/         ← scoping multi-tenant
│   ├── Services/            ← orquestadores de casos de uso
│   ├── DTOs/                ← objetos de transferencia de datos (agnósticos al ORM)
│   ├── Actions/             ← acciones atómicas (un caso de uso = una clase)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/        ← controladores Breeze (web)
│   │   │   └── Api/V1/      ← controladores API versionados
│   │   ├── Middleware/      ← tenant scope, idempotencia, rate limit
│   │   └── Requests/        ← Form Requests por endpoint
│   ├── Models/              ← modelos Eloquent
│   └── Providers/
├── resources/
│   └── js/
│       ├── Pages/           ← vistas Inertia por módulo
│       ├── Components/      ← componentes Vue reutilizables
│       └── Layouts/         ← layouts de página
├── routes/
│   ├── web.php              ← rutas Inertia autenticadas
│   ├── auth.php             ← rutas de autenticación web
│   └── api.php              ← rutas API v1
└── docs/                    ← documentación del proyecto
```

---

## Clientes externos

```
┌──────────────────┐     POST /api/v1/leads      ┌─────────────────────┐
│  ZendVacations   │ ──────────────────────────→ │                     │
│ (source_system:  │     Bearer token             │   leads-service     │
│  zend_vacations) │                              │                     │
└──────────────────┘                              │  ┌───────────────┐  │
                                                  │  │ API v1        │  │
┌──────────────────┐     POST /api/v1/leads       │  │ /api/v1/...   │  │
│   Web Forms /    │ ──────────────────────────→ │  └───────────────┘  │
│  otros sistemas  │     Bearer token             │                     │
│ (source_system:  │                              │  ┌───────────────┐  │
│   web_form)      │                              │  │ Panel Inertia │  │
└──────────────────┘                              │  │ (interno)     │  │
                                                  │  └───────────────┘  │
┌──────────────────┐    GET/PATCH /api/v1/leads   │                     │
│  Agentes via     │ ──────────────────────────→ └─────────────────────┘
│  Panel interno   │     Session (Breeze)
└──────────────────┘
```

---

## Flujo de petición API

```
Cliente externo
    │
    │ POST /api/v1/leads
    │ Authorization: Bearer {token}
    │ Idempotency-Key: {uuid}
    ↓
Laravel Router (api.php)
    │
    ├── Middleware: auth:sanctum  → extrae tenant_id del token
    ├── Middleware: idempotency   → verifica/guarda IdempotencyKey
    ├── Middleware: throttle      → rate limiting por tenant
    ↓
Controller (Api/V1/LeadController)
    │
    ↓
Form Request (validación)
    │
    ↓
Action / Service (lógica de negocio)
    │
    ↓
Domain (Lead, PipelineStage, LeadActivityLog)
    │
    ↓
Response JSON (API Resource)
```

## Flujo de petición web (Inertia)

```
Navegador → Laravel Router → Middleware (auth) → Controller → Inertia::render()
                                                                    ↓
                                                            Vue Component (SPA)
```

---

## Multi-tenancy

**Campo:** `tenant_id` (UUID) presente en todas las entidades de negocio.

El `tenant_id` proviene del token de autenticación, **nunca del body** de la petición.
Se aplica automáticamente mediante un Global Scope o trait `HasTenant` para garantizar
que cada query esté filtrada por tenant sin depender de que el developer lo recuerde.

```
tenant_id ──→ (N) Lead
tenant_id ──→ (N) PipelineStage
tenant_id ──→ (N) LeadNote
tenant_id ──→ (N) LeadActivityLog
tenant_id ──→ (N) IdempotencyKey
```

> **Actualización fase 6.2:** El campo fue renombrado de `organization_id` a `tenant_id`
> para claridad semántica y alineación con el contexto multi-tenant del microservicio.

---

## Idempotencia

Las operaciones de escritura vía API aceptan el header `Idempotency-Key: {uuid}`.
El microservicio almacena la respuesta original en `IdempotencyKey` con un TTL de
24 horas (configurable). Si la misma clave se repite dentro del TTL, se retorna
la respuesta almacenada sin re-ejecutar la operación.

La unicidad del lead también se garantiza a nivel de datos mediante:
`(tenant_id, source_system, external_reference_id)` — único cuando `external_reference_id` no es nulo.

---

## Usuarios como referencias externas

Los usuarios asignados a leads **no se almacenan como entidad propia** en este servicio.
Se referencia al usuario externo por `assigned_user_id` (ID opaco del sistema del cliente),
y se capturan snapshots en el momento de la asignación:

- `assigned_user_name_snapshot`
- `assigned_user_email_snapshot`
- `assigned_user_provider` (ej: `zend_platform`, `leads_service`)

Esto garantiza que el historial del lead permanezca intacto aunque el usuario
sea modificado o eliminado en el sistema de origen.

---

## Portabilidad tecnológica (NestJS)

Los contratos API están versionados (`/api/v1`). Los DTOs son agnósticos al ORM.
La lógica de negocio vive en `Domain/` y `Actions/`, separada de la capa HTTP.
Ver `docs/domain-model.md` sección 10 para el detalle completo.

---

## Base de datos

- Schema: `leads_service`
- Host: `127.0.0.1:3308`
- Tablas base actuales: `users`, `sessions`, `cache`, `jobs`, `migrations`
- Tablas de dominio: pendientes de implementación (fase 6.3)

## Consideraciones de seguridad

- Autenticación web: sesión + CSRF (Breeze)
- Autenticación API: tokens Sanctum (Bearer)
- Rate limiting aplicado a rutas API por tenant
- Validación estricta con Form Requests
- Global Scope de tenant obligatorio en todos los modelos de negocio
