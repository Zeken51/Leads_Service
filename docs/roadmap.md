# Roadmap — leads-service

## Fase 6.1 — Fundación técnica ✓

**Objetivo:** Validar y documentar la base antes de construir.

- [x] Proyecto Laravel creado
- [x] Conexión a base de datos verificada (`leads_service`)
- [x] Migraciones base ejecutadas (users, cache, jobs)
- [x] Breeze + Inertia + Vue + Tailwind verificados
- [x] Login funcional en panel interno
- [x] Documentación inicial creada (`/docs`)
- [x] README del proyecto
- [x] Estructura de dominio propuesta

---

## Fase 6.2 — Análisis y diseño del dominio ✓

**Objetivo:** Delimitar responsabilidades, entidades y contratos antes de implementar.

- [x] Responsabilidades del microservicio definidas (qué sí / qué no)
- [x] Entidades del dominio documentadas
- [x] Campos detallados de cada entidad (incluyendo snapshots de usuarios externos)
- [x] Estados comerciales definidos (`status` + `stage`)
- [x] Flujo funcional base documentado (7 flujos)
- [x] Reglas del pipeline documentadas
- [x] Límites con clientes externos definidos
- [x] Multi-tenancy: `tenant_id` como campo universal
- [x] Idempotencia: comportamiento preliminar documentado
- [x] Preparación para migración NestJS documentada
- [x] `docs/domain-model.md` creado

---

## Fase 6.3 — Cierre de contratos API ✓ (actual)

**Objetivo:** Documentar contratos API v1 completos y consistentes antes de implementar.

- [x] Base URL de producción definida (`https://leads.zendlogic.com/api/v1`)
- [x] Headers globales estandarizados (`Authorization`, `Content-Type`, `X-Request-ID`, `Idempotency-Key`)
- [x] Paginación definida: page-based (`page` + `per_page`)
- [x] Formato de respuesta estándar cerrado (con `request_id` en todas las respuestas)
- [x] `request_id` como campo universal de trazabilidad
- [x] 11 endpoints documentados con request/response completos
- [x] Filtros de `GET /leads` completos (`overdue`, `search`, `followup_from`, `followup_to`, etc.)
- [x] `GET /leads/{id}` incluye notas y actividad reciente (últimos 10)
- [x] `POST /leads/{id}/contact` con `contact_channel` documentado
- [x] `external_reference_id` documentado como campo genérico; mapeo `quote_id` aclarado como específico de ZendVacations
- [x] Rate limiting documentado por tipo de cliente
- [x] Política de versionamiento documentada
- [x] `docs/api-auth.md` creado
- [x] `docs/api-errors.md` creado
- [x] `docs/idempotency.md` creado
- [x] `docs/api-contracts-v1.md` reescrito y cerrado

---

## Fase 6.4 — Implementación del dominio base ✓

**Objetivo:** Crear la estructura de carpetas, modelos y migraciones principales.

- [x] Estructura de carpetas `app/Domain/` creada
- [x] `TenantContext`, `TenantScope` y trait `HasTenant` implementados
- [x] Enums: `LeadStatus`, `LeadPriority`, `LeadEvent`, `CauserType`, `ContactChannel`
- [x] Migración + Modelo `PipelineStage`
- [x] Migración + Modelo `Lead` (todos los campos del dominio, índices, soft delete)
- [x] Migración + Modelo `LeadNote`
- [x] Migración + Modelo `LeadActivityLog` (inmutable, sin `updated_at`)
- [x] Migración + Modelo `IdempotencyKey` (con `request_hash` y `lead_id`)
- [x] Migraciones ejecutadas y verificadas en base de datos
- [ ] Seeders con dos tenants, pipeline stages y leads de prueba

---

## Fase 6.5 — Auth base + Middleware + RequestContext ✓

**Objetivo:** Implementar la base de autenticación, identificación de requests y contexto tenant-aware.

- [x] Sanctum instalado y configurado
- [x] Migraciones: `personal_access_tokens`, `add_tenant_id_to_users`, `tenant_api_clients`
- [x] Modelo `TenantApiClient` con `HasApiTokens` y campos `source_system`/`source_channel`
- [x] Modelo `User` actualizado con `tenant_id` y `HasApiTokens`
- [x] Enum `ApiAbility` con capabilities completas + helpers `forAgent()`, `forExternalCreator()`
- [x] `RequestContext` — DTO inmutable con request_id, tenant_id, source_system, client, abilities
- [x] Middleware `SetRequestId` — genera/preserva `X-Request-ID`, registra contexto inicial
- [x] Middleware `SetTenantContext` — extrae tenant_id del cliente autenticado, activa `TenantContext`
- [x] Middleware `EnsureJsonApiHeaders` — fuerza Accept:json, valida Content-Type en writes
- [x] `ApiResponse` — helper estático para respuestas consistentes con `request_id`
- [x] Exception handler — formatea todos los errores API con envelope `message/errors/request_id`
- [x] `routes/api.php` registrado con middleware API
- [x] `POST /api/v1/auth/login` funcional
- [x] `POST /api/v1/auth/logout` funcional
- [x] `GET /api/health` funcional
- [x] Fix: `tokenable_id` cambiado a varchar(255) para soportar UUIDs de TenantApiClient
- [x] Fix: validación `is_active` en SetTenantContext para clientes API inactivos
- [x] Fix: rechazo de User sin `tenant_id` (403) en rutas protegidas
- [x] Tests: 20 tests Feature (health, auth, middleware, is_active, tenant)
- [ ] Middleware de idempotencia (fase 6.6)
- [ ] Rate limiting por tenant (fase 6.6)

---

## Fase 6.6 — Pipeline y acciones de estado

**Objetivo:** Implementar el flujo comercial del lead.

- [ ] `GET /api/v1/pipeline/stages` con `leads_count`
- [ ] `PATCH /api/v1/leads/{id}/stage` con reglas de transición
- [ ] `PATCH /api/v1/leads/{id}/assign`
- [ ] `PATCH /api/v1/leads/{id}/followup`
- [ ] `POST /api/v1/leads/{id}/contact` con `contact_channel`
- [ ] `PATCH /api/v1/leads/{id}/won`
- [ ] `PATCH /api/v1/leads/{id}/lost`
- [ ] Activity Log automático en cada acción
- [ ] Actualización automática de `last_contact_at`

---

## Fase 6.7 — Notas y actividad

**Objetivo:** Registro histórico completo.

- [ ] `GET /api/v1/leads/{id}/notes` paginado
- [ ] `POST /api/v1/leads/{id}/notes`
- [ ] `GET /api/v1/leads/{id}/activity` paginado con filtro por `event`
- [ ] Agregar nota dispara actualización de `last_contact_at`

---

## Fase 6.8 — Panel interno Inertia

**Objetivo:** UI funcional para gestión de leads.

- [ ] Definir estructura de Pages/Components por módulo
- [ ] Vista: lista de leads con filtros y paginación
- [ ] Vista: detalle de lead (notas, actividad, pipeline)
- [ ] Vista: kanban del pipeline por stage
- [ ] Componentes Vue reutilizables del dominio

---

## Fase 6.9 — Calidad y producción

**Objetivo:** Preparar para entorno productivo.

- [ ] Tests unitarios del dominio (Actions, Services)
- [ ] Tests de aislamiento multi-tenant
- [ ] Documentación API con Scribe o similar
- [ ] Variables de entorno documentadas completamente
- [ ] Verificar entorno de deploy: PHP NTS (no ZTS)
- [ ] Configurar queue worker + job de limpieza de `idempotency_keys` expirados
- [ ] Revisión de seguridad (OWASP básico)

---

## Notas sobre prioridades

- Las fases 6.4 y 6.5 son bloqueantes para el resto.
- La idempotencia se implementa en fase 6.5 antes de exponer a sistemas externos.
- El panel Inertia (6.8) puede desarrollarse en paralelo con 6.6/6.7.
- Los tests de aislamiento multi-tenant (6.9) deben hacerse antes de producción.
