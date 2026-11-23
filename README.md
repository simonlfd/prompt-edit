# PromptEdit Studio

PromptEdit's take-home assessment: an image-generation app on Laravel 13, Vue 3 and Inertia that sends generations to
**Pixelforge**, a simulated AI provider. It charges users Pixelforge's exact cost plus 25%, and handles rate limits,
overloads, failures and duplicate or out-of-order webhooks.

- **Generate**: write a prompt, pick size and quality, and add reference image URLs. A live estimate shows the expected
  cost and the min–max range before you submit.
- **History**: live status (submitting, waiting for capacity, queued, generating, ready, failed), estimated vs actual
  cost, the image, and a plain-language reason when something fails. Failed generations show `$0.00 · not charged`.
- **Balances**: welcome credit, a fake "Buy credits" button, the worst case held on submit, and generating blocked when
  you can't afford it.
- **Admin spend report**: credit spend, provider cost and profit per UTC day and per user, checked line by line against
  Pixelforge's `GET /v1/usage`.

The reasoning behind each choice is in **[DECISIONS.md](DECISIONS.md)**. The UI follows
[docs/style-guide.md](docs/style-guide.md).

## How it fits together

```
Browser ──POST /generations──▶ CreateGeneration ── estimate, hold worst case ─▶ generations (pending)
                                      │
                                      ▼ queue
                               SubmitGeneration ──POST /v1/generations (Idempotency-Key = our id)──▶ Pixelforge
                                      │  retries 429/503/network with backoff
                                      ▼
Pixelforge ──webhook──▶ PixelforgeWebhookController ─ verify signature
                                      ▼
                          HandleWebhookEvent ─ dedup by event id (same transaction)
                                      ▼
Scheduler ─ generations:reconcile ─▶ ApplyProviderState ◀── create response
           (polls overdue ones)        lock row · forward-only status · on final: cost, charge, settle ledger
```

| Where                                                  | What                                                                                                            |
| ------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| `app/Billing`                                          | `Money` (nanodollars), `Rates`, `Pricing`, `PricingCalculator`, `CreditLedger`                                  |
| `app/Pixelforge`                                       | HTTP client, errors, cached price list, webhook signatures                                                      |
| `app/Generations`                                      | `CreateGeneration`, `ApplyProviderState` (the state machine), `HandleWebhookEvent`                              |
| `app/Jobs/SubmitGeneration.php`                        | Submission with retries and backoff                                                                             |
| `app/Console/Commands/ReconcileGenerations.php`        | Polling for missed webhooks and lost jobs                                                                       |
| `app/Reports/SpendReport.php`                          | Admin report and `/v1/usage` reconciliation                                                                     |
| `resources/js/pages/Studio.vue`, `components/studio/*` | The UI                                                                                                          |
| `tests/`                                               | Pest: pricing against the docs' worked examples, webhooks (signature, dedup, ordering), retries, ledger, report |

## Running locally

```bash
composer setup                      # install, .env, key, migrate, npm build
# Put a *local* Pixelforge API key and its signing secret in .env:
#   PIXELFORGE_API_KEY=...  PIXELFORGE_WEBHOOK_SECRET=whsec_...
composer dev                        # server, queue worker, scheduler, logs, vite
```

Pixelforge has to reach your machine to deliver webhooks, so start a tunnel, e.g. `cloudflared tunnel --url
http://localhost:8000`, and set the key's webhook URL in the Pixelforge dashboard to
`https://<tunnel>/webhooks/pixelforge`. Without a tunnel the app still works: `generations:reconcile` polls overdue
generations every minute.

Make yourself an admin to see the spend report:

```bash
php artisan user:make-admin you@example.com
```

Put `[fail]`, `[slow]`, `[duplicate]`, `[blocked]`, `[overloaded]` or `[succeed]` in a prompt to trigger Pixelforge's
failure modes on purpose. The "Test keywords" link above the prompt lists them.

## Tests

```bash
php artisan test        # or ./vendor/bin/pest
./vendor/bin/phpstan analyse
npm run types:check && npm run check
```

## Deploying (Laravel Cloud)

1. Create the app from this repository, with a database (Postgres or MySQL).
2. Environment: `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` from a **separate** Pixelforge key for production,
   plus `QUEUE_CONNECTION=database`.
3. Enable a **queue worker** and the **scheduler**, which runs `generations:reconcile` every minute.
4. Deploy command: `php artisan migrate --force`.
5. In the Pixelforge dashboard, set that key's webhook URL to `https://<your-app>/webhooks/pixelforge`.
