# Brief: Microservicio leads-service

## Descripción general

`leads-service` es un microservicio independiente para la gestión de leads y
seguimiento comercial. Opera como unidad autónoma: tiene su propia base de datos,
su propia autenticación y no depende de otros repositorios del ecosistema.

## Propósito

Centralizar todo el ciclo de vida de un lead comercial:

- Captura y registro de leads
- Avance a través de un pipeline comercial configurable
- Seguimiento y asignación a agentes
- Registro de notas e interacciones
- Log de actividad auditable
- Soporte multi-tenant para múltiples organizaciones

## Usuarios del sistema

| Actor | Rol |
|---|---|
| Agente comercial | Gestiona sus propios leads y pipeline |
| Supervisor | Visualiza y reasigna leads del equipo |
| Admin de tenant | Configura pipeline, etapas y usuarios |
| Sistema externo | Crea leads vía API con JWT |

## Alcance del microservicio

**Incluye:**
- CRUD de leads
- Pipeline con etapas configurables
- Notas y comentarios por lead
- Activity log automático
- Autenticación JWT para API
- Panel interno con Inertia/Vue
- Multi-tenant mediante scoping por organización

**Excluye:**
- Facturación o pagos
- Integración directa con CRM externo (se expone API para eso)
- Notificaciones push (puede delegarse a otro servicio)

## Restricciones técnicas conocidas

- Base de datos compartida físicamente con otros schemas (MySQL multi-schema)
- Puerto de base de datos no estándar: 3308
- Sin acceso a otros repositorios del ecosistema

## Decisiones de diseño preliminares

- **Arquitectura**: Dominio en `app/Domain`, servicios en `app/Services`, DTOs en `app/DTOs`
- **Auth doble**: Breeze/Inertia para el panel interno, Sanctum/JWT para la API
- **Idempotencia**: Clave idempotente en headers para operaciones de escritura vía API
- **Multi-tenant**: Scoping por `organization_id` en todos los modelos principales
