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

## Fase 6.4 — Implementación del dominio base

**Objetivo:** Crear la estructura de carpetas, modelos y migraciones principales.

- [ ] Crear estructura de carpetas: `app/Domain/`, `app/Services/`, `app/DTOs/`, `app/Actions/`
- [ ] Crear trait `HasTenant` + Global Scope de tenant
- [ ] Migración: `pipeline_stages`
- [ ] Migración: `leads` (todos los campos del dominio)
- [ ] Migración: `lead_notes`
- [ ] Migración: `lead_activity_logs` (con campo `contact_channel`)
- [ ] Migración: `idempotency_keys`
- [ ] Modelo `Lead` + `HasTenant`
- [ ] Modelo `PipelineStage`
- [ ] Modelo `LeadNote`
- [ ] Modelo `LeadActivityLog`
- [ ] Modelo `IdempotencyKey`
- [ ] Seeders con dos tenants y datos de prueba

---

## Fase 6.5 — API base (Auth + CRUD + Middleware)

**Objetivo:** Exponer endpoints básicos de leads vía API autenticada.

- [ ] Configurar `routes/api.php` con prefijo `/api/v1`
- [ ] Middleware: `RequestId` (genera/preserva `X-Request-ID`)
- [ ] Middleware: extracción de `tenant_id` desde token Sanctum
- [ ] Middleware: `IdempotencyMiddleware` (verifica/guarda `IdempotencyKey`)
- [ ] Middleware: rate limiting por tenant
- [ ] `POST /api/v1/auth/login` y `logout`
- [ ] `GET /api/v1/leads` con todos los filtros documentados
- [ ] `POST /api/v1/leads` con idempotencia (2 niveles)
- [ ] `GET /api/v1/leads/{id}` con notas + actividad reciente
- [ ] `PATCH /api/v1/leads/{id}` (campos editables)
- [ ] `DELETE /api/v1/leads/{id}` (archivado)
- [ ] API Resources consistentes con el formato de respuesta estándar
- [ ] Form Requests con validación completa
- [ ] Tests de feature para endpoints
- [ ] `GET /api/health`

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
