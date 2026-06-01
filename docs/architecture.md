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

## Capas de la aplicación

```
leads-service/
├── app/
│   ├── Domain/              ← lógica de negocio por módulo (propuesta)
│   │   ├── Leads/
│   │   ├── Pipeline/
│   │   └── Organizations/
│   ├── Services/            ← servicios de aplicación (orquestadores)
│   ├── DTOs/                ← objetos de transferencia de datos
│   ├── Actions/             ← acciones atómicas (un caso de uso = una clase)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/        ← controladores Breeze (web)
│   │   │   └── Api/         ← controladores API (pendiente)
│   │   ├── Middleware/
│   │   └── Requests/
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
│   └── api.php              ← rutas API (pendiente)
└── docs/                    ← documentación del proyecto
```

## Flujo de petición web (Inertia)

```
Navegador → Laravel Router → Middleware (auth) → Controller → Inertia::render()
                                                                    ↓
                                                            Vue Component (SPA)
```

## Flujo de petición API

```
Cliente externo → Laravel Router (api.php) → Middleware (auth:sanctum)
    → Controller → Service → Domain → Response JSON
```

## Multi-tenancy

Todos los modelos de negocio principales tendrán un campo `organization_id`.
El scoping se aplicará automáticamente a través de un Global Scope o trait
`BelongsToOrganization` para evitar fugas de datos entre tenants.

```
Organization (1) ──→ (N) Lead
Organization (1) ──→ (N) Pipeline
Organization (1) ──→ (N) User
```

## Idempotencia

Las operaciones de escritura vía API aceptarán un header `Idempotency-Key`.
El microservicio almacenará el resultado de la operación por un TTL configurable
y devolverá la respuesta cacheada si la clave se repite.

## Base de datos

- Schema: `leads_service`
- Host: `127.0.0.1:3308`
- Tablas base actuales: `users`, `sessions`, `cache`, `jobs`, `migrations`
- Tablas de dominio: pendientes de implementación

## Consideraciones de seguridad

- Autenticación web: sesión + CSRF (Breeze)
- Autenticación API: tokens Sanctum (Bearer)
- Rate limiting aplicado a rutas API
- Validación estricta con Form Requests
