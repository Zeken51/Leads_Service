# Manejo de errores API — leads-service

> Fase 6.3 — Documento de referencia. Sin implementación todavía.

---

## Formato estándar de error

Todos los errores de la API tienen el mismo envelope:

```json
{
  "message": "Descripción legible del error.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `message` | string | Mensaje legible, siempre presente |
| `errors` | object | Detalle de campos con error (vacío `{}` si no aplica) |
| `request_id` | string | ID de trazabilidad de la petición |

---

## `request_id`

Cada petición recibe un ID único de trazabilidad con prefijo `req_` seguido de 8 caracteres aleatorios.

- Si el cliente envía el header `X-Request-ID`, ese valor se usa como `request_id`
- Si no, el servidor lo genera automáticamente
- El `request_id` aparece en **todas las respuestas** — exitosas y de error — como campo en el body JSON
- También se devuelve siempre en el header de respuesta: `X-Request-ID: req_a1b2c3d4`
- **Excepción:** `GET /api/health` devuelve status sin `request_id` en body (endpoint no autenticado, sin contexto de request)
- Útil para correlacionar cualquier respuesta (éxito o error) con los logs del servidor

---

## Formato de errores de validación (422 / 400)

Cuando hay errores en campos específicos, `errors` contiene un mapa de campo → lista de mensajes. Los campos anidados usan notación con punto:

```json
{
  "message": "Validation failed.",
  "errors": {
    "customer.email": ["The customer.email field must be a valid email address."],
    "customer.name": ["The customer.name field is required."],
    "source_system": ["The source_system field is required."],
    "followup_at": ["The followup_at field must be a date after now."]
  },
  "request_id": "req_x9y8z7w6"
}
```

---

## Catálogo de errores

### 400 Bad Request

Petición mal formada o con un parámetro inválido que no es de validación de campos.

```json
{
  "message": "The Idempotency-Key header was already used with a different request.",
  "errors": {
    "idempotency_key": ["This key was used for POST /api/v1/leads. Current request is POST /api/v1/leads/xxx/notes."]
  },
  "request_id": "req_a1b2c3d4"
}
```

**Códigos internos de este tipo:**

| Código interno | Descripción |
|---|---|
| `BAD_REQUEST` | Petición genéricamente mal formada |
| `IDEMPOTENCY_KEY_MISMATCH` | La clave fue usada con un endpoint diferente |
| `INVALID_JSON` | El body no es JSON válido |

---

### 401 Unauthorized

El token está ausente, es inválido o fue revocado.

```json
{
  "message": "Unauthenticated.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

El cliente debe obtener un nuevo token via `POST /api/v1/auth/login`.

---

### 403 Forbidden

El token es válido pero el recurso solicitado no pertenece al tenant del token, o el usuario no tiene el permiso necesario.

```json
{
  "message": "You do not have permission to access this resource.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

> **Importante:** Para ocultar la existencia de recursos de otros tenants, este error también se retorna cuando un lead existe pero pertenece a otro tenant (en lugar de 404).

---

### 404 Not Found

El recurso no existe dentro del tenant del token.

```json
{
  "message": "Lead not found.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

---

### 409 Conflict

Se intenta crear un lead que ya existe (duplicado por `external_reference_id`) sin usar `Idempotency-Key`.

```json
{
  "message": "A lead with this external_reference_id already exists for this tenant and source_system.",
  "errors": {
    "external_reference_id": ["Already exists: lead_id 550e8400-e29b-41d4-a716-446655440000"]
  },
  "request_id": "req_a1b2c3d4"
}
```

La respuesta incluye el `lead_id` existente en `errors` para que el cliente pueda recuperarlo.

---

### 422 Unprocessable Entity

La petición es válida en formato pero viola una regla de negocio.

**Ejemplos:**

Intentar cambiar stage en un lead cerrado:
```json
{
  "message": "Cannot change stage of a closed lead.",
  "errors": {
    "stage_id": ["Lead status is 'won'. Stage changes are not allowed on closed leads."]
  },
  "request_id": "req_a1b2c3d4"
}
```

Intentar marcar como perdido sin `lost_reason`:
```json
{
  "message": "Validation failed.",
  "errors": {
    "lost_reason": ["The lost_reason field is required when closing a lead as lost."]
  },
  "request_id": "req_a1b2c3d4"
}
```

**Códigos internos de este tipo:**

| Código interno | Descripción |
|---|---|
| `LEAD_ALREADY_CLOSED` | El lead está en status won o lost |
| `INVALID_STAGE_TRANSITION` | La transición de stage no está permitida |
| `MISSING_LOST_REASON` | Se intentó cerrar como perdido sin motivo |
| `STAGE_NOT_IN_TENANT` | El stage_id no pertenece al tenant |

---

### 429 Too Many Requests

El cliente superó el límite de peticiones configurado por tenant.

```json
{
  "message": "Too many requests. Please slow down.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

**Headers adicionales en la respuesta:**

```
Retry-After: 30
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1717200000
```

Los límites por defecto: 60 peticiones por minuto por tenant.

---

### 500 Internal Server Error

Error inesperado del servidor. El `request_id` es clave para correlacionar con los logs del servidor.

```json
{
  "message": "An unexpected error occurred. Please contact support with the request_id.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

> En entorno `APP_DEBUG=true` (desarrollo local), la respuesta incluirá el stack trace bajo una clave `debug`. En producción, nunca se expone información de stack.

---

## Resumen de códigos HTTP

| HTTP | Cuándo ocurre |
|---|---|
| `400` | Body mal formado, JSON inválido, o parámetro técnicamente incorrecto |
| `401` | Sin token, token inválido o expirado |
| `403` | Token válido pero sin acceso al recurso |
| `404` | Recurso no existe en el tenant |
| `409` | Duplicado de lead sin idempotency key |
| `422` | Regla de negocio violada (lead cerrado, lost_reason faltante, etc.) |
| `429` | Rate limit excedido |
| `500` | Error interno del servidor |

---

## Recomendaciones para clientes

1. **Siempre leer `request_id`** y loguearlo junto con la petición para facilitar soporte.
2. **En 422**, leer `errors` para mostrar mensajes específicos por campo al usuario.
3. **En 409**, leer el `lead_id` en `errors.external_reference_id` para recuperar el lead existente.
4. **En 401**, re-autenticar y reintentar la petición original una sola vez.
5. **En 429**, respetar el header `Retry-After` antes de reintentar.
6. **En 500**, no reintentar automáticamente. Reportar con `request_id`.
