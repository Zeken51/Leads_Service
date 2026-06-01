# Idempotencia — leads-service

> Fase 6.3 — Documento de referencia. Sin implementación todavía.

---

## ¿Qué es idempotencia en este contexto?

Una operación es **idempotente** cuando ejecutarla múltiples veces produce el mismo resultado que ejecutarla una sola vez. Esto es crítico en sistemas distribuidos donde los clientes pueden reintentar peticiones ante timeouts, errores de red o respuestas ambiguas.

En `leads-service` la idempotencia protege contra:
- Creación de leads duplicados por reintentos de red
- Leads duplicados cuando el sistema de origen envía el mismo lead más de una vez
- Condiciones de carrera en sistemas que envían leads en batch

---

## Dos niveles de idempotencia

### Nivel 1: Header `Idempotency-Key` (idempotencia de red)

Protege contra reintentos de la misma petición. El cliente envía un UUID único por operación:

```
POST /api/v1/leads
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

El servidor almacena la respuesta original en `IdempotencyKey` por un TTL de **24 horas**. Si la misma clave llega dentro del TTL, se retorna la respuesta almacenada sin re-ejecutar la operación.

### Nivel 2: Unicidad por datos (idempotencia semántica)

Protege contra leads duplicados con diferentes `Idempotency-Key` o sin ella. La combinación:

```
(tenant_id, source_system, external_reference_id)
```

debe ser **única** cuando `external_reference_id` no es nulo. Si ya existe un lead con esa combinación y la petición no lleva `Idempotency-Key`, se retorna `409 CONFLICT` con el `lead_id` del lead existente.

---

## Comportamiento completo

### Crear lead: matriz de comportamiento

| `Idempotency-Key` | `external_reference_id` | Lead existe? | Resultado |
|---|---|---|---|
| Presente, nueva | Cualquiera | No | `201` — lead creado, clave guardada |
| Presente, repetida (TTL activo) | Cualquiera | Sí (por la misma key) | `200` — respuesta original, header `Idempotent-Replayed: true` |
| Presente, repetida (TTL expirado) | Cualquiera | — | Trata como nueva clave → re-ejecuta |
| Ausente | Presente | No | `201` — lead creado |
| Ausente | Presente | Sí (misma combinación) | `409 CONFLICT` con lead_id existente |
| Ausente | Ausente | — | `201` — lead creado (sin protección de nivel 2) |
| Presente, usada con otro path | — | — | `400 IDEMPOTENCY_KEY_MISMATCH` |

### Headers de respuesta relacionados

```
Idempotent-Replayed: true     ← presente solo cuando se reproduce una respuesta cacheada
X-Request-ID: req_a1b2c3d4   ← siempre presente
```

---

## `external_reference_id` — identificador externo genérico

El campo `external_reference_id` es el identificador del lead en el **sistema de origen**. Es un campo de propósito general: cada tenant o sistema de origen le da el nombre y la semántica que corresponda a su dominio.

| Sistema | Nombre propio del sistema | Se mapea a |
|---|---|---|
| ZendVacations | `quote_id` (ID de cotización) | `external_reference_id` |
| Formulario web | ID de envío de formulario | `external_reference_id` |
| Otro CRM | ID de su propio lead | `external_reference_id` |
| Manual | Referencia libre | `external_reference_id` |

`external_reference_id` no tiene semántica de cotización ni de viajes. Es simplemente el identificador opaco del lead en el sistema externo, utilizado para garantizar unicidad por `(tenant_id, source_system, external_reference_id)`.

El hecho de que ZendVacations lo llame `quote_id` es específico de ese tenant. Otros tenants pueden mapear cualquier otro concepto a este campo.

---

## Endpoints que soportan `Idempotency-Key`

| Endpoint | Soporte | Notas |
|---|---|---|
| `POST /api/v1/leads` | **Sí — recomendado** | Principal caso de uso |
| `PATCH /api/v1/leads/{id}/stage` | Sí | Útil para evitar doble cambio de stage |
| `PATCH /api/v1/leads/{id}/assign` | Sí | |
| `PATCH /api/v1/leads/{id}/followup` | Sí | |
| `POST /api/v1/leads/{id}/contact` | Sí | |
| `PATCH /api/v1/leads/{id}/won` | Sí | |
| `PATCH /api/v1/leads/{id}/lost` | Sí | |
| `POST /api/v1/leads/{id}/notes` | Sí | |
| `GET` (cualquiera) | No aplica | Los GET son naturalmente idempotentes |

---

## Cómo generar una `Idempotency-Key`

La clave debe ser un **UUID v4** único por operación, no por sesión ni por cliente. Buenas prácticas:

```
✅ UUID v4 generado justo antes de la petición
✅ Hash determinista del payload (si se quiere idempotencia determinista)
❌ Timestamp (colisiones posibles en batch)
❌ Reutilizar la misma clave para operaciones distintas
❌ Secuencias numéricas (predecibles)
```

**Ejemplo en JavaScript:**
```js
const idempotencyKey = crypto.randomUUID();
```

**Ejemplo en PHP:**
```php
$idempotencyKey = Str::uuid()->toString();
```

---

## Caducidad (TTL)

- **TTL por defecto:** 24 horas desde la primera ejecución
- **Configurable** por variable de entorno: `IDEMPOTENCY_TTL_HOURS=24`
- Pasado el TTL, la clave expira y la misma operación puede ejecutarse nuevamente

Los registros expirados se limpian mediante un job programado (a implementar en fase 6.4).

---

## Almacenamiento

La tabla `idempotency_keys` almacena:

| Campo | Valor de ejemplo |
|---|---|
| `key` | `550e8400-e29b-41d4-a716-446655440000` |
| `tenant_id` | UUID del tenant |
| `method` | `POST` |
| `path` | `/api/v1/leads` |
| `response_status` | `201` |
| `response_body` | JSON completo de la respuesta |
| `expires_at` | +24h desde `created_at` |

El campo `key` tiene índice UNIQUE. La búsqueda es O(1) por clave.

---

## Casos extremos

**¿Qué pasa si la primera petición falla a mitad?**
Si el lead se creó pero la respuesta no llegó al cliente (timeout), el cliente reintenta con la misma `Idempotency-Key`. Dado que el lead ya existe, se retorna la respuesta almacenada con `200 + Idempotent-Replayed: true`.

**¿Qué pasa si el procesamiento fue parcial (lead creado, activity log no)?**
Las operaciones que generan múltiples registros (lead + activity log) deben ejecutarse dentro de una transacción de base de datos. Si la transacción falla, nada se guarda y la `IdempotencyKey` tampoco se crea. El cliente puede reintentar con la misma clave.

**¿Claves de distintos tenants pueden colisionar?**
No. La tabla `idempotency_keys` no tiene UNIQUE solo en `key` sino en `(key, tenant_id)`. Dos tenants pueden usar la misma UUID sin conflicto.
