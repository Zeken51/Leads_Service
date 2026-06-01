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
- [x] Entidades del dominio documentadas (Lead, PipelineStage, LeadNote, LeadActivityLog, IdempotencyKey)
- [x] Campos detallados de cada entidad (incluyendo snapshots de usuarios externos)
- [x] Estados comerciales definidos (`status` + `stage`)
- [x] Flujo funcional base documentado (7 flujos)
- [x] Reglas del pipeline documentadas
- [x] Límites con clientes externos definidos
- [x] Multi-tenancy: `tenant_id` como campo universal (renombrado de `organization_id`)
- [x] Idempotencia: comportamiento documentado (Idempotency-Key + unicidad por datos)
- [x] Preparación para migración NestJS documentada
- [x] Contratos API actualizados con campos completos (`customer`, `source_system`, etc.)
- [x] Glosario de términos creado
- [x] `docs/domain-model.md` creado

---

## Fase 6.3 — Implementación del dominio base

**Objetivo:** Crear la estructura de carpetas, modelos y migraciones principales.

- [ ] Crear estructura de carpetas: `app/Domain/`, `app/Services/`, `app/DTOs/`, `app/Actions/`
- [ ] Migración: `tenants` (si se requiere metadata del tenant)
- [ ] Migración: `pipeline_stages` (con campos: `is_initial`, `is_terminal`, `maps_to_status`)
- [ ] Migración: `leads` (todos los campos del dominio, incluyendo snapshots)
- [ ] Migración: `lead_notes`
- [ ] Migración: `lead_activity_logs`
- [ ] Migración: `idempotency_keys`
- [ ] Modelo `Lead` + trait `HasTenant` + Global Scope
- [ ] Modelo `PipelineStage`
- [ ] Modelo `LeadNote`
- [ ] Modelo `LeadActivityLog`
- [ ] Modelo `IdempotencyKey`
- [ ] Seeders con datos de prueba por tenant

---

## Fase 6.4 — API base (CRUD + Auth)

**Objetivo:** Exponer endpoints básicos de leads vía API autenticada.

- [ ] Configurar `routes/api.php` con prefijo `/api/v1`
- [ ] Middleware: extracción de `tenant_id` desde token Sanctum
- [ ] Middleware: verificación de `IdempotencyKey`
- [ ] Middleware: rate limiting por tenant
- [ ] `POST /api/v1/auth/login` y `logout`
- [ ] `GET /api/v1/leads` con filtros (status, stage, assigned_to, source_system)
- [ ] `POST /api/v1/leads` con idempotencia y unicidad por datos
- [ ] `GET /api/v1/leads/{id}`
- [ ] `PATCH /api/v1/leads/{id}` (campos editables)
- [ ] `DELETE /api/v1/leads/{id}` (archivado)
- [ ] API Resources para respuestas consistentes
- [ ] Form Requests con validación completa
- [ ] Tests de feature para endpoints principales
- [ ] `GET /api/health`

---

## Fase 6.5 — Pipeline y acciones de estado

**Objetivo:** Implementar el flujo comercial del lead.

- [ ] `GET /api/v1/pipeline/stages`
- [ ] `PATCH /api/v1/leads/{id}/stage` con reglas de transición
- [ ] `PATCH /api/v1/leads/{id}/assign`
- [ ] `PATCH /api/v1/leads/{id}/followup`
- [ ] `POST /api/v1/leads/{id}/contact`
- [ ] `PATCH /api/v1/leads/{id}/won`
- [ ] `PATCH /api/v1/leads/{id}/lost` (con `lost_reason` obligatorio)
- [ ] Activity Log automático en cada acción
- [ ] Actualización automática de `last_contact_at`

---

## Fase 6.6 — Notas y actividad

**Objetivo:** Registro histórico de interacciones.

- [ ] `GET /api/v1/leads/{id}/notes`
- [ ] `POST /api/v1/leads/{id}/notes`
- [ ] `GET /api/v1/leads/{id}/activity`
- [ ] Agregar nota dispara actualización de `last_contact_at`

---

## Fase 6.7 — Panel interno Inertia

**Objetivo:** UI funcional para gestión de leads del panel interno.

- [ ] Definir estructura de Pages/Components por módulo
- [ ] Vista: lista de leads con filtros y paginación
- [ ] Vista: detalle de lead (notas, actividad, pipeline)
- [ ] Vista: kanban del pipeline por stage
- [ ] Componentes Vue reutilizables del dominio (LeadCard, StageSelector, ActivityFeed)

---

## Fase 6.8 — Calidad y producción

**Objetivo:** Preparar para entorno productivo.

- [ ] Tests unitarios del dominio (Actions, Services)
- [ ] Tests de aislamiento multi-tenant
- [ ] Documentación API con Scribe o similar
- [ ] Variables de entorno documentadas completamente
- [ ] Verificar entorno de deploy: PHP NTS (no ZTS)
- [ ] Configurar queue worker para jobs de background
- [ ] Revisión de seguridad (OWASP básico)

---

## Notas sobre prioridades

- Las fases 6.3 y 6.4 son bloqueantes para el resto.
- La idempotencia debe estar lista en fase 6.4 antes de exponer la API a sistemas externos.
- El panel Inertia (6.7) puede desarrollarse en paralelo con 6.5/6.6.
- Los tests de aislamiento multi-tenant (6.8) deben hacerse antes de producción.
