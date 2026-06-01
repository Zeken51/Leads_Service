# Roadmap — leads-service

## Fase 6.1 — Fundación técnica (actual)

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

## Fase 6.2 — Dominio base

**Objetivo:** Crear la estructura de carpetas y modelos principales.

- [ ] Crear carpetas: `app/Domain`, `app/Services`, `app/DTOs`, `app/Actions`
- [ ] Migración: `organizations` (multi-tenant base)
- [ ] Migración: `leads` (campos esenciales)
- [ ] Migración: `pipeline_stages`
- [ ] Modelo `Organization` + Global Scope de tenant
- [ ] Modelo `Lead` con relaciones básicas
- [ ] Modelo `PipelineStage`
- [ ] Seeders de datos de prueba

---

## Fase 6.3 — API base

**Objetivo:** Exponer endpoints básicos de leads vía API autenticada.

- [ ] Configurar rutas `api.php` con prefijo `/api/v1`
- [ ] Middleware de tenant en rutas API
- [ ] `POST /api/v1/auth/login` y `logout`
- [ ] CRUD básico de leads (`LeadController`)
- [ ] Form Requests con validación
- [ ] API Resources para respuestas consistentes
- [ ] Tests de feature para endpoints

---

## Fase 6.4 — Pipeline y seguimiento

**Objetivo:** Implementar el flujo comercial del lead.

- [ ] CRUD de etapas del pipeline por tenant
- [ ] Endpoint `PATCH /api/v1/leads/{id}/stage`
- [ ] Activity Log automático en cambios de etapa
- [ ] Asignación de leads a agentes
- [ ] Filtros y búsqueda en listado de leads

---

## Fase 6.5 — Notas y actividad

**Objetivo:** Registro histórico de interacciones.

- [ ] Modelo `Note` + migración
- [ ] Modelo `ActivityLog` + migración
- [ ] CRUD de notas por lead
- [ ] Log automático de eventos (creación, cambio de etapa, asignación)
- [ ] Endpoint de actividad del lead

---

## Fase 6.6 — Panel interno Inertia

**Objetivo:** UI funcional para gestión de leads.

- [ ] Vista: lista de leads con filtros
- [ ] Vista: detalle de lead (notas, actividad, pipeline)
- [ ] Vista: kanban del pipeline
- [ ] Componentes Vue reutilizables del dominio

---

## Fase 6.7 — Calidad y producción

**Objetivo:** Preparar para entorno productivo.

- [ ] Idempotencia en endpoints de escritura
- [ ] Rate limiting por tenant
- [ ] Tests unitarios del dominio
- [ ] Documentación API con Scribe o similar
- [ ] Variables de entorno documentadas
- [ ] Health check endpoint (`GET /api/health`)

---

## Notas sobre prioridades

- Las fases 6.2 y 6.3 son bloqueantes para el resto.
- La fase 6.6 (panel Inertia) puede desarrollarse en paralelo con 6.4/6.5.
- La idempotencia (6.7) debe implementarse antes de exponer la API a sistemas externos.
