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

## Fase 6.6 — POST /api/v1/leads ✓

**Objetivo:** Primer endpoint real — creación de leads con idempotencia.

- [x] `POST /api/v1/leads` protegido con auth:sanctum + set.tenant.context
- [x] `CreateLeadRequest` — validación completa del payload
- [x] Resolución de `source_system`/`source_channel` desde cliente o payload
- [x] Idempotencia nivel 1: `Idempotency-Key` header → replay 200 + `Idempotent-Replayed: true`
- [x] Idempotencia nivel 2: unicidad por datos `(tenant_id, source_system, external_reference_id)` → 409
- [x] Race condition manejada con `UniqueConstraintViolationException`
- [x] `CreateLeadAction` — crea Lead + LeadActivityLog en una sola transacción
- [x] `IdempotencyService` — hash de request + store/lookup de registros
- [x] `LeadResource` — respuesta estructurada según contratos
- [x] Activity log `lead_created` con source/channel/external_reference_id
- [x] Tests: 15 tests (auth, validación, source, idempotencia, activity log)
- [x] Respuesta 201 con `request_id` + `idempotent_replay: false`

---

## Fase 6.7 — Endpoints administrativos de gestión de leads ✓

**Objetivo:** Implementar el flujo comercial completo del lead vía API.

### Listado y detalle
- [x] `GET /api/v1/leads` con filtros (status, stage_id, assigned_to, source_system, source_channel, priority, followup_from, followup_to, overdue, search) y paginación page-based
- [x] `GET /api/v1/leads/{id}` con stage, notas (últimas 10) y actividad (últimas 10)

### Acciones de pipeline y estado
- [x] `PATCH /api/v1/leads/{id}/stage` con reglas de transición y stages terminales won/lost
- [x] `PATCH /api/v1/leads/{id}/assign` con snapshots de usuario externo
- [x] `PATCH /api/v1/leads/{id}/followup` con validación de al menos un campo
- [x] `POST /api/v1/leads/{id}/contact` con `contact_channel`, creación automática de nota
- [x] `PATCH /api/v1/leads/{id}/won` con stage terminal, nota opcional, won_at
- [x] `PATCH /api/v1/leads/{id}/lost` con `lost_reason` obligatorio, stage terminal

### Notas y actividad
- [x] `GET /api/v1/leads/{id}/notes` paginado
- [x] `POST /api/v1/leads/{id}/notes` con actualización de `last_contact_at`
- [x] `GET /api/v1/leads/{id}/activity` paginado con filtro por `event`

### Infraestructura
- [x] 7 FormRequests de validación
- [x] 7 Actions de dominio (UpdateLeadStageAction, AssignLeadAction, ScheduleFollowupAction, RegisterLeadContactAction, MarkLeadWonAction, MarkLeadLostAction, CreateLeadNoteAction)
- [x] LeadNoteResource, LeadActivityLogResource
- [x] LeadNoteController, LeadActivityController
- [x] LeadFactory, PipelineStageFactory
- [x] Activity log automático en cada operación
- [x] Abilities/capabilities validadas en cada endpoint
- [x] Aislamiento multi-tenant verificado vía TenantScope + lookup explícito
- [x] 104 tests de la fase (+ 63 previos = 167 total, todos pasando)

### Validación contractual (post-6.7)
- [x] `POST /contact` confirmado como POST (evento/acción, no PATCH de recurso)
- [x] `last_contact_at` en notas documentado como decisión de dominio
- [x] Abilities corregidas: `/followup`, `/contact`, `/won`, `/lost` → `leads:update`; `GET /notes`, `GET /activity` → `leads:read`
- [x] Multi-tenant isolation verificado en todos los lookups explícitos
- [x] Tests de abilities (`LeadAbilitiesTest`) — 22 tests, par correcto por endpoint
- [x] Suite completa: **188 tests pasando**

### Pendiente de fase 6.7+
- [ ] `PATCH /api/v1/leads/{id}` (campos editables: customer, priority, metadata)
- [ ] `DELETE /api/v1/leads/{id}` (archivado — status=archived)
- [ ] `GET /api/v1/pipeline/stages` con `leads_count`
- [ ] Seeders con dos tenants, pipeline stages y leads de prueba

---

## Fix post-6.8 — Validación de reglas terminales ✓

**Objetivo:** Eliminar comportamientos silenciosos y dejar los contratos completamente explícitos.

- [x] `PATCH /stage` con stage terminal rechaza `next_action`/`followup_at` con 422 (antes: ignoraba silenciosamente)
- [x] Documentado: `/stage` vs `/won`/`/lost` como flujos alternativos equivalentes de cierre
- [x] `api-errors.md` con códigos `FOLLOWUP_ON_TERMINAL_STAGE` y `FOLLOWUP_ON_CLOSED_LEAD`
- [x] PipelineRulesTest: 2 tests renombrados (ignores→rejects) + 4 tests nuevos para confirmaciones positivas
- [x] Suite completa: **222 tests pasando**

---

## Fase 6.8 — Consolidación de reglas comerciales del pipeline ✓

**Objetivo:** Asegurar que las operaciones existentes respeten reglas de negocio consistentes.

- [x] `last_contact_at` corregido: solo se actualiza en `/contact` (notas y stage ya no lo actualizan)
- [x] Stages terminales: `next_action`/`followup_at` ignorados al mover a won/lost via `/stage`
- [x] `/followup` bloqueado en leads terminales (won/lost) → 422
- [x] `next_action` obligatorio cuando se provee `followup_at` en `/followup`
- [x] Filtro `overdue` validado: excluye correctamente leads won/lost (solo activos)
- [x] Activity logs: event_data consistente en stage_changed (from/to), contact_registered (channel), followup_scheduled (action+date), won/lost (at+reason)
- [x] `Lead::isOverdue()` excluye terminales correctamente
- [x] Asignación: solo referencias externas, sin depender de usuarios internos
- [x] Stage inicial en creación: usa `is_initial=true` del tenant, o `null` si no hay pipeline configurado
- [x] Documentación actualizada: domain-model.md, api-contracts-v1.md, technical-notes.md (#41–44)
- [x] Tests: `PipelineRulesTest` (30 tests nuevos) + ajustes en LeadStageTest, LeadNotesTest, LeadFollowupTest
- [x] Suite completa: **220 tests pasando**

---

## Fase 6.8 (anterior) — Panel interno Inertia

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
