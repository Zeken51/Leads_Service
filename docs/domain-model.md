# Modelo de dominio — leads-service

> Fase 6.2 — Análisis y diseño. Sin implementación todavía.

---

## 1. Descripción del dominio

El dominio **Leads** representa el ciclo de vida comercial de un prospecto desde
su captura hasta su resolución (ganado o perdido). Este microservicio es el único
sistema responsable de ese ciclo. No depende de ningún otro repositorio ni base
de datos externa para operar.

Los leads llegan desde sistemas externos (formularios, APIs, integraciones) y
son gestionados internamente por agentes comerciales del tenant. El microservicio
registra cada interacción, cambio de estado y nota para dar trazabilidad completa.

---

## 2. Responsabilidades del microservicio

### SÍ pertenece a este dominio

- Recibir leads desde clientes externos vía API
- Validar payloads de entrada
- Identificar tenant y origen del lead
- Evitar duplicados mediante idempotencia (`external_reference_id` + `source_system` + `tenant_id`)
- Almacenar y gestionar el lead y sus datos
- Gestionar la etapa comercial (pipeline) del lead
- Gestionar próximas acciones y fechas de seguimiento
- Registrar notas e interacciones por lead
- Registrar el log de actividad automático
- Exponer endpoints API versionados
- Permitir consultas filtradas por tenant, status, stage y asignación
- Servir como base para el panel interno de prueba (Inertia/Vue)

### NO pertenece a este dominio

- Cotizar viajes, calcular precios ni manejar inventario
- Manejar hoteles, habitaciones ni disponibilidad
- Ser el frontend público principal del producto
- Depender de ZendVacations, del CRM anterior ni de ningún sistema externo
- Acceder a bases de datos de otros servicios
- Almacenar lógica de negocio de otros dominios
- Gestionar notificaciones push (se delega a un servicio externo si aplica)
- Facturación o pagos

---

## 3. Entidades principales

### 3.1 Tenant *(referencia externa)*

Representa a la organización que usa el microservicio. **No se almacena como tabla propia** en esta etapa — se referencia únicamente por `tenant_id` (UUID). Toda entidad de negocio lleva este campo para el scoping multi-tenant.

```
tenant_id: uuid  ← se extrae del token de autenticación. Nunca del body ni del payload
```

> Decisión: si en el futuro se requiere metadata del tenant (nombre, configuración),
> se creará la tabla `tenants`. Por ahora es solo un identificador.

---

### 3.2 Lead *(entidad principal)*

El prospecto comercial. Contiene datos del contacto, su posición en el pipeline,
referencias al sistema de origen y snapshots del usuario asignado.

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | uuid PK | Identificador interno |
| `tenant_id` | uuid | Tenant al que pertenece |
| `source_system` | string | Sistema que originó el lead (ej: `zend_vacations`, `web_form`, `manual`) |
| `source_channel` | string | Canal específico (ej: `landing_page`, `whatsapp`, `email`, `api`) |
| `external_reference_id` | string nullable | ID del lead en el sistema externo; usado para idempotencia |
| `status` | enum | Estado del sistema: `active`, `won`, `lost`, `archived` |
| `stage_id` | uuid FK | Etapa comercial actual dentro del pipeline |
| `priority` | enum | `low`, `medium`, `high`, `urgent` |
| `assigned_user_id` | string nullable | ID externo del usuario asignado |
| `assigned_user_name_snapshot` | string nullable | Nombre del agente al momento de la asignación |
| `assigned_user_email_snapshot` | string nullable | Email del agente al momento de la asignación |
| `assigned_user_provider` | string nullable | Sistema que gestiona al usuario (ej: `zend_platform`, `leads_service`) |
| `next_action` | string nullable | Descripción de la próxima acción a tomar |
| `followup_at` | timestamp nullable | Fecha/hora programada para el seguimiento |
| `last_contact_at` | timestamp nullable | Última vez que hubo contacto real con el lead |
| `won_at` | timestamp nullable | Momento en que se marcó como ganado |
| `lost_at` | timestamp nullable | Momento en que se marcó como perdido |
| `lost_reason` | string nullable | Motivo del cierre como perdido |
| `metadata` | json nullable | Datos adicionales de libre estructura por source_system |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp nullable | Soft delete |

**`external_reference_id` es opcional:**
Tenants que ingresan leads manualmente o desde sistemas sin ID propio pueden omitirlo. En ese caso el lead se crea sin protección de deduplicación de nivel 2 (solo aplica el `Idempotency-Key` header).

**Unicidad idempotente (nivel 2):**
`(tenant_id, source_system, external_reference_id)` es único cuando `external_reference_id` no es nulo. MySQL 8 permite múltiples filas con `external_reference_id = NULL` bajo el mismo índice UNIQUE — el índice solo actúa cuando los tres valores son no nulos.

---

### 3.3 LeadCustomer *(sub-entidad embebida)*

Los datos del contacto/prospecto. Se almacena como parte del lead (columnas propias
o JSON embebido), **no como tabla separada**, porque el cliente aquí es el
prospecto en contexto de venta, no una entidad de identidad global.

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `customer_name` | string | Nombre completo |
| `customer_email` | string nullable | Email del prospecto |
| `customer_phone` | string nullable | Teléfono |
| `customer_country` | string nullable | País (ISO 3166-1 alpha-2) |
| `customer_metadata` | json nullable | Datos adicionales del prospecto |

---

### 3.4 PipelineStage *(entidad de configuración)*

Las etapas del pipeline comercial. Cada tenant tiene su propio conjunto de etapas,
ordenadas y con indicadores de tipo (inicial/terminal).

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid | Tenant propietario |
| `name` | string | Nombre de la etapa (ej: "Nuevo contacto") |
| `slug` | string | Identificador normalizado (ej: `new_contact`) |
| `order` | integer | Orden en el pipeline |
| `color` | string | Color hex para UI |
| `is_initial` | boolean | Si es la etapa de entrada por defecto |
| `is_terminal` | boolean | Si es una etapa de cierre (ganado/perdido) |
| `maps_to_status` | enum nullable | `won` o `lost` si es terminal |
| `created_at` | timestamp | |

---

### 3.5 LeadNote *(entidad de interacción)*

Nota libre escrita por un agente sobre el lead.

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | uuid PK | |
| `lead_id` | uuid FK | |
| `tenant_id` | uuid | Para scoping y consultas directas |
| `content` | text | Contenido de la nota |
| `author_id` | string | ID externo del autor |
| `author_name_snapshot` | string | Nombre del autor al momento de crear |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp nullable | Soft delete |

---

### 3.6 LeadActivityLog *(entidad de auditoría)*

Registro inmutable de cada evento significativo en el ciclo de vida del lead.
**No se permite editar ni eliminar** registros de este log.

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | uuid PK | |
| `lead_id` | uuid FK | |
| `tenant_id` | uuid | Para scoping |
| `event` | string | Código del evento (ver tabla de eventos) |
| `description` | string | Descripción legible del evento |
| `payload` | json nullable | Datos del antes/después del cambio |
| `causer_id` | string nullable | ID externo del actor |
| `causer_name_snapshot` | string nullable | Nombre del actor al momento del evento |
| `causer_type` | enum | `user`, `system`, `api_client` |
| `contact_channel` | string nullable | Canal del contacto. **Solo aplica al evento `contact_registered`.** No es una propiedad del Lead: no vive en la tabla `leads`, solo en `lead_activity_logs`. Valores: `phone`, `whatsapp`, `email`, `in_person`, `video_call`, `sms`, `other` |
| `created_at` | timestamp | |

**Eventos registrados:**

| Evento | Descripción |
|---|---|
| `lead_created` | Lead creado |
| `lead_updated` | Campos del lead actualizados |
| `stage_changed` | Etapa comercial cambiada |
| `status_changed` | Status del sistema cambiado |
| `lead_assigned` | Lead asignado a un agente |
| `lead_unassigned` | Lead desasignado |
| `note_added` | Nota agregada |
| `contact_registered` | Contacto con el prospecto registrado (incluye `contact_channel` en payload) |
| `followup_scheduled` | Próxima acción/seguimiento programado |
| `lead_won` | Lead marcado como ganado |
| `lead_lost` | Lead marcado como perdido |
| `lead_archived` | Lead archivado |
| `lead_restored` | Lead restaurado desde archivo |

---

### 3.7 IdempotencyKey *(entidad de control)*

Almacena el resultado de operaciones de escritura recientes para responder con
el mismo resultado si la misma clave se repite dentro del TTL.

**Campos:**

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | uuid PK | |
| `key` | string UNIQUE | El valor del header `Idempotency-Key` |
| `tenant_id` | uuid | Tenant que realizó la petición |
| `method` | string | Método HTTP |
| `path` | string | Ruta de la petición |
| `response_status` | integer | Código HTTP de la respuesta original |
| `response_body` | json | Cuerpo de la respuesta original |
| `expires_at` | timestamp | TTL (por defecto 24 horas) |
| `created_at` | timestamp | |

---

### 3.8 Capability *(entidad de extensión)*

Define permisos/capacidades que pueden asignarse a tokens API o roles. Permite
extensión sin romper contratos.

> Entidad propuesta para fases posteriores. No implementar todavía.

```
capability: string  ← ej: "leads:create", "leads:assign", "pipeline:manage"
```

---

## 4. Relaciones conceptuales

```
Tenant (referencia) ──→ (N) Lead
Tenant (referencia) ──→ (N) PipelineStage

Lead (1) ──→ (N) LeadNote
Lead (1) ──→ (N) LeadActivityLog
Lead (N) ──→ (1) PipelineStage

IdempotencyKey ← operaciones POST/PATCH de la API
```

Diagrama simplificado:

```
[Sistema externo]
       │
       │ POST /api/v1/leads  (+ Idempotency-Key header)
       ↓
[leads-service API]
       │
       ├── valida tenant_id (del token)
       ├── verifica IdempotencyKey
       ├── crea / recupera Lead
       │       ├── LeadCustomer (embebido)
       │       ├── PipelineStage (stage inicial del tenant)
       │       └── LeadActivityLog (lead_created)
       └── responde 201 / 200 (si idempotente)
```

---

## 5. Estados comerciales del lead

### 5.1 Status del sistema (campo `status`)

Controla el estado de ciclo de vida a nivel de sistema.

```
active ──→ won
active ──→ lost
active ──→ archived
archived ──→ active  (restaurar)
won        [terminal]
lost       [terminal]
```

| Status | Descripción |
|---|---|
| `active` | En pipeline, en seguimiento activo |
| `won` | Convertido/ganado — estado terminal |
| `lost` | Perdido/descartado — estado terminal |
| `archived` | Inactivo temporalmente, no en pipeline activo |

### Relación entre `status` y `stage_id`

Son campos distintos, **no redundantes**. `status` es la fuente de verdad del estado del sistema; `stage_id` es la fuente de verdad de la posición comercial.

**Reglas de sincronización (aplicadas por la lógica de negocio, no por la DB):**

| Acción | Efecto en `status` | Efecto en `stage_id` |
|---|---|---|
| Mover a stage terminal `maps_to_status=won` | → `won` automático | Se actualiza al stage terminal |
| Mover a stage terminal `maps_to_status=lost` | → `lost` automático | Se actualiza al stage terminal |
| Llamar a `/won` directamente | → `won` | Se mueve al stage terminal `won` del tenant si existe; si no, `stage_id` queda sin cambio |
| Llamar a `/lost` directamente | → `lost` | Se mueve al stage terminal `lost` del tenant si existe; si no, `stage_id` queda sin cambio |
| `status=won` o `status=lost` | No se puede cambiar `stage_id` | Bloqueado |
| `status=archived` | No afecta `stage_id` | Sin cambio |

`stage_id` puede ser `NULL` si el tenant no tiene pipeline configurado. En ese caso `status` es la única fuente de estado del lead.

### 5.2 Stage del pipeline (campo `stage_id`)

Etapas configuradas por tenant. Ejemplo de pipeline típico:

```
[Nuevo contacto] → [Contactado] → [Cotización enviada] → [Negociación] → [Ganado / Perdido]
     (inicial)                                                              (terminales)
```

Las etapas no tienen transiciones forzadas entre sí (se puede mover libremente),
excepto:
- No se puede cambiar de stage si el `status` es `won` o `lost`
- Al mover a una stage `is_terminal=true`, el `status` cambia automáticamente

---

## 6. Flujo funcional base

### 6.1 Crear lead

```
1. Cliente externo envía POST /api/v1/leads con Idempotency-Key
2. Verificar si la clave ya existe → retornar respuesta cacheada si aplica
3. Identificar tenant_id desde el token
4. Validar payload (Form Request)
5. Verificar unicidad: (tenant_id, source_system, external_reference_id)
   → si existe: error 409 CONFLICT sin Idempotency-Key, o respuesta cacheada con ella
6. Obtener stage inicial del tenant (PipelineStage donde is_initial=true)
7. Crear Lead con status=active
8. Registrar LeadActivityLog: lead_created
9. Guardar IdempotencyKey con respuesta
10. Retornar 201 con el lead creado
```

### 6.2 Asignar lead

```
1. PATCH /api/v1/leads/{id}/assign
2. Validar que el lead pertenece al tenant
3. Actualizar: assigned_user_id, assigned_user_name_snapshot,
   assigned_user_email_snapshot, assigned_user_provider
4. Registrar LeadActivityLog: lead_assigned (payload: usuario anterior / nuevo)
5. Retornar lead actualizado
```

### 6.3 Cambiar stage

```
1. PATCH /api/v1/leads/{id}/stage
2. Validar que el lead está en status=active
3. Validar que el stage_id pertenece al tenant
4. Si stage.is_terminal=true y stage.maps_to_status=won:
   → actualizar status=won, won_at=now()
   → registrar LeadActivityLog: lead_won
5. Si stage.is_terminal=true y stage.maps_to_status=lost:
   → requerir lost_reason
   → actualizar status=lost, lost_at=now()
   → registrar LeadActivityLog: lead_lost
6. Actualizar stage_id
7. Registrar LeadActivityLog: stage_changed (payload: stage anterior / nuevo)
8. Retornar lead actualizado
```

### 6.4 Registrar próxima acción

```
1. PATCH /api/v1/leads/{id}/followup
2. Validar que lead está activo
3. Actualizar: next_action, followup_at
4. Registrar LeadActivityLog: followup_scheduled
5. Retornar lead actualizado
```

### 6.5 Registrar contacto

```
1. POST /api/v1/leads/{id}/contact
2. Validar que lead está activo
3. Actualizar: last_contact_at=now()
4. (Opcional) limpiar followup_at si aplica
5. Registrar LeadActivityLog: contact_registered
6. Retornar lead actualizado
```

### 6.6 Marcar ganado

```
1. PATCH /api/v1/leads/{id}/won  (o via cambio de stage terminal)
2. Validar status=active
3. Actualizar: status=won, won_at=now()
4. Mover a stage terminal de tipo "won" si existe
5. Registrar LeadActivityLog: lead_won
6. Retornar lead actualizado
```

### 6.7 Marcar perdido

```
1. PATCH /api/v1/leads/{id}/lost
2. Validar status=active
3. Requerir lost_reason en el body (obligatorio)
4. Actualizar: status=lost, lost_at=now(), lost_reason
5. Mover a stage terminal de tipo "lost" si existe
6. Registrar LeadActivityLog: lead_lost
7. Retornar lead actualizado
```

---

## 7. Reglas del pipeline

| Regla | Detalle |
|---|---|
| Etapa inicial | Primera stage del tenant (`is_initial=true`). Si no existe, se asigna null |
| Etapas terminales | `is_terminal=true` en PipelineStage. Solo existen dos tipos: won/lost |
| Bloqueo en terminales | Si `status=won` o `status=lost`, no se puede cambiar stage |
| `last_contact_at` | Se actualiza al: registrar contacto (`/contact`), agregar nota (`/notes`), cambiar stage (`/stage`). Representa la última actividad comercial sobre el lead, no exclusivamente una interacción directa con el cliente. Para registrar el canal específico del contacto usar el endpoint `/contact`. |
| `next_action` + `followup_at` | Recomendados (no bloqueantes) al avanzar de stage. Bloqueantes solo en fases futuras |
| `lost_reason` | **Obligatorio** al marcar status=lost o mover a stage terminal de tipo lost |
| `won_at` | Se llena automáticamente al marcar ganado |
| `lost_at` | Se llena automáticamente al marcar perdido |
| Soft delete | Los leads se archivan (deleted_at), nunca se borran físicamente |
| Unicidad | `(tenant_id, source_system, external_reference_id)` único cuando `external_reference_id` no es null |

---

## 8. Límites con clientes externos

```
┌─────────────────────────────────────────────────────────┐
│                    leads-service                        │
│                                                         │
│  ┌────────────┐   ┌──────────────┐   ┌──────────────┐  │
│  │    Lead    │   │ PipelineStage│   │  LeadNote    │  │
│  └────────────┘   └──────────────┘   └──────────────┘  │
│                                                         │
│  ENTRADA: API v1 (Bearer token)                         │
│  SALIDA:  JSON responses / webhooks (futuro)            │
└─────────────────────────────────────────────────────────┘
         ↑                    ↑
   ZendVacations         Web forms
   (source_system:        (source_system:
    zend_vacations)        web_form)
```

**Contratos de entrada:**
- El cliente externo provee `source_system`, `source_channel`, `external_reference_id`
- El cliente externo provee los datos del `customer`
- El cliente externo provee `assigned_user_id` como referencia opaca (ID de su propio sistema)
- Este microservicio **no consulta** al sistema externo para validar ese ID

**Contratos de salida:**
- Respuestas JSON en formato estándar (ver `api-contracts-v1.md`)
- El `id` (UUID interno) es el identificador que el cliente externo debe usar para
  operaciones posteriores

---

## 9. Multi-tenant

- Toda entidad de negocio tiene `tenant_id`
- El `tenant_id` se deriva del token de autenticación (no viene en el body)
- Un Global Scope o trait `HasTenant` aplica el filtro automáticamente en todas las queries
- Tests de aislamiento deben verificar que un tenant no puede acceder a datos de otro

---

## 10. Preparación para migración tecnológica (NestJS)

Los contratos API están versionados (`/api/v1`) para poder cambiar la implementación
interna sin afectar a los clientes. Las decisiones de diseño que preservan esta portabilidad:

- **Sin lógica de negocio en la capa HTTP**: controllers son delgados
- **DTOs explícitos**: definen el contrato de entrada/salida independientemente del ORM
- **Eventos de dominio documentados**: facilitan migración a event-driven en NestJS
- **Idempotency-Key**: es un patrón agnóstico al framework
- **UUIDs como PKs**: no hay dependencia de auto-increment de MySQL

Al migrar, el contrato `POST /api/v1/leads` con el mismo payload y headers debe
producir la misma respuesta, independientemente del lenguaje que lo implemente.

---

## 11. Glosario

| Término | Definición |
|---|---|
| **Lead** | Prospecto comercial con interés potencial en un producto/servicio |
| **Tenant** | Organización cliente que usa el microservicio de forma aislada |
| **Pipeline** | Secuencia de etapas comerciales que atraviesa un lead |
| **Stage** | Etapa individual dentro del pipeline (ej: "Contactado") |
| **Status** | Estado del sistema del lead: active, won, lost, archived |
| **source_system** | Identificador del sistema que originó el lead |
| **source_channel** | Canal específico dentro del source_system |
| **external_reference_id** | Identificador opaco del lead en el sistema de origen, usado para garantizar unicidad e idempotencia. Cada sistema externo le da su propio nombre (ZendVacations lo llama `quote_id`; ese mapeo es específico de ese tenant, no una equivalencia general del campo) |
| **Snapshot** | Copia del valor de un campo externo en el momento de la operación |
| **assigned_user_provider** | Sistema que gestiona al usuario asignado |
| **Idempotencia** | Propiedad que garantiza que repetir una operación produce el mismo resultado |
| **Activity Log** | Registro inmutable de eventos en el ciclo de vida del lead |
| **Terminal stage** | Etapa de cierre: won o lost. No permite avance posterior |
