# Architecture

A short guide for discussing the design. The code is intentionally small; the goal is that
DDD / Hexagonal / CQRS are visible in the **model and the boundaries**, not just in folder
names.

## 1. Layers and why each responsibility lives where it does

```
Presentation  ->  Application  ->  Domain
     \                /  \
      \              /    \
   Infrastructure --´      (Domain depends on nothing but PHP)
```

### Domain (`src/Payment/Domain`)

Business concepts and invariants, **pure PHP**:

- `Payment` aggregate — owns its lifecycle. State changes only through intent methods
  (`markProcessing`, `markSucceeded`, `markFailed`); there is no public status setter, and
  illegal transitions throw `InvalidPaymentTransition`. `markSucceeded` is idempotent so a
  repeated completion cannot raise.
- Value objects — `Money` (integer minor units; decimal input parsed with string math, EUR
  only), `PaymentProvider` (an **open** identifier, `/^[a-z0-9_]+$/`, not an enum),
  `PayerEmail`, `PaymentId` (UUID v4 generated in pure PHP).
- `PaymentStatus` — exactly the four states the two flows need: `Pending`, `Processing`,
  `Succeeded`, `Failed`.
- `PaymentRepository` — the aggregate's persistence contract.

Why here: these rules are true regardless of HTTP, Doctrine or which provider is used.
The layer-boundary test (`tests/Architecture`) fails the build if a framework or SDK
namespace appears here.

### Application (`src/Payment/Application`)

Use cases and the **outbound contracts** they need:

- One handler per command/query (`InitiatePaymentHandler`, `CompletePaymentHandler`,
  `ChangeDefaultProviderHandler`, `GetPaymentStatusHandler`, `GetProviderSettingsHandler`).
- Ports: `PaymentGatewayInterface`, `PaymentGatewayRegistry`, `ProviderSettingsPort`.
- Provider-neutral gateway DTOs (`StartCheckoutInput`, `StartedCheckout`,
  `CheckoutInstructions`, `CompleteCheckoutInput`, `CheckoutOutcome`).
- `PaymentGatewayResolver`.

Why here: a use case ("initiate a payment") is an application concern, not a domain rule.
It coordinates domain objects and ports but knows nothing about Stripe, Doctrine or Twig.

### Infrastructure (`src/Payment/Infrastructure`)

The adapters that make the ports real: `DoctrinePaymentRepository` and the external XML
mapping, `DoctrineProviderSettingsRepository`, `StripePaymentGateway`,
`PayPalPaymentGateway` + `PayPalApiClient`, `TaggedGatewayRegistry`, and the Messenger
bus adapters. This is the only layer allowed to import an SDK or Doctrine.

### Presentation (`src/Payment/Presentation`)

Symfony controllers and forms. Controllers **validate and map** HTTP input, dispatch a
command or query, and render a response. They contain no business rules — e.g. the
`amount` string is validated for shape by the form but *parsed* by `Money` in the domain,
so there is one source of truth for what a valid amount is.

### Wiring (`config/`)

All wiring lives outside the inner layers:

- `config/services.yaml` binds each port to its adapter, injects environment values
  (`STRIPE_SECRET_KEY`, `PAYMENT_DEFAULT_PROVIDER`, …), tags gateway adapters, and
  registers handlers on their bus.
- Doctrine mapping is external XML in `config/doctrine/` — the domain classes carry no ORM
  attributes.

## 2. How commands and queries are separated

- **Commands** mutate state. They may return a *minimal* use-case result — a payment id
  plus checkout instructions (`InitiatePaymentResult`), or the resulting status
  (`CompletePaymentResult`). This is a deliberate convention: it keeps the controller from
  having to immediately re-query for the one or two values it needs to render the next step.
- **Queries** never mutate state and return read DTOs (`PaymentStatusView`,
  `ProviderSettingsView`).
- Two application-owned interfaces, `CommandBus::dispatch()` and `QueryBus::ask()`, are
  implemented by thin adapters over two synchronous Symfony Messenger buses
  (`command.bus`, `query.bus`). Handlers are plain objects with a single typed
  `__invoke()`; they are attached to a bus **by configuration** (`messenger.message_handler`
  tag in `services.yaml`), not by a `#[AsMessageHandler]` attribute, so the Application
  layer stays framework-free.
- One SQLite database. CQRS here is about **separating the models and the intent**, not
  about separate stores or async processing. Reads currently reuse the aggregate
  repository and map to view DTOs; with only two flows a dedicated read store would be
  dead weight.

## 3. Why the provider integration contract belongs in Application

`PaymentGatewayInterface` is defined in `Application`, not `Infrastructure`, because it is
an **outbound port** — it expresses what a use case needs from "a payment provider" in the
application's own vocabulary:

```php
public function startCheckout(StartCheckoutInput $input): StartedCheckout;
public function completeCheckout(CompleteCheckoutInput $input): CheckoutOutcome;
```

Stripe and PayPal have genuinely different checkout lifecycles, so the contract has two
verbs — *start* and *complete/verify* — rather than a misleading synchronous
`pay(): bool`. All types crossing this boundary are small, typed, provider-neutral DTOs.
Adapters translate to and from the provider APIs and never return SDK objects.

`StartedCheckout` carries a `CheckoutInstructions { kind, parameters }` bag. `kind` is an
open string (`"stripe_embedded"`, `"paypal_buttons"`); the browser switches on it.
`parameters` contains only public values (publishable key, client secret, order id) — never
a server secret.

### Checkout lifecycle in practice

| | Stripe | PayPal |
|---|---|---|
| start | create Checkout Session (`ui_mode=embedded`, EUR, minor units) → `client_secret` | create Order (`intent=CAPTURE`, EUR) → order id |
| browser | mounts Stripe's hosted iframe; redirects to our `return_url` | renders PayPal buttons; buyer approves |
| complete | retrieve Session server-side; require `status=complete` **and** `payment_status=paid` | capture order server-side; require `status=COMPLETED` |
| reference stored | payment intent id | capture id |

Success is **only** ever established from the server-side retrieve/capture response, never
from a browser callback parameter. Raw card data never passes through PHP.

## 4. How `PaymentGatewayResolver` works

```php
final readonly class PaymentGatewayResolver
{
    public function __construct(
        private PaymentGatewayRegistry $registry,   // application-owned contract
        private ProviderSettingsPort $settings,     // effective default-provider setting
    ) {}

    public function forNewPayment(): PaymentGatewayInterface
    {
        return $this->registry->get($this->settings->effectiveProvider());
    }

    public function forPayment(Payment $payment): PaymentGatewayInterface
    {
        return $this->registry->get($payment->provider());
    }
}
```

- **New payment** → the *effective* provider: the value saved via `/admin/payment-provider`
  if one exists, otherwise `PAYMENT_DEFAULT_PROVIDER` (injected into the settings adapter;
  no layer reads the environment directly).
- **Existing payment** → the provider identifier stored *on that payment*.
- `registry->get()` throws `UnknownPaymentProvider` — a clear application exception — when
  no adapter is registered for the identifier.

The registry (`TaggedGatewayRegistry`) is built from Symfony **tagged services**
(`app.payment_gateway`), indexed by `$gateway->provider()->value`. There is no central
`switch`, and handlers never receive a concrete gateway — only the resolver.

## 5. Why provider selection is stored on each payment

The default provider can change at any time from the admin page. A payment's checkout
lifecycle spans multiple requests (initiate → provider interaction → complete). If
completion resolved the gateway from the *current* default, a payment started on Stripe
could be "completed" against PayPal after an admin change — verifying the wrong thing.

Storing `PaymentProvider` on the `Payment` aggregate at initiation makes the choice an
invariant of that payment: every subsequent operation (`completeCheckout`, status queries)
uses `$payment->provider()`. The admin setting only ever influences *new* payments. This
is covered by `CompletePaymentHandlerTest::testCompletesUsingThePaymentsOriginalProvider…`.

## 6. How to add a third provider

Say, Adyen:

1. **Adapter** — `src/Payment/Infrastructure/Gateway/Adyen/AdyenPaymentGateway.php`
   implementing `PaymentGatewayInterface`, with `provider()` returning
   `PaymentProvider::fromString('adyen')`. Add a thin API client next to it if needed.
2. **Configuration** — add its constructor arguments (API key, etc.) in `services.yaml`.
   The `_instanceof` rule already tags every `PaymentGatewayInterface` with
   `app.payment_gateway`, so the registry picks it up automatically.
3. **Checkout UI** — handle its `kind` (e.g. `"adyen_dropin"`) in `pay.html.twig`.
4. Add `adyen` to the environments / admin dropdown by simply having the gateway
   registered — `GetProviderSettingsHandler` lists whatever the registry contains.

**No changes** to any use-case handler, to `PaymentGatewayResolver`, or to the domain.
That is the extensibility test for this design.

## 7. Production concerns deliberately omitted

| Omitted | Why it's safe to omit here / what we do instead |
|---|---|
| Webhooks | Completion is driven synchronously from the return/approve step and verified server-side. A real system would additionally reconcile via webhooks. |
| Background workers / brokers | Both buses are synchronous. No message is queued. |
| Event sourcing / outbox | The aggregate stores current state only. No cross-system event publishing. |
| Automatic retries / reconciliation | On an ambiguous provider response the payment is left `processing` and the UI says "outcome could not be confirmed". No retry, no polling job. |
| Cross-provider failover | A failed/uncertain start never tries another provider — that would double-charge risk and confuse reporting. |
| Refunds / subscriptions | Out of scope; only one-off EUR payments. |
| Full idempotency / concurrency subsystem | The UI replaces the form after submit; the completion handler no-ops when the payment is already `succeeded`. This is **not** production-grade duplicate-payment protection — no idempotency keys, no row locking. |
| Deployment infrastructure | `Dockerfile` + `docker-compose.yml` (+ `compose.override.yml` for dev) run the PHP built-in server against a SQLite file. Dev only. |

### Consistency limitation (documented, not solved)

`InitiatePaymentHandler` persists the `Payment` and commits **before** calling the provider,
so no database transaction is ever held open across a network call. The trade-off: if the
process dies between "provider created the checkout" and "we saved the provider reference",
the payment stays `pending` with no reference. A production system would recover this via a
webhook or a reconciliation job keyed on the `payment_id` metadata we send to both
providers. Here we simply surface the uncertain state.
