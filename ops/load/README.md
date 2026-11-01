# Load test harness

This is a harness, not evidence that the T049 pilot profile passed. The full profile is 50 tenants, 100,000 historical orders, 20 RPS, 50 virtual users, and an eight-hour soak. The local smoke run is only a wiring check.

Install k6 separately, then run:

```bash
BASE_URL=http://127.0.0.1:8000 \
API_TOKEN=... TENANT_ID=... BRANCH_ID=... LOCATION_ID=... \
RPS=2 VUS=2 DURATION=10s k6 run ops/load/k6-pilot.js
```

Do not commit or paste tokens into this directory. Use a disposable test tenant and a migrated test database. The endpoint mix is intentionally read-heavy for the initial wiring check; production pilot evidence must also include order intake, production, payment, export degradation, queue age, lock, and 5xx separation as required by T049.
