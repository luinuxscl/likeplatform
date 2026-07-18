# LikePlatform

**Base tecnológica interna de [Like Innovación](https://soluciones.like.cl).**

> Construimos sobre LikePlatform, nuestra base tecnológica probada, para entregar más rápido sin sacrificar orden ni calidad.

LikePlatform es la plataforma que respalda cada solución que construimos. No es un producto que se venda por separado, sino una capacidad que acelera el desarrollo con estructura, seguridad y criterio desde el día uno.

---

## Arquitectura: Hub & Spoke

La plataforma sigue un modelo **Hub & Spoke** que separa lo común de lo específico:

- **Hub** — Autenticación, multi-tenancy, roles, permisos y navegación. La base que todo proyecto necesita y que no debería reinventarse.
- **Spokes** — Módulos enchufables que cada tenant contrata según lo que requiere. Cada Spoke es un conjunto de funcionalidades con su propio plan (Free, Pro) y rutas aisladas.

```
Hub (auth · tenants · roles · app switcher)
 │
 ├── Docs      →  Documentación con markdown
 ├── Invoicing →  Facturación y gestión de cobros
 └── ...        (nuevos Spokes según necesidad)
```

Cada Spoke se protege con middleware de contexto (`EnsureTenantContext`, `EnsureSpokeAccess`) y se despliega dinámicamente en el App Switcher del tenant.

---

## Stack

| Capa | Tecnología |
|---|---|
| **Backend** | PHP 8.3 · Laravel 13 · Fortify |
| **Frontend** | Livewire 4 · Flux UI 2 · Tailwind CSS 4 |
| **Build** | Vite 8 · laravel-vite-plugin |
| **Auth** | Passkeys · 2FA · email verification |
| **Permisos** | Spatie Laravel Permission (scoped por tenant) |
| **Billing** | Laravel Cashier (Stripe) · CLP · IVA 19% |
| **Base de datos** | SQLite (dev) · compatible con MySQL/PostgreSQL |
| **Testing** | Pest 4 · Larastan 3 · Pint |
| **Tooling** | Laravel Boost · Pail · Chisel · Blaze |

---

## Modelos principales

| Modelo | Rol |
|---|---|
| `User` | Autenticación con passkeys y 2FA, roles por tenant. `is_platform_admin` para superadmin. |
| `Tenant` | Unidad organizacional multi-tenant. Agrupa usuarios, documentos, facturas y Spokes activos. |
| `Spoke` | Módulo funcional del ecosistema. Define el icono, ruta y slug del módulo. |
| `SpokePlan` | Plan de suscripción dentro de un Spoke (Free, Pro). Define precio y features. |
| `Document` | Documento en markdown scoped por tenant (Spoke Docs). |
| `Invoice` | Factura con items, estados y cálculo automático (Spoke Invoicing). |

```
User ──BelongsToMany── Tenant ──HasMany── Document
                  │          │
                  │       HasMany── Invoice
                  │
           BelongsToMany── Spoke ──HasMany── SpokePlan
```

---

## Requisitos

- PHP 8.3+
- Composer 2
- Node.js 20+
- SQLite (viene preconfigurado para desarrollo local)
- (Opcional) [Stripe CLI](https://docs.stripe.com/stripe-cli) — para escuchar webhooks en local
- (Opcional) `ext-bcmath` — requerida por Laravel Cashier para cálculos monetarios

---

## Instalación

```bash
git clone <repo-url> likeplatform
cd likeplatform
composer run setup
```

El script `setup` ejecuta:

1. `composer install`
2. Copia `.env.example` → `.env`
3. Genera la app key
4. Corre migraciones
5. Siembra la base con datos de demo
6. `npm install && npm run build`

---

## Inicio rápido

```bash
composer run dev
```

Esto levanta el servidor de desarrollo de Laravel y Vite en paralelo.

### Datos de demo

Después del seed, podés iniciar sesión con:

| Email | Rol | Contraseña |
|---|---|---|
| `root@demo.com` | Platform Admin | `password` |
| `admin@demo.com` | Tenant Owner (ACME) | `password` |
| `user@demo.com` | Member (ACME) | `password` |

---

## Estructura del proyecto

```
likeplatform/
├── app/
│   ├── Actions/Fortify/        # Acciones de autenticación
│   ├── Console/Commands/       # Comandos Artisan personalizados
│   ├── Core/Spokes/            # Registro y resolución de Spokes
│   ├── Http/Middleware/        # EnsureTenantContext, EnsureSpokeAccess
│   ├── Livewire/               # Componentes de auth y settings
│   ├── Models/                 # User, Tenant, Spoke, SpokePlan, Document, Invoice
│   └── Providers/              # App, Fortify
├── config/                     # Configuración de Laravel + Fortify + Permission
├── database/
│   ├── factories/              # 6 factories
│   ├── migrations/             # 14 migraciones
│   └── seeders/                # DatabaseSeeder, DemoUser, Role, Spoke, Tenant
├── docs/brand/                 # Documentación de marca
├── packages/core-ui/           # Paquete local del shell UI (AppSwitcher, layouts)
├── resources/views/
│   ├── components/             # Componentes Livewire Volt de cada Spoke
│   ├── flux/                   # Overrides de Flux UI
│   ├── layouts/                # app.blade.php, auth.blade.php
│   └── livewire/               # Vistas de auth y settings
├── routes/
│   ├── web.php                 # Rutas principales
│   ├── settings.php            # Perfil, seguridad, apariencia
│   ├── docs.php                # Rutas del Spoke Docs
│   └── invoicing.php           # Rutas del Spoke Invoicing
└── tests/                      # Pest (18 feature, 1 unit)
```

---

## Comandos disponibles

| Comando | Descripción |
|---|---|
| `composer run dev` | Levanta servidor Laravel + Vite |
| `composer run setup` | Instalación completa del proyecto |
| `composer test` | Corre linter + análisis estático + tests |
| `composer lint` | Formatea código con Pint |
| `composer lint:check` | Verifica estilo sin modificar |
| `composer types:check` | Análisis estático con Larastan |
| `php artisan test` | Ejecuta la suite de tests |
| `php artisan spokes:list` | Lista los Spokes disponibles |
| `php artisan tenants:grant-spoke {tenant} {spoke}` | Otorga un Spoke a un tenant |

---

## Tests

El proyecto usa **Pest 4** con 19 tests entre feature y unit. Los tests cubren:

- Autenticación (registro, login, 2FA, reset de contraseña, verificación de email)
- CRUD del Spoke Docs con aislamiento por tenant
- CRUD del Spoke Invoicing con cambios de estado
- Middleware de tenancy
- Registro de Spokes
- Settings y validación del seeder demo

```bash
php artisan test --compact
```

---

## Agregar un nuevo Spoke

1. Creá la migración para las tablas necesarias
2. Registrá el Spoke en el seeder `SpokeSeeder`
3. Creá los componentes Livewire Volt en `resources/views/components/{spoke}/`
4. Definí las rutas en `routes/{spoke}.php`
5. Agregá los permisos en `RoleSeeder`
6. Escribí los tests

---

## Variables de entorno (Stripe / Cashier)

Para habilitar el flujo de cobros, definí estas variables en `.env` (modo `test` de Stripe):

```ini
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx          # lo entrega `stripe listen` en dev
CASHIER_CURRENCY=clp
CASHIER_CURRENCY_LOCALE=es_CL
CASHIER_PATH=stripe
```

Para escuchar webhooks en local:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

---

## Guía de marca y editorial

La documentación de marca está en `docs/brand/`:

- `brand-book.md` — identidad, voz, posicionamiento y atributos
- `guia-editorial.md` — tono por canal, idioma, titulares y reglas de redacción

---

**Like Innovación**
**Tu visión, nuestras soluciones**
