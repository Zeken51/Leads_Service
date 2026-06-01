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

### 3. Multi-tenancy por `organization_id` (shared database)
Se eligió multi-tenancy por columna (no por schema ni por base de datos separada)
dado que el servidor ya maneja múltiples schemas y escalar a una base por tenant
no está justificado actualmente. Se mitigará el riesgo de fugas con un Global
Scope obligatorio.

### 4. UUIDs como PK en modelos de dominio
Los modelos de negocio (leads, organizations, stages) usarán UUIDs para facilitar
la integración con sistemas externos y evitar enumeración de IDs.

### 5. bootstrap.js faltante — corregido en fase 6.1
El archivo `resources/js/bootstrap.js` no fue incluido en el commit inicial.
Se creó manualmente con la configuración estándar de Axios. Sin este archivo
Vite fallaba al compilar `app.js`.

---

## Riesgos identificados

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Base de datos compartida con otros proyectos | Alto | Schema dedicado `leads_service`, nunca cruzar queries |
| Fuga de datos entre tenants | Alto | Global Scope en todos los modelos + tests de aislamiento |
| Ausencia de tests desde el inicio | Medio | Incluir feature tests desde fase 6.3 |
| Crecimiento del panel Inertia sin estructura | Medio | Definir estructura de Pages/Components por módulo antes de 6.6 |
| Idempotencia no implementada temprano | Bajo-Medio | Prioritizar en 6.7 antes de exposición externa |

---

## Observaciones del stack

- **Vite ^8.0**: versión muy reciente, puede tener cambios de API respecto a documentación de Laravel.
- **Inertia ^2.0**: versión mayor con cambios en la forma de compartir props; verificar compatibilidad con Breeze.
- **Laravel 13**: versión más reciente; la comunidad puede tener menos recursos que Laravel 11/12.
- **PHP 8.3 ZTS**: la variante Thread Safe (ZTS) es inusual en producción; confirmar que el entorno de deploy usa NTS.

---

## Siguientes pasos inmediatos (fase 6.2)

1. Crear estructura de carpetas del dominio
2. Diseñar y ejecutar migración de `organizations`
3. Diseñar y ejecutar migración de `leads`
4. Implementar Global Scope de tenant
5. Crear seeders para datos de prueba locales
