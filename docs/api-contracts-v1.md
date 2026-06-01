# API Contracts v1 — leads-service

> Estado: BORRADOR. Contratos preliminares para alineación técnica.
> No implementados todavía.

## Convenciones generales

- Base URL: `/api/v1`
- Formato: JSON (`Content-Type: application/json`)
- Autenticación: `Authorization: Bearer {token}`
- Idempotencia: header opcional `Idempotency-Key: {uuid}`
- Paginación: cursor-based, parámetro `cursor`
- Timestamps: ISO 8601 UTC

## Autenticación

### POST /api/v1/auth/login
Genera un token de acceso.

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
  "token": "1|abc123...",
  "expires_at": "2025-12-31T23:59:59Z"
}
```

### POST /api/v1/auth/logout
Revoca el token actual.

**Response 204:** No content

---

## Leads

### GET /api/v1/leads
Lista leads del tenant autenticado.

**Query params:** `status`, `assigned_to`, `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Juan Pérez",
      "email": "juan@empresa.com",
      "phone": "+52 55 1234 5678",
      "status": "new",
      "stage_id": "uuid",
      "assigned_to": "uuid",
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "next_cursor": "abc123",
    "per_page": 25
  }
}
```

### POST /api/v1/leads
Crea un nuevo lead.

**Request:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@empresa.com",
  "phone": "+52 55 1234 5678",
  "source": "web_form",
  "stage_id": "uuid",
  "metadata": {}
}
```

**Response 201:**
```json
{
  "data": { ...lead }
}
```

### GET /api/v1/leads/{id}
Detalle de un lead.

### PATCH /api/v1/leads/{id}
Actualiza campos del lead.

### DELETE /api/v1/leads/{id}
Archiva (soft delete) el lead.

---

## Pipeline

### GET /api/v1/pipeline/stages
Lista las etapas del pipeline del tenant.

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Nuevo",
      "order": 1,
      "color": "#3B82F6"
    }
  ]
}
```

### PATCH /api/v1/leads/{id}/stage
Mueve un lead a otra etapa.

**Request:**
```json
{
  "stage_id": "uuid"
}
```

**Response 200:** lead actualizado

---

## Notas

### GET /api/v1/leads/{id}/notes
Lista notas de un lead.

### POST /api/v1/leads/{id}/notes
Agrega una nota.

**Request:**
```json
{
  "content": "El cliente confirmó interés para el próximo mes."
}
```

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "content": "...",
    "author": { "id": "uuid", "name": "Agente" },
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

---

## Activity Log

### GET /api/v1/leads/{id}/activity
Lista el log de actividad de un lead en orden cronológico inverso.

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "event": "stage_changed",
      "description": "Movido de Nuevo a Contactado",
      "causer": { "id": "uuid", "name": "Agente" },
      "created_at": "2025-01-15T10:05:00Z"
    }
  ]
}
```

---

## Códigos de error estándar

| HTTP | Código | Descripción |
|---|---|---|
| 400 | VALIDATION_ERROR | Datos de entrada inválidos |
| 401 | UNAUTHENTICATED | Token ausente o inválido |
| 403 | FORBIDDEN | Sin permiso para el recurso |
| 404 | NOT_FOUND | Recurso no existe |
| 409 | CONFLICT | Conflicto de estado |
| 422 | UNPROCESSABLE | Regla de negocio violada |
| 429 | RATE_LIMITED | Demasiadas peticiones |

**Formato de error:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "El campo email es requerido.",
    "details": {
      "email": ["El campo email es requerido."]
    }
  }
}
```
