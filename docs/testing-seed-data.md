# Testing Seed Data

Run the comprehensive testing fixture directly:

```bash
php artisan db:seed --class=TestingSeedDataSeeder
```

Or include it in the normal database seed run:

```bash
SEED_TESTING_DATA=true php artisan db:seed
```

All seeded users use the password `password` and have MFA marked as configured so they can exercise protected application flows.

| Role | Email |
| --- | --- |
| Super admin | `seed.admin@futureshiftadvisory.test` |
| Lead advisor | `seed.advisor@futureshiftadvisory.test` |
| Junior advisor | `seed.junior@futureshiftadvisory.test` |
| Client principal | `seed.client.primary@futureshiftadvisory.test` |
| Client team | `seed.client.team@futureshiftadvisory.test` |
| Buyer principal | `seed.buyer.primary@futureshiftadvisory.test` |
| Buyer analyst | `seed.buyer.analyst@futureshiftadvisory.test` |
| Entrepreneur | `seed.entrepreneur@futureshiftadvisory.test` |
| Broker | `seed.broker@futureshiftadvisory.test` |
| Coach | `seed.coach@futureshiftadvisory.test` |
| Mentor | `seed.mentor@futureshiftadvisory.test` |

The fixture covers active, paused, suspended, offboarded, due-diligence, post-acquisition, and entrepreneur workflows. It is idempotent, so it can be re-run while developing without duplicating the seeded records.
