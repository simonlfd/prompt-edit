# PromptEdit Studio

![tests](https://github.com/simonlfd/prompt-edit/actions/workflows/tests.yml/badge.svg)

PromptEdit's take-home assessment: an image-generation app on Laravel 13, Vue 3 and Inertia that sends generations to
**Pixelforge**, a simulated AI provider. It charges users Pixelforge's exact cost plus 25%, never charges twice, never
charges for failures, and keeps working through rate limits, overloads and duplicate or out-of-order webhooks.

**Live app:** https://prompt-edit-production-lgdsai.laravel.cloud

The reasoning behind each choice is in **[DECISIONS.md](DECISIONS.md)**. The UI follows
[docs/style-guide.md](docs/style-guide.md).

---

## For reviewers: verify it in 5 minutes

Log in at the live app with the reviewer account from the application form (an admin with $25 of credit), or sign up
for your own account, which starts with $2.

**Already there:** the reviewer account holds a finished test round, so you can compare with Pixelforge's records straight
away: five succeeded generations in different sizes and qualities (one with a reference image, one `[duplicate]`, one
`[slow]`) and two free failures (`[fail]`, `[blocked]`). Their provider cost matches `GET /v1/usage` for the production key
to the last digit.

**1. Generate and watch the money.** In **Studio**, type a prompt. The estimate updates as you type and shows a min–max
range ("How it's calculated" opens the token maths). Generate it: the stage shows live progress and a timer, then
**"You paid"** with the actual bill line by line (tokens × rate per token type, Pixelforge's cost, the 25% fee) and how it
compared with the estimate. Your balance drops by exactly that amount. **Billing** shows the reserve → return →
charge entries behind it.

**2. Break it on purpose.** Put these in a prompt (they're also listed under "Test keywords" above the prompt box):

| Keyword         | What you should see                                                                        | Cost                        |
| --------------- | ------------------------------------------------------------------------------------------ | --------------------------- |
| `[fail]`        | Accepted, then **Failed**, with a plain reason and a **Try again** button                  | $0.00, reservation returned |
| `[blocked]`     | Fails immediately: "rejected under its content policy"                                     | $0.00                       |
| `[overloaded]`  | **Waiting for capacity** with a retry countdown, backing off for ~8 minutes, then gives up | $0.00                       |
| `[duplicate]`   | Every webhook arrives twice; charged once. See **Operations → Duplicates ignored**         | Charged once                |
| `[slow]`        | 60–90 seconds of processing, with the timer running                                        | Normal                      |
| Several at once | Pixelforge allows 3 concurrent generations; the rest wait for capacity and go through      | Normal                      |

**3. Check the books (admin).**

- **Spend report**: credit spend, provider cost and profit per UTC day and per user, with Pixelforge's own
  `GET /v1/usage` figure beside each day and a ✓ when they match exactly (they're compared as 9-decimal strings).
  Charts show each day's charge split into Pixelforge's cost and our margin, and the top spenders.
- **Operations**: every webhook with its outcome (applied, out-of-order and ignored, duplicate count), retried
  deliveries, failures by reason, hourly charts of outcomes and webhook handling, and an hourly automatic reconciliation
  against Pixelforge.

**4. Look around.** **Gallery** has filters (favourites, ready, failed), sort by cost, a keyboard-navigable lightbox and
downloads named after the prompt. Images are copied to our own storage, so they still load days after Pixelforge's
24-hour links expire.

### Good to know

- **Pixelforge limits are per account, across keys:** 3 concurrent generations and 60 requests a minute. If several of
  you generate at once, some generations will show **Waiting for capacity** and then go through. That's the app working
  as designed, not a fault.
- **A brand-new generation can briefly show ✗ in the spend report.** The report caches Pixelforge's usage for 30 seconds,
  and Pixelforge only counts a generation once it completes. Refresh after a minute and it matches.
- **Days are UTC**, and a generation counts on the day it completed, because that's how Pixelforge's usage report buckets
  costs.
- **Images are placeholders.** Pixelforge is simulated, so every image is an abstract pattern captioned with its prompt.
  We keep our own private copy, so they keep loading after Pixelforge's 24-hour links expire.
- **Credits are fake.** "Buy credits" adds $5, $10 or $25 without taking a payment.
- **Production has its own Pixelforge key**, separate from development, so its usage report contains only the live app's
  generations.

---

## What's in it

| Requirement                                                              | Where                                                                       |
| ------------------------------------------------------------------------ | --------------------------------------------------------------------------- |
| Prompt, size, quality, reference URLs, cost shown **before** submitting  | `GenerateForm.vue`, `EstimateController`, `PricingCalculator::estimate()`   |
| Pixelforge cost + 25%, actual cost recorded on completion, failures free | `ApplyProviderState`, `PricingCalculator`, `Rates::costNanos()`             |
| History with live status, estimated vs actual cost, the image            | Studio stage and rail, Gallery (`usePoll` while anything is in flight)      |
| Webhooks: signatures, duplicates, out of order                           | `PixelforgeWebhookController`, `HandleWebhookEvent`, `ApplyProviderState`   |
| Stretch: admin report matching `/v1/usage`                               | `SpendReport`, `admin/SpendReport.vue`, `usage:check`                       |
| Stretch: balances, buy credits, block when unaffordable                  | `CreditLedger` (reserve the worst case, settle on completion), Billing page |

Beyond the brief: our own copies of images, favourites, a gallery with downloads, "try again" and "reuse settings",
a Billing ledger, an Operations page, admin charts, and a test that runs **all 240 orderings** of duplicate and out-of-order
deliveries.

## How it fits together

```
Browser ──POST /generations──▶ CreateGeneration ── estimate, reserve worst case ─▶ generations (pending)
                                      │
                                      ▼ queue
                               SubmitGeneration ──POST /v1/generations (Idempotency-Key = our id)──▶ Pixelforge
                                      │  retries 429/503/network with capped backoff
                                      ▼
Pixelforge ──webhook──▶ PixelforgeWebhookController ─ verify HMAC signature + timestamp
                                      ▼
                          HandleWebhookEvent ─ dedup by event id, in the same transaction
                                      ▼
Scheduler ─ generations:reconcile ─▶ ApplyProviderState ◀── create response
           (polls overdue ones)        lock row · status only moves forward · on final: cost, charge, settle ledger
                                      ▼
                              StoreGenerationImage ─ copy the PNG to our storage
```

| Where                                                           | What                                                                                                               |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `app/Billing`                                                   | `Money` (integer nanodollars), `Rates`, `Pricing`, `PricingCalculator`, `CreditLedger`                             |
| `app/Pixelforge`                                                | HTTP client, errors, cached price list, webhook signatures, image fetcher                                          |
| `app/Generations`                                               | `CreateGeneration`, `ApplyProviderState` (the state machine), `HandleWebhookEvent`                                 |
| `app/Jobs`                                                      | `SubmitGeneration` (retries and backoff), `StoreGenerationImage`                                                   |
| `app/Console/Commands`                                          | `generations:reconcile` (every minute), `usage:check` (hourly), `app:create-reviewer`                              |
| `app/Reports/SpendReport.php`                                   | Admin report and `/v1/usage` reconciliation                                                                        |
| `resources/js/pages`, `components/studio`, `components/gallery` | The UI                                                                                                             |
| `tests/`                                                        | Pest: pricing against the docs' worked examples, webhooks, every event ordering, retries, ledger, storage, reports |

## Running locally

```bash
composer setup                      # install, .env, key, migrate, npm build
# In .env, use a *local* Pixelforge API key and its signing secret:
#   PIXELFORGE_API_KEY=...  PIXELFORGE_WEBHOOK_SECRET=whsec_...
composer dev                        # server, queue worker, scheduler, logs, vite
```

Webhooks need a public URL, so start a tunnel, e.g. `cloudflared tunnel --url http://127.0.0.1:8000`, and set the
key's webhook URL in the Pixelforge dashboard to `https://<tunnel>/webhooks/pixelforge`. Without a tunnel the app still
works: `generations:reconcile` polls overdue generations every minute.

```bash
php artisan app:create-reviewer you@example.com   # admin login with $25, prints a generated password
```

## Tests and checks

```bash
composer ci:check     # everything CI runs: lint, PHPStan, vue-tsc, tests
php artisan test      # 364 Pest tests, including 240 delivery orderings
```

## Deploying (Laravel Cloud)

1. Create the app from this repository with a database (Postgres or MySQL) and a **private** object storage bucket.
   Images are only ever served to their owner: the app checks ownership, then redirects to a 5-minute signed URL.
2. Environment:
    - `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` from a **separate** Pixelforge key for production
    - `QUEUE_CONNECTION=database`
    - `IMAGES_DISK` set to the bucket's disk name
3. Turn on a **queue worker** and the **scheduler** (`generations:reconcile` every minute, `usage:check` hourly).
4. Deploy command: `php artisan migrate --force`.
5. In the Pixelforge dashboard, set that key's webhook URL to `https://<your-app>/webhooks/pixelforge` (exact URL:
   Pixelforge doesn't follow redirects), then press "Send test event".
6. `php artisan app:create-reviewer <email>` and share the printed password through the application form.
