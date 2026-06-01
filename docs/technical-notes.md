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
| ¿El panel interno maneja múltiples tenants o un solo tenant? | Impacta el diseño del panel Inertia en fase 6.7 | Media |

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

## Siguientes pasos inmediatos (fase 6.3)

1. Crear estructura de carpetas del dominio (`Domain/`, `Services/`, `DTOs/`, `Actions/`)
2. Crear trait `HasTenant` y Global Scope de tenant
3. Diseñar y ejecutar migración de `pipeline_stages`
4. Diseñar y ejecutar migración de `leads` con todos los campos definidos en fase 6.2
5. Diseñar y ejecutar migraciones de `lead_notes`, `lead_activity_logs`, `idempotency_keys`
6. Crear modelos con relaciones y scoping
7. Crear seeders con dos tenants y datos de prueba
