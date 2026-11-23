# Decisions

The brief leaves some details open on purpose. These are the choices I made and why.

## Money

**Integer nanodollars everywhere.** Pixelforge reports costs as exact 9-decimal strings ("never rounded"), and every
rate is in dollars per 1M tokens. Storing `bigint` nanodollars (1 USD = 10⁹) represents every amount Pixelforge can bill
exactly, with no floats and no decimal library. `App\Billing\Money` parses and formats; the browser never does
arithmetic on money, it only formats the strings the server sends (with `BigInt`).

**Cost = Σ(tokens × rate) ÷ 1M, divided once.** `Rates::costNanos()` multiplies each token count by the rate in
nanodollars per 1M tokens, sums, and divides by 1M at the end. With today's rates this is always exact. If a future
rate had more than 3 decimals, the result would be rounded up at the last step (we'd never under-record a cost).

**Rates come from the generation, not from our price list.** Pixelforge fixes the rates when a generation is created
and returns them on the generation object. The actual cost always uses those. `GET /v1/pricing` (cached 10 minutes,
with the last good copy kept for outages) is only used for estimates.

**Markup: provider cost × 1.25, rounded up to the next nanodollar.** At current rates every provider cost is a multiple
of 500 nanodollars, so ×1.25 is already exact. Rounding up only matters if rates change, and then it's in our favour
by at most a nanodollar. I didn't round to cents. Users see 4 decimals (e.g. `$0.0723`), and hovering shows the exact
amount. A balance can sit at a fractional cent, which is fine for prepaid credit.

**Failed generations cost nothing**, whether Pixelforge rejected the request, it failed after acceptance, or we gave up
submitting. `actual_*_nanos` is set to 0 and the hold is released in the same transaction that marks the generation
failed.

## Estimates

Before generating, image and output tokens are exact. Text tokens are `ceil(chars / 4)` ±15% (Unicode characters, so
`mb_strlen`), and reasoning tokens fall anywhere in a per-quality range. So the UI shows:

- **Estimate**: text estimate plus the midpoint of the reasoning range. Stored as `estimated_*`.
- **Range**: the minimum (−15% text, minimum reasoning) and maximum (+15% text, maximum reasoning).

The same `PricingCalculator` produces the live estimate (via `GET /generations/estimate`) and the final charge, so the
UI and the bill can't drift apart.

## Balances (stretch goal)

- `users.balance_nanos` plus a `credit_transactions` ledger. Every change goes through `CreditLedger` and writes a row,
  so the balance always equals the sum of the ledger.
- **Hold the worst case.** On submit we hold the maximum possible charge. When the generation succeeds we release the
  hold and charge the actual cost; when it fails we just release. Because the actual cost can never exceed the
  maximum, a finished generation can never take a user below zero, and "can they afford it?" is a single comparison
  against the spendable balance.
- The user row is locked (`lockForUpdate`) for every balance change, so two concurrent submits can't both spend the
  same money.
- New users get $2 of welcome credit so reviewers can try the app immediately. "Buy credits" offers fixed $5/$10/$25
  packs (no payment), and the server only accepts those amounts.

## Webhooks

- **Signature**: HMAC-SHA256 of `"{t}.{raw body}"`, compared with `hash_equals`, rejected if the timestamp is more than
  5 minutes away from now (replay protection). Checked against the raw body before any JSON decoding.
- **Dedup by event id**: `webhook_events.event_id` is unique. We `insertOrIgnore` the event and apply it **in the same
  transaction**. A duplicate inserts nothing and is acknowledged with 200. If applying throws, the event row rolls back
  too, we return 500, and Pixelforge's retry gets a clean second attempt.
- **Out of order**: `ApplyProviderState` is the only code that changes a generation's status. It locks the row, and
  status only moves forward (pending → queued → processing → succeeded|failed). Older news is recorded as
  `ignored_stale`.
- **Charged at most once, three times over**: (1) event-id dedup; (2) money only moves on the single transition into a
  final status, under a row lock; (3) a unique `(generation_id, type)` index on the ledger.
- **A webhook before the create request returns**: we send our own id in `metadata.generation_id` and match on it
  first, so an early `succeeded` webhook still finds the right row and records the provider id.
- Unknown generations are acknowledged (200) and logged, since retrying wouldn't help.
- The work is small DB updates, so it runs inline (well inside the 10-second timeout) rather than on a queue. That
  keeps "recorded" and "acknowledged" atomic.

## Submitting to Pixelforge

- The browser request only records the generation and holds credit. A queued job (`SubmitGeneration`) calls
  Pixelforge, so a slow or overloaded provider never blocks the request.
- **Idempotency-Key = our generation ULID** on every attempt, so a retry after a timeout or a worker crash can never
  create a second generation or a second bill.
- **Retries**: 429 (rate and concurrency limits), 503 overloaded, other 5xx and connection errors are retried with
  `max(Retry-After, 2^attempt)` seconds (capped at 60s, plus jitter), for up to 12 attempts (about 10 minutes). While
  it waits, the UI shows "Waiting for capacity" with the reason and a countdown. Nothing is charged.
- **Not retried**: 400 content policy, 422 validation, 409 idempotency mismatch, and the daily quota (it won't clear for
  hours). These fail immediately and for free, with a plain-language message.
- **Giving up after an ambiguous error.** After network errors we can't be sure the request didn't land. We mark it
  failed and free, but if Pixelforge later reports it finished, Pixelforge's word wins and we record the real outcome
  and cost, so our records still match what Pixelforge bills. This override only applies to generations Pixelforge
  never confirmed to us.

## Missed webhooks

Delivery isn't guaranteed. `generations:reconcile` runs every minute (Laravel scheduler). It:

- polls `GET /v1/generations/{id}` for queued or processing generations that haven't changed for 90 seconds, at most 15
  per run to stay well inside 60 requests/minute, and feeds the result through the same `ApplyProviderState` as
  webhooks;
- re-dispatches pending generations whose submit job seems lost (safe because of the idempotency key).

## Admin report (stretch goal)

- A day is the **UTC day the generation completed**, because that's how `GET /v1/usage` buckets costs. Only succeeded
  generations count.
- Each day shows charged, provider cost and profit next to Pixelforge's own `cost_usd` and generation count for the API
  key, with a ✓/✗ for an exact match. All values are exact 9-decimal strings, compared as strings.
- Access requires `users.is_admin` (`php artisan user:make-admin you@example.com`).

## Product and UX

- Live status uses Inertia polling every 2.5 seconds, only while something is in flight. It reloads just
  `generations` and `balance`. This is simpler and more robust than websockets for this scale.
- Failed generations offer **Try again**, which creates a new generation with the same settings and its own estimate and
  reservation. Pixelforge says `internal_error` failures usually succeed on retry, but we never retry automatically,
  because a retry is a separately billed generation.
- Email verification is turned off so reviewers can sign up without a mail server.
- Validation mirrors Pixelforge's limits (prompt ≤ 4,000 characters, ≤ 4 http(s) reference URLs, enum sizes and
  qualities read from the price list), so users get instant, specific errors rather than a failed generation.

## Images

- Pixelforge's signed image URLs expire 24 hours after completion, and reviewers will look later than that. So on success
  `StoreGenerationImage` copies the PNG into our storage (`IMAGES_DISK`: local in development, a bucket in production).
  `generations:reconcile` backfills any copy the queue missed while Pixelforge's URL is still valid.
- Images are private: they're served through an owner-only route (`/generations/{id}/image`), not public URLs.
- The fetcher only accepts bytes that start with the PNG signature, so an error page can never be saved as an image.
  If the stored URL is rejected, it asks Pixelforge for a freshly signed one once.
- Downloads go through the server too. Browsers ignore `download` on cross-origin links, and this lets us name the file
  after the prompt.

## Operations

- **Operations** (admin) shows webhook outcomes (applied, ignored as stale, duplicate count, unmatched), retried
  deliveries, work waiting for capacity or quiet for 5+ minutes, failures by reason, and a 7-day cost check against
  Pixelforge.
- `usage:check` runs hourly, compares the last three days with `/v1/usage`, logs a warning for any day that differs,
  and records the result for the Operations page. A generation that has finished at Pixelforge but whose webhook we
  haven't processed yet can cause a brief difference; one that persists is a bug.
- Duplicate deliveries are counted on the event row (`duplicate_deliveries`), so "never recorded twice" is visible,
  not just true.

## Testing

- The pricing tests use the docs' own worked examples (the $0.373875 maximum and the usage example) as fixtures.
- `EventOrderingTest` sends every ordering of five deliveries (processing ×2, final ×2, plus the create response):
  120 orderings each for success and failure. Each must reach the same final state with exactly one charge, or a full
  refund, and the status must never move backwards at any step. I checked that it catches a deliberately broken
  forward-only rule.
- A regression test pushes a job through the real database queue driver. The sync driver and `Queue::fake()` hid a bug
  where a method named `backoff()` clashed with Laravel's job hook of the same name.

## Things I'd do next

- Throttle submissions locally to the account-wide limit of 3 concurrent generations, instead of discovering it through
  429s.
- Alert (Slack or email) when `usage:check` finds a mismatch, instead of only logging it.
- Thumbnails for the gallery, so it doesn't load full-size PNGs.
