# API Contracts v1 — leads-service

> Estado: BORRADOR. Contratos preliminares para alineación técnica.
> No implementados todavía. Actualizados en fase 6.2.

---

## Convenciones generales

- Base URL: `/api/v1`
- Formato: JSON (`Content-Type: application/json`)
- Autenticación: `Authorization: Bearer {token}`
- El `tenant_id` se extrae del token — **nunca en el body ni en query params**
- Idempotencia: header `Idempotency-Key: {uuid}` en operaciones POST/PATCH
- Paginación: cursor-based, parámetros `cursor` y `per_page`
- Timestamps: ISO 8601 UTC
- PKs: UUIDs en todas las entidades de negocio

---

## Idempotencia

Cualquier endpoint de escritura (POST, PATCH) acepta el header:

```
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

**Comportamiento:**
- Si la clave no existe: se ejecuta la operación y se guarda la respuesta
- Si la clave existe y está en TTL (24h): se retorna la respuesta almacenada con header `Idempotent-Replayed: true`
- Si la clave existe con diferente path/method: `400 IDEMPOTENCY_KEY_MISMATCH`
- El header es **opcional**. Sin él, la operación es idempotente solo a nivel de unicidad de datos

---

## Respuesta estándar

### Éxito con datos

```json
{
  "data": { ... }
}
```

### Éxito con lista

```json
{
  "data": [ ... ],
  "meta": {
    "next_cursor": "abc123",
    "per_page": 25,
    "total": 142
  }
}
```

### Error

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Descripción legible del error.",
    "details": {
      "campo": ["Mensaje de validación."]
    }
  }
}
```

---

## Autenticación

### POST /api/v1/auth/login

Genera un token de acceso para agentes del panel interno o sistemas externos.

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
    "token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": "2026-12-31T23:59:59Z"
  }
}
```

### POST /api/v1/auth/logout

Revoca el token actual.

**Response 204:** No content

---

## Leads

### Estructura de un lead

```json
{
  "id": "uuid",
  "source_system": "zend_vacations",
  "source_channel": "landing_page",
  "external_reference_id": "ZV-00123",
  "status": "active",
  "stage": {
    "id": "uuid",
    "name": "Contactado",
    "slug": "contacted",
    "order": 2
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
    "budget": "5000"
  },
  "created_at": "2026-05-28T08:00:00Z",
  "updated_at": "2026-06-01T09:30:00Z"
}
```

---

### GET /api/v1/leads

Lista leads del tenant autenticado.

**Query params:**

| Param | Tipo | Descripción |
|---|---|---|
| `status` | string | Filtrar por status: `active`, `won`, `lost`, `archived` |
| `stage_id` | uuid | Filtrar por etapa del pipeline |
| `assigned_to` | string | Filtrar por `assigned_user_id` externo |
| `source_system` | string | Filtrar por sistema de origen |
| `priority` | string | Filtrar por prioridad |
| `cursor` | string | Cursor de paginación |
| `per_page` | integer | Resultados por página (default: 25, max: 100) |

**Response 200:** lista paginada de leads

---

### POST /api/v1/leads

Crea un nuevo lead. Soporta idempotencia via `Idempotency-Key`.

**Headers:**
```
Authorization: Bearer {token}
Idempotency-Key: {uuid}  (recomendado)
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
    "metadata": {}
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
    "trip_type": "honeymoon"
  }
}
```

**Response 201:** lead creado

**Unicidad:** Si `(tenant_id, source_system, external_reference_id)` ya existe
sin `Idempotency-Key` → `409 CONFLICT`. Con la misma `Idempotency-Key` → `200`
con la respuesta original.

---

### GET /api/v1/leads/{id}

Detalle completo de un lead.

**Response 200:** lead con estructura completa

---

### PATCH /api/v1/leads/{id}

Actualiza campos editables del lead (nombre del cliente, prioridad, metadata, etc.).
No usar para cambios de stage, asignación ni acciones de estado (tienen endpoints propios).

**Response 200:** lead actualizado

---

### DELETE /api/v1/leads/{id}

Archiva el lead (soft delete). Cambia `status=archived`.

**Response 200:** lead con `status: archived`

---

### PATCH /api/v1/leads/{id}/stage

Mueve el lead a otra etapa del pipeline.

**Request:**
```json
{
  "stage_id": "uuid",
  "lost_reason": "El cliente eligió a la competencia"
}
```

> `lost_reason` obligatorio si el stage es terminal de tipo `lost`

**Response 200:** lead actualizado con nueva stage y posible cambio de status

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

**Response 200:** lead actualizado

---

### PATCH /api/v1/leads/{id}/followup

Registra la próxima acción y fecha de seguimiento.

**Request:**
```json
{
  "next_action": "Enviar propuesta por email",
  "followup_at": "2026-06-10T14:00:00Z"
}
```

**Response 200:** lead actualizado

---

### POST /api/v1/leads/{id}/contact

Registra que hubo contacto con el lead. Actualiza `last_contact_at`.

**Request:** body vacío o con nota opcional
```json
{
  "note": "Llamada de 15 minutos, cliente confirmó interés."
}
```

**Response 200:** lead actualizado. Si se incluye nota, también crea una `LeadNote`.

---

### PATCH /api/v1/leads/{id}/won

Marca el lead como ganado directamente.

**Request:** body vacío o con nota opcional
```json
{
  "note": "Venta cerrada. Paquete Cancún 7 noches."
}
```

**Response 200:** lead con `status: won`, `won_at` lleno

---

### PATCH /api/v1/leads/{id}/lost

Marca el lead como perdido.

**Request:**
```json
{
  "lost_reason": "El cliente decidió no viajar este año."
}
```

> `lost_reason` es **obligatorio**

**Response 200:** lead con `status: lost`, `lost_at` y `lost_reason` llenos

---

## Pipeline

### GET /api/v1/pipeline/stages

Lista las etapas del pipeline del tenant autenticado.

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Nuevo contacto",
      "slug": "new_contact",
      "order": 1,
      "color": "#3B82F6",
      "is_initial": true,
      "is_terminal": false,
      "maps_to_status": null
    },
    {
      "id": "uuid",
      "name": "Ganado",
      "slug": "won",
      "order": 5,
      "color": "#10B981",
      "is_initial": false,
      "is_terminal": true,
      "maps_to_status": "won"
    }
  ]
}
```

---

## Notas

### GET /api/v1/leads/{id}/notes

Lista notas del lead en orden cronológico inverso.

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
      "created_at": "2026-06-01T10:00:00Z"
    }
  ]
}
```

### POST /api/v1/leads/{id}/notes

Agrega una nota al lead. Actualiza `last_contact_at` del lead.

**Request:**
```json
{
  "content": "Llamada de seguimiento. Cliente pide cotización."
}
```

**Response 201:** nota creada

---

## Activity Log

### GET /api/v1/leads/{id}/activity

Log de actividad del lead en orden cronológico inverso.

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
        "to": { "id": "uuid", "name": "Contactado" }
      },
      "causer": {
        "id": "ext-user-abc",
        "name": "María Agente",
        "type": "user"
      },
      "created_at": "2026-06-01T09:30:00Z"
    }
  ]
}
```

---

## Health check

### GET /api/health

Verifica que el servicio está activo.

**Response 200:**
```json
{
  "status": "ok",
  "service": "leads-service",
  "version": "1.0.0"
}
```

---

## Códigos de error estándar

| HTTP | Código | Descripción |
|---|---|---|
| 400 | VALIDATION_ERROR | Datos de entrada inválidos |
| 400 | IDEMPOTENCY_KEY_MISMATCH | La clave fue usada con un endpoint diferente |
| 401 | UNAUTHENTICATED | Token ausente o inválido |
| 403 | FORBIDDEN | Sin permiso para el recurso del tenant |
| 404 | NOT_FOUND | Recurso no existe |
| 409 | CONFLICT | Lead duplicado (sin Idempotency-Key) |
| 422 | UNPROCESSABLE | Regla de negocio violada (ej: mover stage en lead cerrado) |
| 429 | RATE_LIMITED | Demasiadas peticiones del tenant |
