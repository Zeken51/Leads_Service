# Notas técnicas — leads-service

## Decisiones tomadas

### 1. Laravel Breeze para panel interno + Sanctum para API
Se mantiene Breeze (sesión + CSRF) para el panel interno con Inertia, y
Sanctum (tokens Bearer) para la API externa. Esto evita mezclar dos mecanismos
de auth en las mismas rutas y mantiene cada flujo limpio.

### 2. Arquitectura orientada al dominio (sin DDD estricto)
Se propone separar `app/Domain/` para la lógica de negocio, `app/Actions/` para
casos de uso atómicos y `app/Services/` para orquestación. No se adopta DDD
completo (bounded contexts, eventos de dominio) para mantener la complejidad
manejable en esta etapa.

### 3. Multi-tenancy por `tenant_id` (shared database)
Se eligió multi-tenancy por columna (no por schema ni por base de datos separada)
dado que el servidor ya maneja múltiples schemas y escalar a una base por tenant
no está justificado actualmente. Se mitigará el riesgo de fugas con un Global
Scope obligatorio en todos los modelos.

> **Fase 6.2:** El campo fue renombrado de `organization_id` a `tenant_id` para
> mayor claridad semántica. Todos los documentos y futuras migraciones deben usar `tenant_id`.

### 4. UUIDs como PK en modelos de dominio
Los modelos de negocio usarán UUIDs para facilitar la integración con sistemas
externos, evitar enumeración de IDs y facilitar una posible migración tecnológica.

### 5. bootstrap.js faltante — corregido en fase 6.1
El archivo `resources/js/bootstrap.js` no fue incluido en el commit inicial.
Se creó manualmente con la configuración estándar de Axios.

### 6. Usuarios como referencias externas con snapshots *(fase 6.2)*
Los usuarios (agentes) no se almacenan como entidad propia en este microservicio.
Se referencia al usuario externo por `assigned_user_id` (opaco) y se almacenan
snapshots (`assigned_user_name_snapshot`, `assigned_user_email_snapshot`,
`assigned_user_provider`) en el momento de la asignación.

**Razón:** el microservicio no tiene acceso al sistema de usuarios del cliente.
Los snapshots garantizan trazabilidad histórica si el usuario es modificado o eliminado.

### 7. `customer` embebido en Lead, no tabla separada *(fase 6.2)*
Los datos del prospecto (nombre, email, teléfono, país) se almacenan como columnas
propias en `leads`, no como tabla separada. El `customer` en este contexto es
el prospecto en contexto de venta, no una identidad global.

**Razón:** evitar joins innecesarios y simplificar el modelo para esta etapa.
Si en el futuro se requiere deduplicación de clientes entre tenants, se puede
migrar a una tabla `customers` sin romper contratos API (el campo `customer`
en las respuestas JSON permanece igual).

### 8. Idempotencia en dos niveles *(fase 6.2)*
Se implementará idempotencia en dos niveles complementarios:
- **Header `Idempotency-Key`**: para clientes que mandan la misma petición varias veces (red)
- **Unicidad por datos** `(tenant_id, source_system, external_reference_id)`: previene duplicados semánticos incluso sin el header

### 9. `status` vs `stage` son conceptos distintos *(fase 6.2)*
- `status`: estado del sistema (active, won, lost, archived) — finito, controlado por el microservicio
- `stage`: posición en el pipeline comercial — configurable por tenant

Un lead puede estar en `status=active` y en cualquier `stage`. Al llegar a un
stage terminal, el status se actualiza automáticamente.

### 10. `lost_reason` obligatorio al cerrar como perdido *(fase 6.2)*
Se requiere `lost_reason` para forzar al agente a documentar el motivo. Esto
alimenta análisis de conversión en el futuro.

### 11. Paginación page-based en lugar de cursor-based *(fase 6.3)*
Se cambió de cursor-based (decisión preliminar en fase 6.2) a paginación por página (`page` + `per_page`).

**Razón:** Los casos de uso del panel de agentes requieren navegación por número de página exacto ("ir a página 3"), ordenamiento dinámico y conteo total de resultados — funcionalidades que no son naturales con cursores. El volumen esperado por tenant no justifica la complejidad de cursor-based.

**Impacto:** Los clientes API que implementen paginación deben usar `page`/`per_page`, no `cursor`.

### 12. `request_id` universal en todas las respuestas *(fase 6.3)*
Todas las respuestas de la API incluyen un campo `request_id` con formato `req_{8chars}`. El cliente puede enviar su propio ID en el header `X-Request-ID`; si no, el servidor lo genera.

**Razón:** Trazabilidad end-to-end entre logs del servidor y reportes del cliente. Crítico para debug en producción.

**Implementación:** Un middleware `RequestIdMiddleware` debe ejecutarse antes de todos los demás, inyectando el ID en el contexto de la request.

### 13. Formato de error unificado con `message` + `errors` + `request_id` *(fase 6.3)*
Se cambió el formato de error de `{ "error": { "code":..., "message":... } }` al formato:
`{ "message": "...", "errors": {...}, "request_id": "..." }`.

**Razón:** El nuevo formato es más cercano al comportamiento nativo de Laravel (validación), más familiar para developers PHP/Laravel, y más interoperable para una futura migración NestJS (que usa el mismo patrón por defecto).

**Impacto:** Cualquier cliente que ya consuma la API debe actualizar su manejo de errores. Al ser una etapa pre-implementación, no hay impacto hoy.

### 14. `contact_channel` pertenece al activity log, no al Lead *(fase 6.3)*
El endpoint `POST /leads/{id}/contact` acepta un campo `contact_channel` (`phone`, `whatsapp`, `email`, `in_person`, `video_call`, `sms`, `other`). Este valor se guarda como columna dedicada en `lead_activity_logs`, **no en la tabla `leads`**.

El Lead sólo recibe la actualización de `last_contact_at`. El canal de contacto es metadata del evento de actividad, no una propiedad permanente del lead.

**Razón:** El canal de contacto puede variar en cada interacción. Almacenarlo en el activity log permite análisis histórico (ej: qué canal genera mejor conversión) sin contaminar el modelo principal del lead.

### 16. Estructura del dominio implementada *(fase 6.4)*

```
app/Domain/
├── Concerns/HasTenant.php          ← trait aplicado a todos los modelos de negocio
├── Tenants/TenantContext.php       ← portador estático del tenant_id activo
├── Tenants/TenantScope.php         ← Global Scope aplicado vía HasTenant
├── Leads/Enums/                    ← LeadStatus, LeadPriority, LeadEvent, CauserType, ContactChannel
├── Leads/Models/                   ← Lead, LeadNote, LeadActivityLog
├── Pipeline/Models/PipelineStage
└── Idempotency/Models/IdempotencyKey
```

El `TenantContext` se activa cuando el middleware de autenticación (fase 6.5) llame a `TenantContext::set($tenantId)`. Hasta entonces, el Global Scope no filtra (comportamiento seguro para seeders y tests).

### 17. `event_type` como nombre de columna en `lead_activity_logs` *(fase 6.4)*

El task brief especificó `event_type` como nombre de columna. Los docs anteriores usaban `event`. Se adoptó `event_type` (más explícito) en la tabla. La API Resource de fase 6.5 lo mapeará a `event` en la respuesta JSON para mantener el contrato documentado en `api-contracts-v1.md`.

### 18. `content` como nombre en `lead_notes` (no `note`) *(fase 6.4)*

El task brief mencionó `note` como campo, pero el domain model y los contratos API usan `content`. Se implementó `content` para mantener consistencia con los contratos ya documentados.

### 19. `request_hash` en `idempotency_keys` *(fase 6.4)*

Se agregó un campo `request_hash` (SHA-256 de method + path + body normalizado) a la tabla `idempotency_keys`. Permite detectar peticiones idénticas por contenido incluso cuando el cliente no envía `Idempotency-Key`. El middleware de idempotencia (fase 6.5) lo calculará y usará como fallback.

### 20. `lead_id` nullable en `idempotency_keys` *(fase 6.4)*

El campo `lead_id` en `idempotency_keys` es nullable porque el registro de idempotencia se crea antes de que el lead exista (para capturar el intento). Se rellena con el UUID del lead creado exitosamente.

### 21. Índice único `(tenant_id, source_system, external_reference_id)` en `leads` *(fase 6.4)*

Se implementó como unique index named `leads_tenant_source_ext_ref_unique`. MySQL permite múltiples NULLs en columnas de un índice único, por lo que filas con `external_reference_id = NULL` no colisionan entre sí. La unicidad solo se aplica cuando los tres valores son no nulos.

### 15. JWT vs Sanctum Bearer — duda abierta *(fase 6.3)*
El brief menciona "JWT" pero la implementación actual usará tokens Bearer de Sanctum (opacos, no JWT firmados). Sanctum puede configurarse para emitir JWT reales si se integra `tymon/jwt-auth` o si se usa Sanctum con `PersonalAccessToken` + formato JWT.

**Por ahora:** usar Sanctum estándar. Si se requiere JWT real (para validación sin DB lookup, para claims enriquecidos o para compartir tokens entre microservicios), se evaluará en fase 6.5. Registrado como duda abierta.

---

## Riesgos identificados

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Base de datos compartida con otros proyectos | Alto | Schema dedicado `leads_service`, nunca cruzar queries |
| Fuga de datos entre tenants | Alto | Global Scope `HasTenant` obligatorio + tests de aislamiento |
| Ausencia de tests desde el inicio | Medio | Incluir feature tests desde fase 6.4 |
| Crecimiento del panel Inertia sin estructura | Medio | Definir estructura Pages/Components antes de fase 6.7 |
| Idempotencia implementada tarde | Medio | Implementar en fase 6.4, antes de exponer a sistemas externos |
| Usuarios externos sin validación | Bajo | Se acepta por diseño — se guarda snapshot, no se valida contra sistema externo |
| Pipeline sin etapa inicial configurada | Bajo | Manejar con gracia: `stage=null` si el tenant no tiene pipeline configurado |

---

## Dudas abiertas

| Duda | Contexto | Prioridad |
|---|---|---|
| ¿Se almacena metadata del tenant? | Si el tenant necesita nombre/config, se necesita tabla `tenants` | Media |
| ¿Los stages son compartidos o por tenant? | Por diseño actual: por tenant. Confirmar si hay stages globales | Media |
| ¿`followup_at` y `next_action` son bloqueantes para avanzar de stage? | Por ahora son opcionales. Confirmar si deben ser obligatorios en fases futuras | Baja |
| ¿Se requieren webhooks de salida? | No está en alcance pero es común en integraciones comerciales | Baja |
| ¿El panel interno maneja múltiples tenants o un solo tenant? | Impacta el diseño del panel Inertia en fase 6.8 | Media |
| ¿JWT real o Sanctum Bearer opaco? | Sanctum estándar por ahora. Evaluar si se necesita JWT firmado en fase 6.5 | Media |
| ¿Los tokens de API se vinculan a un `source_system` específico? | Mejora de seguridad pendiente. Un token de ZendVacations solo debería crear leads con `source_system=zend_vacations` | Baja |

---

## Recomendaciones para implementación

1. **Crear `HasTenant` trait primero** antes de cualquier modelo de dominio. Todos los modelos lo usarán.
2. **Crear `IdempotencyKey` antes que `Lead`** para que el middleware esté listo cuando se exponga el endpoint de creación.
3. **Seeders por tenant** desde el inicio para facilitar pruebas locales y del panel interno.
4. **Usar `after_commit` en observers del activity log** para evitar registrar actividad de operaciones que luego hacen rollback.
5. **No mezclar flujos**: los endpoints de cambio de estado (`/won`, `/lost`, `/stage`) deben ir a Actions dedicadas, no al método `update` genérico.
6. **Probar aislamiento multi-tenant con dos tenants desde el primer seeder** para detectar fugas temprano.

---

## Observaciones del stack

- **Vite ^8.0**: versión muy reciente, puede tener cambios de API respecto a documentación de Laravel.
- **Inertia ^2.0**: versión mayor con cambios en la forma de compartir props; verificar compatibilidad con Breeze.
- **Laravel 13**: versión más reciente; la comunidad puede tener menos recursos que Laravel 11/12.
- **PHP 8.3 ZTS**: la variante Thread Safe (ZTS) es inusual en producción; confirmar que el entorno de deploy usa NTS.

---

### 22. `external_reference_id` es opcional — sin deduplicación nivel 2 si es NULL *(fix post-6.4)*

`external_reference_id` no es obligatorio para ningún tenant. Tenants que ingresan leads manualmente o desde sistemas sin ID externo pueden omitirlo. Cuando está presente, activa la protección de deduplicación por datos `(tenant_id, source_system, external_reference_id)`. Cuando está ausente, solo el `Idempotency-Key` header protege contra duplicados.

El índice `UNIQUE(tenant_id, source_system, external_reference_id)` en MySQL 8 permite múltiples filas con `external_reference_id = NULL` bajo el mismo tenant/source_system — es el comportamiento correcto e intencional.

### 23. `status` y `stage_id` son fuentes de verdad distintas — sincronización es responsabilidad de la lógica de negocio *(fix post-6.4)*

`status` (active/won/lost/archived) es la fuente de verdad del estado del sistema. `stage_id` es la fuente de verdad de la posición comercial en el pipeline. No son redundantes. La sincronización entre ellos ocurre en las Actions (capa de negocio), nunca en triggers de DB ni en el modelo. Ver `domain-model.md` sección 5 para la tabla de reglas completa.

`stage_id` puede ser NULL cuando el tenant no tiene pipeline configurado — en ese caso `status` es el único indicador de estado.

### 24. Estrategia segura para `TenantContext` en todos los contextos *(fix post-6.4)*

El `TenantScope` solo aplica cuando `TenantContext::isSet()` es `true`. Esto es intencional y seguro por defecto:

| Contexto | Comportamiento | Acción requerida |
|---|---|---|
| HTTP request | Middleware `SetTenantFromToken` llama `set()` y `clear()` | Automático en fase 6.5 |
| Queue worker/Job | `TenantContext` **no está seteado** — el scope no filtra | El Job debe llamar `TenantContext::withTenant($this->tenantId, fn()=>...)` en `handle()` |
| Artisan command | `TenantContext` no está seteado — el scope no filtra | Pasar `tenant_id` explícito al crear modelos, o usar `withTenant()` |
| Database seeder | `TenantContext` no está seteado — el scope no filtra | Pasar `tenant_id` explícito en el array de datos, o llamar `TenantContext::set()` al inicio |
| Tests | `TenantContext` no está seteado por defecto | Llamar `TenantContext::withTenant($tenantId, fn()=>...)` en el test, o `set()`/`clear()` en setUp/tearDown |

Se agregó `TenantContext::withTenant(string $tenantId, callable $callback): mixed` que establece el contexto, ejecuta el callback y restaura el estado previo en un bloque `try/finally`. Es el patrón recomendado para jobs y tests.

**Riesgo mitigado:** Si un Job olvida setear el contexto, las queries no filtrarán por tenant. Para prevenir esto, en fase 6.5 se documentará un contrato: "Todo Job que use modelos con `HasTenant` DEBE llamar `withTenant()` en `handle()`."

Se corrigió también `self $model` → `Model $model` en el closure del trait `HasTenant` para evitar ambigüedad de resolución de `self` en traits de PHP.

### 25. `TenantApiClient` como modelo separado para clientes machine-to-machine *(fase 6.5)*

Se creó `tenant_api_clients` como tabla y modelo separado de `users`. Tiene `HasApiTokens` de Sanctum, por lo que puede emitir Bearer tokens. Sus tokens llevan `source_system` y `source_channel` del cliente, lo que permite que el `RequestContext` los exponga automáticamente sin que el cliente los envíe en el body.

**Razón:** Separar clientes API de sistema (ZendVacations, forms, etc.) de usuarios humanos del panel.

### 26. `RequestContext` como DTO inmutable por request *(fase 6.5)*

`RequestContext` es un readonly-property DTO construido en dos pasos:
1. `SetRequestId` lo crea con solo `request_id` y lo registra en el contenedor
2. `SetTenantContext` lo reemplaza con una copia enriquecida (`withAuth()`) que incluye tenant, client y abilities

Se registra en `app()->instance(RequestContext::class, $context)` para que los controllers lo reciban por inyección de dependencias.

### 27. Exception handler centralizado en `bootstrap/app.php` *(fase 6.5)*

Laravel 11 configura excepciones en `bootstrap/app.php` via `withExceptions()`. El renderable verifica `$request->expectsJson()` para aplicar el formato `{ message, errors, request_id }` solo a rutas API. Las rutas web siguen con el handler por defecto de Laravel/Inertia.

### 29. `personal_access_tokens.tokenable_id` cambiado a `varchar(255)` *(fix post-6.5)*

Sanctum crea `tokenable_id` como `BIGINT UNSIGNED`, pero `TenantApiClient` usa UUID como PK. Se creó la migración `2026_06_01_200002` para cambiar la columna a `VARCHAR(255)`. MySQL acepta enteros (IDs de `users`) en columnas varchar sin pérdida. La migración es no-op en SQLite (driver dinámicamente tipado).

### 30. Flujo de auth: `users` para panel interno, `TenantApiClient` para clientes externos *(fix post-6.5)*

Distinción documentada:
- `users` → agentes humanos del panel Inertia, pueden obtener tokens API con `ApiAbility::forAgent()`
- `TenantApiClient` → sistemas externos (ZendVacations, web forms), tokens de larga duración con `ApiAbility::forExternalCreator()` o custom
- Login endpoint (`/api/v1/auth/login`) es solo para `users`
- Los `TenantApiClient` reciben sus tokens fuera de banda (creados por admin)

### 31. `is_active` validado en `SetTenantContext`, no en auth:sanctum *(fix post-6.5)*

El check de `is_active` en `TenantApiClient` vive en `SetTenantContext` (que se aplica al grupo de rutas de negocio), no en el middleware `auth:sanctum`. Esto permite que `logout` funcione para clientes inactivos (revocar token siempre debe ser posible), mientras que todas las operaciones de negocio son bloqueadas.

### 32. Usuario sin `tenant_id` recibe 403 en rutas protegidas *(fix post-6.5)*

Si un `User` tiene `tenant_id = null` y obtiene un token, `SetTenantContext` retorna 403 con mensaje `'No tenant associated with this token.'`. Esto protege contra operaciones sin contexto de tenant en un sistema multi-tenant.

### 28. `tenant_id` agregado a `users` como nullable *(fase 6.5)*

Se agregó `tenant_id` nullable a la tabla `users` para que los agentes del panel puedan pertenecer a un tenant y obtener tokens API con contexto de tenant. Es nullable para no romper usuarios existentes de Breeze.

### 33. Idempotencia implementada en dos niveles en `POST /api/v1/leads` *(fase 6.6)*

Nivel 1 (header): `IdempotencyService::findActiveByKey()` busca en `idempotency_keys` por `(idempotency_key, tenant_id)` con `expires_at > now()`. Si existe y no expiró → replay con `idempotent_replay: true` en data y HTTP 200.

Nivel 2 (datos): query explícita en `leads` por `(tenant_id, source_system, external_reference_id)` antes de crear. Si existe → 409. En el raro caso de race condition, el índice único de MySQL captura la colisión con `UniqueConstraintViolationException`.

Los registros de `idempotency_keys` se guardan DESPUÉS de crear el lead (fuera de la transacción del lead). Si hay race condition en la clave, se captura y se retorna el replay del registro ya guardado.

### 34. `source_system` y `source_channel` — resolución desde contexto o payload *(fase 6.6)*

1. Si el payload incluye `source_system` y el cliente tiene `source_system` fijo (TenantApiClient) → deben coincidir, si no → 422
2. Si el payload no incluye `source_system` → se usa el del cliente (TenantApiClient)
3. Si ni el payload ni el cliente tienen `source_system` → 422 (User sin fuente definida)

Esto permite que ZendVacations envíe requests sin incluir `source_system` cada vez (viene del token), pero no puede crear leads con `source_system` diferente al suyo.

### 35. `LeadResource` con `idempotent_replay` como parámetro del constructor *(fase 6.6)*

Se eligió pasar `$idempotentReplay: bool` al constructor en lugar de usar `additional()` de JsonResource para mantener el flag DENTRO del objeto `data`, consistente con los contratos documentados.

## Siguientes pasos inmediatos (fase 6.7)

1. Implementar `GET /api/v1/leads` con filtros y paginación
2. Implementar `GET /api/v1/leads/{id}` con notas + actividad reciente
3. Implementar `PATCH /api/v1/leads/{id}` (campos editables)
4. Implementar `DELETE /api/v1/leads/{id}` (archivado)
5. Crear seeders con dos tenants, pipeline stages y leads de prueba
6. Rate limiting por tenant
