# KMI Face Service

Local FastAPI service for attendance face enrollment and verification.

## Development

Install Python dependencies once:

```bash
npm run face:install
```

Run with Laravel and Vite:

```bash
npm run dev:all
```

The service listens on `http://127.0.0.1:9000` and is called only by Laravel.
InsightFace may download the `buffalo_l` model on first use.
