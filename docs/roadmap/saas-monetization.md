# LikePlatform — Roadmap priorizado hacia SaaS comercializable

Fecha: 2026-07-17
Estado actual: Base técnica completa (auth + 2FA + passkeys + multi-tenancy + 2 spokes funcionales con tests). Falta todo lo que convierte al sistema en un SaaS con flujo de ingresos autoservicio.

## Decisiones de diseño ya tomadas

| Decisión | Elección | Implicación |
|---|---|---|
| Objetivo | SaaS comercializable | Todo gira alrededor de poder cobrar y atender clientes pagos |
| Pasarela | Laravel Cashier (Stripe) | `composer require laravel/cashier`; aplica `Billable` al modelo `Tenant` |
| Moneda | CLP | `cashier.currency = clp`; precios en pesos (no centavos) para evitar decimales |
| Impuestos | IVA 19% desglosado | Se muestra neto + IVA; se configura `tax_rates` en Stripe con `inclusive=false` para que la factura SaaS sea recuperable por empresas |
| Trial | 14 días, Free spokes, sin tarjeta | Trial sin `stripe_subscription`; al expirar, el tenant sigue con Free indefinidamente o upgradea con tarjeta |
| Modelo de cobro | Una suscripción Stripe por tenant con N items (uno por spoke suscrito) | Una sola factura consolidada por tenant; `tenant_spoke_subscriptions` actúa como espejo local |
| Panel platform admin | Completo (impersonar, métricas, gestión manual) | Rutas bajo `/admin` con guard dedicado |

## Convenciones del backlog

- Cada tarea es lo más pequeña posible para ser retomada en frío
- Cada tarea incluye archivos afectados y criterios de aceptación
- El orden de fases es **estricto**: no iniciar fase N+1 hasta cerrar fase N (porque hay dependencias técnicas)
- Las fases 0–1 son la ruta crítica para facturar; las demás se pueden paralelizar después

---

## Fase 0 — Fundamentos de monetización (prerrequisito técnico) ✅

> Sin esta fase nada más funciona. Es la base sobre la que se apoya el cobro.
>
> **Cerrada en commit `8320111` (`develop`)** — 13 tests nuevos, 101/101 pasando, PHPStan y Pint limpios.

### T0.1 Instalar y configurar Laravel Cashier ✅
- **Acción**: `composer require laravel/cashier`; publicar config; agregar `CASHIER_CURRENCY=clp`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` a `.env.example`
- **Archivos**: `composer.json`, `.env.example`, `config/cashier.php` (publicado), `config/services.php`
- **Aceptación**: `php artisan cashier:install` corre sin errores; variables documentadas en README
- **Implementado**: Cashier v16.6 instalado, `config/cashier.php` con `currency=clp` y `currency_locale=es_CL`, vars en `.env.example` y README con sección dedicada.

### T0.2 Hacer que `Tenant` sea el modelo facturable ✅
- **Acción**: aplicar `Laravel\Cashier\Billable` trait a `Tenant`; crear migración para agregar `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` a la tabla `tenants` (Cashier los gestiona)
- **Archivos**: `app/Models/Tenant.php`, nueva migración `add_cashier_columns_to_tenants_table`
- **Aceptación**: Un tenant puede tener `->createAsStripeCustomer()` y `->updateDefaultPaymentMethod()`
- **Implementado**: Migrations reescritas para apuntar a `tenants`/`subscriptions.tenant_id` (la default de Cashier usa `users`/`user_id`, incompatible con nuestro modelo billable). `Cashier::useCustomerModel(Tenant::class)` registrado en `AppServiceProvider`. Cast `trial_ends_at` agregado.

### T0.3 Vincular `SpokePlan` con precios de Stripe ✅
- **Acción**: agregar columna `stripe_product_id` y `stripe_price_id` (nullable) a `spoke_plans`; crear `app/Core/Stripe/SpokePlanSyncer.php` con método `sync(StripePriceService $stripe)` que crea/actualiza producto+precio en Stripe a partir de un `SpokePlan`
- **Archivos**: nueva migración `add_stripe_ids_to_spoke_plans_table`, `app/Core/Stripe/SpokePlanSyncer.php`, `app/Console/Commands/SyncStripePricesCommand.php`
- **Aceptación**: `php artisan stripe:sync-prices` crea precios en Stripe test; cada `SpokePlan` queda con su `stripe_price_id` poblado
- **Implementado**: `SpokePlanSyncer` con `sync()` (reusa Product existente, archiva Price y crea uno nuevo si cambia el monto) y `syncAll()`. Comando `stripe:sync-prices` con opción `--plan=*`. **Protección contra race conditions** con `Cache::lock("spoke-plan-sync:{id}", 30)->block(10)` por plan. Excluye planes Free (price_cents <= 0).

### T0.4 Configurar webhook endpoint de Stripe ✅
- **Acción**: crear `app/Http/Controllers/Webhooks/StripeWebhookController.php`; registrar ruta `POST /stripe/webhook` (excluida de CSRF) en `routes/web.php`; configurar verificación de firma con `Cashier::webhook()`
- **Archivos**: `app/Http/Controllers/Webhooks/StripeWebhookController.php`, `routes/web.php`, `bootstrap/app.php` (excluir CSRF en la ruta)
- **Aceptación**: `stripe listen --forward-to localhost/stripe/webhook` recibe eventos; log muestra el payload
- **Implementado**: `StripeWebhookController` extiende el de Cashier (verificación de firma vía `STRIPE_WEBHOOK_SECRET` automática). Ruta `POST /stripe/webhook` (nombre `cashier.webhook`). **CSRF excluido solo en el path concreto `stripe/webhook`** (no el glob `stripe/*` — hallazgo del code review, evita exponer futuros POST). `missingMethod` loguea eventos sin handler dedicado (T1.3 agregará `handleCustomerSubscriptionCreated`, etc.).

---

## Fase 1 — Suscripciones self-service (ruta crítica de ingresos)

> Aquí el tenant puede pagar. Sin esto no hay ingresos.

### T1.1 Página de pricing dentro del tenant
- **Acción**: Livewire Volt component `resources/views/billing/⚡pricing.blade.php` que lista todos los `Spoke` activos con sus `SpokePlan`. Marca visualmente qué spokes ya están suscritos y en qué plan. Botón "Actualizar" / "Suscribirse" por plan Pro.
- **Archivos**: `resources/views/billing/⚡pricing.blade.php`, ruta en `routes/billing.php` (prefijo `/app/billing`, middleware `auth, verified, tenant.context`)
- **Aceptación**: Tenant con spoke Free ve "Actualizar a Pro"; tenant sin spoke ve "Suscribirse"; ambos CTAs llevan al checkout

### T1.2 Checkout de Stripe por spoke
- **Acción**: Livewire action que llama `$tenant->newSubscription($spoke->slug, $priceId)->trialDays(0)->checkout([...])`; redirige a Stripe Checkout. Al volver (success URL `/app/billing?checkout=success`), un job/acción actualiza `tenant_spoke_subscriptions` con `is_active = true` y `subscribed_at = now()`.
- **Archivos**: `app/Livewire/Billing/CheckoutButton.php` o acción Volt, `app/Jobs/ActivateSpokeSubscription.php`
- **Aceptación**: Click en "Suscribirse a Docs Pro" → Stripe Checkout → al volver el spoke está activo; fila nueva en `tenant_spoke_subscriptions`

### T1.3 Webhook handler reactivo
- **Acción**: en `StripeWebhookController`, manejar eventos:
  - `customer.subscription.created` / `updated`: sincronizar `tenant_spoke_subscriptions` (crear/actualizar `is_active`, `expires_at` desde `current_period_end`)
  - `customer.subscription.deleted`: marcar `is_active = false`
  - `invoice.payment_succeeded`: log + email
  - `invoice.payment_failed`: marcar `is_active = false` + email al owner
- **Acción**: idempotencia por `event->id` (tabla `webhook_calls` o uso de `Cashier::handleSubscriptionUpdated()` que ya es idempotente)
- **Archivos**: `app/Http/Controllers/Webhooks/StripeWebhookController.php`, `app/Mail/SubscriptionPaymentFailed.php`
- **Aceptación**: Cancelar suscripción en Stripe dashboard → webhook recibido → fila local marcada `is_active = false`; idempotencia probada con replay

### T1.4 UI para cancelar / reanudar suscripción
- **Acción**: en `resources/views/billing/⚡pricing.blade.php`, botón "Cancelar suscripción" por spoke que llama a `$tenant->subscription($spoke->slug)->cancel()`; botón "Reanudar" si está en grace period
- **Archivos**: el mismo componente de T1.1
- **Aceptación**: Cancelar → el spoke sigue activo hasta fin de periodo; UI muestra fecha de expiración; "Reanudar" lo reactiva

### T1.5 Manejo de trial de 14 días (sin tarjeta)
- **Acción**: el trial de Free spokes NO usa Stripe. Es simplemente un flag `trial_ends_at` en el tenant (ya agregado en T0.2). Crear comando `tenants:expire-trials` que pone `is_active = false` en `tenant_spoke_subscriptions` donde el trial venció. Programar en `routes/console.php` para correr diario.
- **Archivos**: `app/Console/Commands/ExpireTrialsCommand.php`, `routes/console.php`
- **Aceptación**: Crear tenant con trial que vence mañana → correr comando → spokes se desactivan; sin trial → comando es no-op

### T1.6 Billing Portal de Stripe
- **Acción**: ruta `GET /app/billing/portal` que llama a `$tenant->redirectToBillingPortal()`; botón "Actualizar método de pago" en la vista de billing
- **Archivos**: `app/Http/Controllers/Billing/PortalController.php`, `routes/billing.php`
- **Aceptación**: Click → redirige a portal Stripe → al volver mantiene sesión en la app

---

## Fase 2 — Onboarding de nuevos clientes

> Reduce la fricción desde el registro hasta el primer uso.

### T2.1 Wizard post-registro
- **Acción**: tras `register` exitoso, redirigir a `/onboarding` si el usuario no tiene tenant. Wizard Livewire Volt de 3 pasos: (1) nombre del tenant, (2) elegir spokes iniciales, (3) opcional invitar compañeros. Al terminar, crea tenant + owner role + suscripciones a spokes Free + `trial_ends_at = now() + 14 days`.
- **Archivos**: `resources/views/onboarding/⚡create.blade.php`, `app/Actions/Onboarding/CreateTenantAction.php`
- **Aceptación**: Registrarse → wizard → entrar al dashboard con spokes Free activos y trial de 14 días

### T2.2 Banner de trial en la UI
- **Acción**: componente Flux `flux:toast` o banner persistente en el layout que muestra "Te quedan X días de trial" si el tenant tiene `trial_ends_at` futuro; CTA "Actualizar plan" en el banner
- **Archivos**: `resources/views/components/billing/⚡trial-banner.blade.php`, incluir en `packages/core-ui/resources/views/components/app-shell.blade.php`
- **Aceptación**: Tenant con trial activo ve el banner; sin trial no lo ve; el contador se actualiza diariamente

### T2.3 Emails transaccionales del trial
- **Acción**: `app/Mail/TrialExpiringMail.php` enviado cuando faltan 3 días; configurar en `app/Console/Commands/NotifyExpiringTrialsCommand.php` con schedule diario
- **Archivos**: Mail + vista Markdown + comando + schedule
- **Aceptación**: Tenant con trial que vence en 3 días recibe email (verificable con `Mail::fake()` en test y con `log` driver en dev)

---

## Fase 3 — Gestión de miembros del tenant

> Convierte al tenant de "yo solo" a "mi equipo".

### T3.1 Listado de miembros
- **Acción**: Livewire Volt `resources/views/settings/team/⚡index.blade.php`; tabla con nombre, email, rol, "Unido el"; solo visible para usuarios con `tenant.manage_members`
- **Archivos**: nuevo componente + ruta `/settings/team`
- **Aceptación**: Owner ve la lista; member sin permiso recibe 403

### T3.2 Invitar miembro
- **Acción**: form con email + rol; crea `Invitation` (nuevo modelo) con token firmado; envía email con link `/invitations/{token}`; al aceptar, crea la fila `tenant_user` y asigna el rol
- **Archivos**: `app/Models/Invitation.php` + migración, `app/Mail/TeamInvitationMail.php`, `app/Livewire/Team/InviteMember.php`, `app/Livewire/Team/AcceptInvitation.php`
- **Aceptación**: Owner invita `x@y.com` como `admin` → email enviado → al hacer click se crea la membresía con rol admin; token expira en 7 días

### T3.3 Cambiar / remover rol
- **Acción**: dropdown de rol en la tabla (T3.1); botón "Remover" con confirmación
- **Aceptación**: Cambiar rol se refleja inmediatamente; owner no puede removerse a sí mismo

---

## Fase 4 — Tenant switcher

> Usuarios con múltiples tenants pueden alternar entre ellos.

### T4.1 UI de cambio de tenant
- **Acción**: dropdown en el sidebar (dentro de `packages/core-ui`) que lista los tenants del usuario y permite cambiar el activo. Al cambiar, actualiza `current_tenant_id` en sesión y refresca permisos Spatie
- **Archivos**: `resources/views/livewire/⚡tenant-switcher.blade.php`, `app/Livewire/Team/SwitchTenant.php`, integrar en `app-shell`
- **Aceptación**: Usuario con 2 tenants cambia de uno a otro desde el sidebar → la app refleja datos del tenant elegido; permisos se recargan

---

## Fase 5 — Configuración del tenant y dashboard de billing

### T5.1 Vista "Configuración" del tenant
- **Acción**: `/app/settings` con tabs: General (nombre, slug), Miembros (link a T3.1), Facturación (link a billing)
- **Archivos**: `resources/views/tenant-settings/⚡general.blade.php`
- **Aceptación**: Owner puede editar nombre del tenant; cambio se refleja en sidebar

### T5.2 Dashboard de billing
- **Acción**: `/app/billing` con resumen: spokes activos con plan, próxima fecha de cobro, método de pago (últimos 4), botones a Stripe Portal. Tabla con historial de invoices de Stripe
- **Archivos**: `resources/views/billing/⚡index.blade.php`, `app/Livewire/Billing/InvoicesList.php`
- **Aceptación**: Owner ve su próxima fecha de cobro; member sin permiso `tenant.view_billing` recibe 403; lista de invoices con links a PDF

---

## Fase 6 — Panel de platform admin

> Para operar el SaaS sin tocar la BD.

### T6.1 Layout y guard `/admin`
- **Acción**: layout separado `resources/views/layouts/admin.blade.php`; rutas bajo prefijo `/admin` con middleware `auth, platform.admin` (nuevo middleware que verifica `is_platform_admin`)
- **Archivos**: nuevo layout, `app/Http/Middleware/EnsurePlatformAdmin.php`, `routes/admin.php`
- **Aceptación**: Usuario no-admin que intenta `/admin` recibe 403; admin ve dashboard

### T6.2 Listado de tenants
- **Acción**: `/admin/tenants` con tabla: nombre, owner, plan activo, estado (`is_active`, `is_suspended`), MRR; filtros por estado
- **Archivos**: `resources/views/admin/tenants/⚡index.blade.php`
- **Aceptación**: Admin ve todos los tenants; búsqueda por nombre funciona

### T6.3 Suspender / reactivar tenant
- **Acción**: acciones masivas en la tabla; suspender setea `is_suspended = true` y el middleware `EnsureTenantContext` ya los bloquea; opcionalmente cancelar subscripciones en Stripe
- **Aceptación**: Suspender → usuarios del tenant son redirigidos con mensaje al login; reactivar restaura acceso

### T6.4 Conceder / revocar spokes (override manual)
- **Acción**: vista detalle del tenant con sus spokes; botones para añadir plan Free/Pro manualmente (crea suscripción en Stripe si es Pro) o revocar (cancela en Stripe + limpia local)
- **Aceptación**: Admin puede dar Pro gratis a un tenant de soporte; revocación se refleja vía webhook

### T6.5 Impersonar tenant
- **Acción**: botón "Impersonar" que loguea al admin como el owner del tenant objetivo, con un banner persistente "Estás impersonando a X. [Salir]"; al salir restaura la sesión admin
- **Archivos**: `app/Http/Controllers/Admin/ImpersonationController.php`, `app/Livewire/Admin/ImpersonationBanner.php`
- **Aceptación**: Admin entra a `/admin/tenants/123/impersonate` → es redirigido al dashboard del tenant objetivo con el banner; click "Salir" lo devuelve a `/admin`

### T6.6 Métricas básicas del SaaS
- **Acción**: `/admin` dashboard con MRR (suma de subscripciones activas × price), tenants activos, nuevos en los últimos 30 días, churn del mes
- **Archivos**: `app/Livewire/Admin/Dashboard.php`, queries Eloquent agregadas
- **Aceptación**: Números cuadran con consulta SQL directa; cache de 1h para no pegar a Stripe en cada carga

---

## Fase 7 — Pulido de UX

### T7.1 Apariencia (dark mode)
- **Acción**: implementar el componente stub `app/Livewire/Settings/Appearance.php`; toggle dark/light/system; persistir en `users` (columna nueva) o `session`; integrar con `like-style` skill
- **Aceptación**: Toggle cambia tema sin recargar; preferencia persiste entre sesiones

### T7.2 Dashboard dinámico
- **Acción**: convertir `dashboard.blade.php` en Livewire Volt con widgets: conteo de documentos del tenant, conteo de invoices por estado, próximos vencimientos, actividad reciente
- **Aceptación**: Cada widget refleja datos reales del tenant activo

### T7.3 Landing pública
- **Acción**: `resources/views/welcome.blade.php` mejorada con propuesta de valor, lista de spokes, pricing público, CTAs a registro/login
- **Aceptación**: Visitante anónimo ve la propuesta; click "Empezar" → registro

### T7.4 Dunning básico
- **Acción**: emails escalonados tras `invoice.payment_failed` (3, 7, 14 días); tras 14 días sin pago, las suscripciones Pro se cancelan (Cashier ya lo hace por `cancel_after_grace_period_days`)
- **Aceptación**: Simular fallo de pago con tarjeta de test 4000000000000002 → recibe emails según schedule; tras grace period el spoke Pro se desactiva

---

## Fase 8 — Calidad y operaciones

### T8.1 CI con GitHub Actions
- **Acción**: workflow que corre Pint, PHPStan/Larastan, Pest en cada PR; matrix PHP 8.4 / 8.5
- **Aceptación**: PR sin tests que pasen no se puede mergear

### T8.2 Tests E2E con Pest browser
- **Acción**: cubrir flujos críticos: registro → onboarding → crear documento → invitar miembro → suscribirse a Pro
- **Aceptación**: Suite E2E corre en CI en <5 min; flaky test = bloqueante

### T8.3 Estrategia de deploy
- **Acción**: documentar y configurar deploy (Laravel Cloud recomendado por la fundación Laravel); variables de producción; `APP_ENV=production`; Horizon si hay colas
- **Aceptación**: Deploy a staging documentado paso a paso

### T8.4 Observabilidad
- **Acción**: Sentry para errores; log estructurado de webhooks (entrante + acción tomada); alerta si webhook falla 3 veces seguidas
- **Aceptación**: Un error 500 en producción aparece en Sentry con contexto

---

## Cómo retomar este plan

1. Elegir la siguiente tarea **T0.1** desde la rama actual
2. Crear rama `feature/cashier-setup` (convención a definir)
3. Implementar siguiendo los archivos listados
4. Correr `./vendor/bin/pint --dirty` y `php artisan test --compact --filter=...` antes de commit
5. PR con descripción enlazando al ID de tarea (T0.1, T0.2, ...)
6. Al cerrar la tarea, tacharla de este documento y abrir la siguiente

## Riesgos y supuestos

- **Stripe en Chile**: Stripe opera en Chile pero la cuenta debe estar en nombre de LikeInnovación. Sin cuenta activa, los tests usan modo `test` de Stripe que no requiere cuenta real pero no procesa cobros.
- **IVA 19% en Stripe**: configurar `tax_rates` en Stripe con `country=CL`, `percentage=19`, `inclusive=false`, `jurisdiction=CL-VS` (validación SII). Si esto cambia, ajustar `StripePlanSyncer`.
- **Trial sin tarjeta**: Stripe no permite trials sin tarjeta en suscripciones pagas. Por eso el trial es solo para spokes Free y se modela con un flag local, no con Stripe.
- **Cashier y multi-tenant**: aplicar `Billable` a `Tenant` en vez de `User` no es el caso de uso más común de Cashier; revisar documentación de Cashier para casos no estándar antes de implementar.
- **Webhooks en local**: usar `stripe-cli` (`stripe listen --forward-to ...`) durante desarrollo; en prod configurar endpoint público con `STRIPE_WEBHOOK_SECRET`.

## Fuera de alcance (por ahora)

- App móvil / API pública
- Integraciones con SII (facturación electrónica chilena)
- Múltiples idiomas (i18n)
- Mercado público de spokes por terceros
- Migración a Postgres (se mantiene SQLite para dev, MySQL/Postgres para prod)
- Webhooks de salida / eventos para integraciones de terceros
