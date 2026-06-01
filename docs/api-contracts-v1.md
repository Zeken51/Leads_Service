# API Contracts v1 — leads-service

> Estado: CERRADO para implementación. Actualizado en fase 6.3.
> Ver también: `docs/api-auth.md`, `docs/api-errors.md`, `docs/idempotency.md`

---

## Base URL

| Entorno | URL |
|---|---|
| Producción | `https://leads.zendlogic.com/api/v1` |
| Desarrollo local | `http://localhost:8000/api/v1` |

---

## Headers globales

Todas las peticiones a `/api/v1` deben incluir:

```
Authorization:   Bearer {token}
Content-Type:    application/json
Accept:          application/json
```

Headers opcionales pero recomendados:

```
X-Request-ID:    {uuid}    ← trazabilidad. Si no se envía, el servidor lo genera
Idempotency-Key: {uuid}    ← en todas las operaciones de escritura (POST/PATCH)
```

El `tenant_id` **nunca se envía** en headers, body ni query params. Se extrae automáticamente del token.

Ver `docs/api-auth.md` para el flujo completo de autenticación.

---

## Formato de respuesta estándar

### Recurso único

```json
{
  "data": { ... },
  "request_id": "req_a1b2c3d4"
}
```

### Lista paginada

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 142,
    "last_page": 6,
    "from": 1,
    "to": 25
  },
  "request_id": "req_a1b2c3d4"
}
```

### Sin contenido (204)

Sin body. Header `X-Request-ID` presente.

### Error

Ver `docs/api-errors.md` para el catálogo completo. Formato resumido:

```json
{
  "message": "Descripción del error.",
  "errors": { "campo": ["mensaje"] },
  "request_id": "req_a1b2c3d4"
}
```

---

## Paginación

Todos los endpoints de listado usan paginación basada en página + tamaño:

| Parámetro | Default | Máximo | Descripción |
|---|---|---|---|
| `page` | `1` | — | Número de página |
| `per_page` | `25` | `100` | Resultados por página |

> **Decisión de fase 6.3:** Se cambió de cursor-based (fase 6.2) a page-based.
> Razón: los casos de uso del panel de agentes requieren navegación por número de página exacto,
> lo que no es natural con cursores. Ver `technical-notes.md` decisión #11.

---

## Estructura completa de un lead

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "tenant_id": "org-uuid-abc",
  "source_system": "zend_vacations",
  "source_channel": "landing_page",
  "external_reference_id": "ZV-00123",
  "status": "active",
  "stage": {
    "id": "stage-uuid",
    "name": "Contactado",
    "slug": "contacted",
    "order": 2,
    "color": "#3B82F6"
  },
  "priority": "high",
  "customer": {
    "name": "Juan Pérez",
    "email": "juan@empresa.com",
    "phone": "+52 55 1234 5678",
    "country": "MX",
    "metadata": {}
  },
  "assigned_to": {
    "user_id": "ext-user-abc",
    "name": "María Agente",
    "email": "maria@zend.com",
    "provider": "zend_platform"
  },
  "next_action": "Llamar para confirmar disponibilidad",
  "followup_at": "2026-06-15T10:00:00Z",
  "last_contact_at": "2026-06-01T09:30:00Z",
  "won_at": null,
  "lost_at": null,
  "lost_reason": null,
  "metadata": {
    "trip_type": "honeymoon",
    "budget": "5000",
    "quote_id": "ZV-00123"
  },
  "idempotent_replay": false,
  "created_at": "2026-05-28T08:00:00Z",
  "updated_at": "2026-06-01T09:30:00Z"
}
```

> **`external_reference_id`** es el campo genérico del modelo para cualquier referencia externa.
> El nombre que cada sistema de origen le da a ese identificador es propio de ese sistema.
> Por ejemplo, ZendVacations lo llama `quote_id`. El cliente debe mapear su propio nombre a `external_reference_id`
> al crear el lead. Para preservar el nombre original puede incluirlo dentro de `metadata`,
> pero `external_reference_id` en sí no tiene semántica de cotización ni de viajes.

---

## Endpoints de autenticación

### POST /api/v1/auth/login

**Request:**
```json
{
  "email": "agente@empresa.com",
  "password": "secret"
}
```

**Response 200:**
```json
{
  "data": {
    "token": "7|hR3kLmN9pQ...",
    "token_type": "Bearer",
    "expires_at": null
  },
  "request_id": "req_a1b2c3d4"
}
```

**Errores:** `401` credenciales inválidas, `429` rate limit de login.

---

### POST /api/v1/auth/logout

Revoca el token actual. No requiere body.

**Response 204:** Sin contenido.

---

## Endpoints de leads

### POST /api/v1/leads

Crea un nuevo lead. **Operación principal del microservicio.**

**Headers recomendados:**
```
Idempotency-Key: {uuid}
```

**Request:**
```json
{
  "source_system": "zend_vacations",
  "source_channel": "landing_page",
  "external_reference_id": "ZV-00123",
  "priority": "high",
  "customer": {
    "name": "Juan Pérez",
    "email": "juan@empresa.com",
    "phone": "+52 55 1234 5678",
    "country": "MX",
    "metadata": {
      "preferred_contact": "whatsapp"
    }
  },
  "assigned_to": {
    "user_id": "ext-user-abc",
    "name": "María Agente",
    "email": "maria@zend.com",
    "provider": "zend_platform"
  },
  "next_action": "Llamar en las próximas 24h",
  "followup_at": "2026-06-02T10:00:00Z",
  "metadata": {
    "trip_type": "honeymoon",
    "budget": "5000",
    "quote_id": "ZV-00123"
  }
}
```

**Campos obligatorios:** `source_system`, `source_channel`, `customer.name`

**Campos opcionales:** todos los demás

**Response 201** (lead creado):
```json
{
  "data": { ... lead completo ... },
  "request_id": "req_a1b2c3d4"
}
```

**Response 200** (idempotent replay):
```json
{
  "data": { ... lead original ... , "idempotent_replay": true },
  "request_id": "req_a1b2c3d4"
}
```
Header adicional: `Idempotent-Replayed: true`

**Errores:** `400` JSON inválido, `409` duplicado sin Idempotency-Key, `422` validación de negocio.

---

### GET /api/v1/leads

Lista leads del tenant con filtros.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `status` | string | `active`, `won`, `lost`, `archived` |
| `stage_id` | uuid | Filtrar por etapa del pipeline |
| `assigned_to` | string | `assigned_user_id` externo |
| `source_system` | string | Sistema de origen |
| `source_channel` | string | Canal de origen |
| `priority` | string | `low`, `medium`, `high`, `urgent` |
| `followup_from` | datetime ISO | Leads con `followup_at` ≥ esta fecha |
| `followup_to` | datetime ISO | Leads con `followup_at` ≤ esta fecha |
| `overdue` | boolean | `true` → leads con `followup_at` en el pasado y `status=active` |
| `search` | string | Búsqueda en `customer.name`, `customer.email`, `customer.phone` |
| `page` | integer | Página (default: 1) |
| `per_page` | integer | Por página (default: 25, max: 100) |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "tenant_id": "uuid",
      "source_system": "zend_vacations",
      "source_channel": "landing_page",
      "external_reference_id": "ZV-00123",
      "status": "active",
      "stage": { "id": "uuid", "name": "Contactado", "slug": "contacted", "order": 2 },
      "priority": "high",
      "customer": { "name": "Juan Pérez", "email": "juan@empresa.com", "phone": "+52 55 1234 5678" },
      "assigned_to": { "user_id": "ext-user-abc", "name": "María Agente" },
      "next_action": "Llamar mañana",
      "followup_at": "2026-06-02T10:00:00Z",
      "last_contact_at": "2026-06-01T09:00:00Z",
      "created_at": "2026-05-28T08:00:00Z",
      "updated_at": "2026-06-01T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 142,
    "last_page": 6,
    "from": 1,
    "to": 25
  },
  "request_id": "req_a1b2c3d4"
}
```

> La lista no incluye `notes` ni `activity_logs` para mantener la respuesta liviana. Usar `GET /leads/{id}` para el detalle completo.

---

### GET /api/v1/leads/{id}

Detalle completo del lead incluyendo notas y actividad reciente.

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "tenant_id": "uuid",
    "source_system": "zend_vacations",
    "source_channel": "landing_page",
    "external_reference_id": "ZV-00123",
    "status": "active",
    "stage": {
      "id": "uuid", "name": "Contactado", "slug": "contacted", "order": 2, "color": "#3B82F6"
    },
    "priority": "high",
    "customer": {
      "name": "Juan Pérez",
      "email": "juan@empresa.com",
      "phone": "+52 55 1234 5678",
      "country": "MX",
      "metadata": {}
    },
    "assigned_to": {
      "user_id": "ext-user-abc",
      "name": "María Agente",
      "email": "maria@zend.com",
      "provider": "zend_platform"
    },
    "next_action": "Llamar para confirmar disponibilidad",
    "followup_at": "2026-06-15T10:00:00Z",
    "last_contact_at": "2026-06-01T09:30:00Z",
    "won_at": null,
    "lost_at": null,
    "lost_reason": null,
    "metadata": { "trip_type": "honeymoon", "budget": "5000" },
    "notes": [
      {
        "id": "uuid",
        "content": "Cliente confirmó interés para julio.",
        "author": { "id": "ext-user-abc", "name": "María Agente" },
        "created_at": "2026-06-01T09:30:00Z"
      }
    ],
    "activity": [
      {
        "id": "uuid",
        "event": "stage_changed",
        "description": "Etapa cambiada de 'Nuevo contacto' a 'Contactado'",
        "causer": { "id": "ext-user-abc", "name": "María Agente", "type": "user" },
        "created_at": "2026-06-01T09:00:00Z"
      }
    ],
    "created_at": "2026-05-28T08:00:00Z",
    "updated_at": "2026-06-01T09:30:00Z"
  },
  "request_id": "req_a1b2c3d4"
}
```

> `notes` y `activity` en el detalle muestran los **10 registros más recientes**. Para el historial completo usar los endpoints dedicados `GET /leads/{id}/notes` y `GET /leads/{id}/activity`.

---

### PATCH /api/v1/leads/{id}

Actualiza campos editables del lead: `customer`, `priority`, `metadata`, `source_channel`.
**No usar** para cambios de stage, asignación ni acciones de estado (tienen endpoints propios).

**Request (todos los campos son opcionales):**
```json
{
  "priority": "urgent",
  "customer": {
    "name": "Juan Carlos Pérez",
    "phone": "+52 55 9999 8888"
  },
  "metadata": {
    "budget": "8000"
  }
}
```

**Response 200:** lead actualizado completo.

---

### DELETE /api/v1/leads/{id}

Archiva el lead (soft delete). Cambia `status=archived`. No elimina físicamente.

**Response 200:**
```json
{
  "data": { ... lead con status: "archived" ... },
  "request_id": "req_a1b2c3d4"
}
```

Para restaurar un lead archivado: `PATCH /api/v1/leads/{id}/restore` (pendiente de documentar en fase 6.5).

---

### PATCH /api/v1/leads/{id}/stage

Mueve el lead a otra etapa del pipeline.

**Request:**
```json
{
  "stage_id": "uuid-de-la-etapa",
  "next_action": "Enviar propuesta detallada",
  "followup_at": "2026-06-10T14:00:00Z",
  "lost_reason": "El cliente eligió a la competencia"
}
```

**Campos obligatorios:** `stage_id`

**`lost_reason` obligatorio** cuando el stage es terminal de tipo `lost`.

**`next_action` y `followup_at`:** Solo válidos para stages **no terminales**. Si se envían hacia un stage terminal (won/lost), el servidor retorna `422` — no los ignora silenciosamente.

**Comportamiento automático:**
- Stage no terminal: `stage_id` actualizado; `next_action`/`followup_at` opcionales
- Stage terminal won: `status=won`, `won_at=now()`, activity log `stage_changed` + `lead_won`
- Stage terminal lost: `status=lost`, `lost_at=now()`, `lost_reason` requerido, activity log `stage_changed` + `lead_lost`

> **Recomendación:** Usar `PATCH /won` o `PATCH /lost` para cerrar leads cuando no es necesaria la navegación explícita por etapas del pipeline (por ejemplo, tenants sin pipeline configurado o cierres directos desde un panel). `PATCH /stage` con stage terminal es el flujo natural cuando el agente navega por las etapas del pipeline.

**Errores:**
- `422` si el lead está cerrado (`status=won/lost`)
- `422` si el stage no pertenece al tenant
- `422` si el stage es terminal y se envían `next_action` o `followup_at`
- `422` si el stage es terminal `lost` y falta `lost_reason`

**Response 200:** lead actualizado.

---

### PATCH /api/v1/leads/{id}/assign

Asigna o reasigna el lead a un agente externo.

**Request:**
```json
{
  "user_id": "ext-user-xyz",
  "name": "Carlos Agente",
  "email": "carlos@zend.com",
  "provider": "zend_platform"
}
```

**Campos obligatorios:** `user_id`, `name`

**Comportamiento:** Guarda snapshots de nombre y email. No valida el `user_id` contra sistema externo.

**Response 200:** lead actualizado con el nuevo `assigned_to`.

---

### PATCH /api/v1/leads/{id}/followup

Programa la próxima acción y fecha de seguimiento.

**Request:**
```json
{
  "next_action": "Enviar propuesta por email",
  "followup_at": "2026-06-10T14:00:00Z"
}
```

**Campos obligatorios:** al menos uno de los dos (`next_action` o `followup_at`). Si se envía `followup_at`, `next_action` también es obligatorio (no tiene sentido agendar una fecha sin describir la acción).

**Restricción:** Bloqueado si el lead está en estado `won` o `lost` (422).

**Response 200:** lead actualizado.

---

### POST /api/v1/leads/{id}/contact

Registra que hubo un contacto real con el lead. Actualiza `last_contact_at=now()`.

> **Por qué POST y no PATCH:** registrar un contacto es registrar un *evento*, no actualizar el estado parcial de un recurso. Cada llamada crea un nuevo registro `contact_registered` en el activity log. Dos llamadas legítimas producen dos eventos distintos. Ver `docs/technical-notes.md` nota #38.

**Request:**
```json
{
  "contact_channel": "phone",
  "contact_notes": "Llamada de 15 minutos. Cliente confirmó interés para julio.",
  "next_action": "Enviar propuesta de paquete Cancún",
  "followup_at": "2026-06-05T10:00:00Z"
}
```

**Campos opcionales:** todos. Body puede estar vacío `{}`.

**`contact_channel` valores:** `phone`, `whatsapp`, `email`, `in_person`, `video_call`, `sms`, `other`

**Comportamiento:**
- Actualiza `last_contact_at=now()`
- Si se envían `next_action`/`followup_at`, los actualiza también
- Si se envía `contact_notes`, crea una `LeadNote` automáticamente
- Registra `contact_registered` en el activity log

**Response 200:** lead actualizado.

---

### PATCH /api/v1/leads/{id}/won

Marca el lead como ganado.

**Request:**
```json
{
  "won_at": "2026-06-01T15:30:00Z",
  "note": "Venta cerrada. Paquete Cancún 7 noches confirmado.",
  "metadata": {
    "final_amount": "5500",
    "booking_ref": "BK-99123"
  }
}
```

**Campos opcionales:** todos. Body puede estar vacío `{}`.

**`won_at`**: si no se envía, se usa `now()`.

**Comportamiento:**
- Actualiza `status=won`, `won_at`
- Mueve al stage terminal de tipo `won` si existe
- Si se envía `note`, crea una `LeadNote`
- Registra `lead_won` en el activity log

**Errores:** `422` si el lead ya está cerrado.

**Response 200:** lead con `status: "won"`.

---

### PATCH /api/v1/leads/{id}/lost

Marca el lead como perdido.

**Request:**
```json
{
  "lost_reason": "El cliente decidió no viajar este año.",
  "lost_at": "2026-06-01T15:30:00Z",
  "metadata": {
    "competitor": "OtraAgencia"
  }
}
```

**Campos obligatorios:** `lost_reason`

**`lost_at`**: si no se envía, se usa `now()`.

**Comportamiento:**
- Actualiza `status=lost`, `lost_at`, `lost_reason`
- Mueve al stage terminal de tipo `lost` si existe
- Registra `lead_lost` en el activity log

**Errores:** `422` si el lead ya está cerrado, `422` si falta `lost_reason`.

**Response 200:** lead con `status: "lost"`.

---

## Endpoints de pipeline

### GET /api/v1/pipeline/stages

Lista las etapas del pipeline del tenant en orden.

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Nuevo contacto",
      "slug": "new_contact",
      "order": 1,
      "color": "#6B7280",
      "is_initial": true,
      "is_terminal": false,
      "maps_to_status": null,
      "leads_count": 12
    },
    {
      "id": "uuid",
      "name": "Contactado",
      "slug": "contacted",
      "order": 2,
      "color": "#3B82F6",
      "is_initial": false,
      "is_terminal": false,
      "maps_to_status": null,
      "leads_count": 8
    },
    {
      "id": "uuid",
      "name": "Ganado",
      "slug": "won",
      "order": 5,
      "color": "#10B981",
      "is_initial": false,
      "is_terminal": true,
      "maps_to_status": "won",
      "leads_count": 25
    }
  ],
  "request_id": "req_a1b2c3d4"
}
```

---

## Endpoints de notas

### GET /api/v1/leads/{id}/notes

Lista todas las notas del lead en orden cronológico inverso.

**Query params:** `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "content": "El cliente confirmó interés para el próximo mes.",
      "author": {
        "id": "ext-user-abc",
        "name": "María Agente"
      },
      "created_at": "2026-06-01T10:00:00Z",
      "updated_at": "2026-06-01T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 4, "last_page": 1 },
  "request_id": "req_a1b2c3d4"
}
```

---

### POST /api/v1/leads/{id}/notes

Agrega una nota al lead.

> **Decisión fase 6.8:** Las notas **NO** actualizan `last_contact_at`. Una nota es un registro interno del agente; puede ser una observación, un recordatorio o información adicional que no implica necesariamente contacto con el cliente. Para registrar un contacto real (con canal explícito), usar `POST /contact`. Ver `docs/technical-notes.md` nota #41.

**Request:**
```json
{
  "content": "Llamada de seguimiento. Cliente pide cotización del paquete Europa.",
  "author_user_id": "ext-user-abc",
  "author_name": "María Agente"
}
```

**Campos obligatorios:** `content`

**`author_user_id`** y **`author_name`**: opcionales si el token ya identifica al usuario.

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "content": "Llamada de seguimiento. Cliente pide cotización del paquete Europa.",
    "author": { "id": "ext-user-abc", "name": "María Agente" },
    "created_at": "2026-06-01T10:00:00Z",
    "updated_at": "2026-06-01T10:00:00Z"
  },
  "request_id": "req_a1b2c3d4"
}
```

---

## Endpoints de actividad

### GET /api/v1/leads/{id}/activity

Historial completo de actividad del lead en orden cronológico inverso.

**Query params:** `page`, `per_page`, `event` (filtrar por tipo de evento)

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "event": "stage_changed",
      "description": "Etapa cambiada de 'Nuevo contacto' a 'Contactado'",
      "payload": {
        "from": { "id": "uuid", "name": "Nuevo contacto" },
        "to":   { "id": "uuid", "name": "Contactado" }
      },
      "causer": {
        "id": "ext-user-abc",
        "name": "María Agente",
        "type": "user"
      },
      "created_at": "2026-06-01T09:30:00Z"
    },
    {
      "id": "uuid",
      "event": "lead_created",
      "description": "Lead creado desde zend_vacations/landing_page",
      "payload": {},
      "causer": {
        "id": null,
        "name": "Sistema",
        "type": "api_client"
      },
      "created_at": "2026-05-28T08:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 7, "last_page": 1 },
  "request_id": "req_a1b2c3d4"
}
```

---

## Health check

### GET /api/health

Verifica que el servicio está activo. **No requiere autenticación.**

**Response 200:**
```json
{
  "status": "ok",
  "service": "leads-service",
  "version": "1.0.0",
  "timestamp": "2026-06-01T10:00:00Z"
}
```

---

## Rate limiting

| Tipo de cliente | Límite | Ventana |
|---|---|---|
| API externa (sistemas) | 120 req/min | por tenant |
| Agentes (panel/API) | 60 req/min | por usuario |
| Login | 5 intentos | por IP / 60 segundos |

Headers de respuesta cuando se acerca o supera el límite:

```
X-RateLimit-Limit:     60
X-RateLimit-Remaining: 12
X-RateLimit-Reset:     1717200060
Retry-After:           30      ← solo cuando 429
```

---

## Versionamiento

La API está versionada en la URL (`/api/v1`). Las reglas de compatibilidad:

| Tipo de cambio | Versión actual (`v1`) | Nueva versión (`v2`) |
|---|---|---|
| Agregar campo a respuesta | ✅ Compatible | — |
| Agregar parámetro opcional | ✅ Compatible | — |
| Cambiar nombre de campo | ❌ Breaking | Requiere `v2` |
| Eliminar campo | ❌ Breaking | Requiere `v2` |
| Cambiar tipo de campo | ❌ Breaking | Requiere `v2` |
| Cambiar código HTTP de éxito | ❌ Breaking | Requiere `v2` |

Mientras exista `v2`, `v1` se mantiene activa por al menos 6 meses con aviso de deprecación en headers:

```
Deprecation: true
Sunset: Sat, 31 Dec 2027 00:00:00 GMT
```
