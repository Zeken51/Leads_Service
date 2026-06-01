# Autenticación API — leads-service

> Fase 6.3 — Documento de referencia. Sin implementación todavía.

---

## Resumen

El microservicio expone dos mecanismos de autenticación separados según el tipo de cliente:

| Mecanismo | Aplica a | Implementación |
|---|---|---|
| Sesión + CSRF | Panel interno (Inertia/Vue) | Laravel Breeze |
| Bearer Token | API externa (sistemas, agentes vía API) | Laravel Sanctum |

Las rutas bajo `/api/v1` **siempre requieren Bearer Token**. No hay endpoints públicos sin autenticación, excepto `GET /api/health`.

---

## Bearer Tokens (API)

### Emisión del token

```
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "sistema@zendvacations.com",
  "password": "secret"
}
```

**Respuesta 200:**
```json
{
  "data": {
    "token": "7|hR3kLmN9pQ...",
    "token_type": "Bearer",
    "expires_at": "2027-06-01T00:00:00Z"
  },
  "request_id": "req_a1b2c3d4"
}
```

El token emitido por Sanctum funciona como un Bearer opaco vinculado internamente al usuario/tenant. Es el mecanismo estándar de Laravel Sanctum para API tokens.

> **Nota:** La tarea menciona JWT. Sanctum puede configurarse para emitir tokens JWT si se integra con `tymon/jwt-auth`. En esta etapa se usa el token Sanctum estándar (Bearer opaco), que es funcionalmente equivalente para este caso de uso. Registrado como duda abierta en `technical-notes.md`.

---

### Uso del token

Todas las peticiones a `/api/v1` deben incluir el header:

```
Authorization: Bearer {token}
```

Headers recomendados en cada petición:

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
X-Request-ID: {uuid}            ← opcional, para trazabilidad
Idempotency-Key: {uuid}         ← recomendado en POST/PATCH
```

---

### Tenant binding

Cada token está asociado a exactamente **un tenant**. El `tenant_id` se extrae del token automáticamente por el middleware. El cliente **nunca debe enviar `tenant_id` en el body ni en query params**: es un campo derivado del token.

```
Token ──→ User ──→ tenant_id (vinculado al crear el token)
```

Si un sistema externo (ej: ZendVacations) necesita enviar leads de múltiples tenants, debe obtener un token distinto por tenant.

---

### Source system binding

Se recomienda que los tokens de sistemas externos queden vinculados a un `source_system` específico. Por ejemplo, el token de ZendVacations solo puede crear leads con `source_system: "zend_vacations"`.

Esta restricción se implementará como validación en la capa de negocio (no como característica del token en esta etapa). Registrado como mejora futura.

---

### Expiración y renovación

Los tokens no tienen expiración automática configurada en Sanctum por defecto. Para el entorno de producción se recomienda:

- Tokens de sistemas externos: larga duración (1 año), renovación manual
- Tokens de agentes vía API: duración media (90 días)
- Tokens del panel interno: gestionados por la sesión de Breeze (no Bearer)

La revocación de un token se realiza con:

```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

---

### Errores de autenticación

| Situación | HTTP | Código |
|---|---|---|
| Token ausente | 401 | `UNAUTHENTICATED` |
| Token inválido o expirado | 401 | `UNAUTHENTICATED` |
| Token revocado | 401 | `UNAUTHENTICATED` |
| Token válido pero sin permiso para el recurso | 403 | `FORBIDDEN` |

```json
{
  "message": "Unauthenticated.",
  "errors": {},
  "request_id": "req_a1b2c3d4"
}
```

---

## Sesión web (panel interno)

El panel interno con Inertia/Vue usa autenticación por sesión a través de Laravel Breeze. El flujo es:

1. Agente accede a `https://leads.zendlogic.com/login`
2. Envía credenciales por formulario
3. Laravel crea sesión y setea cookie de sesión
4. Todas las peticiones Inertia posteriores incluyen la cookie + CSRF token automáticamente

Este mecanismo **no aplica** a la API externa. Las peticiones AJAX del panel usan la sesión existente, no Bearer tokens.

---

## Seguridad adicional

- Rate limiting en el endpoint de login: máx 5 intentos por IP en 60 segundos
- Rate limiting en rutas API: ver `docs/api-contracts-v1.md`
- Los tokens se almacenan con hash en la base de datos (Sanctum por defecto)
- HTTPS obligatorio en producción (`https://leads.zendlogic.com`)
