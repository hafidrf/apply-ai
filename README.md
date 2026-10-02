# Asisten Apply Kerja

Asisten Apply Kerja (Job Application Assistant) is a web app that turns a candidate profile and a job posting into application messages that are ready to edit and send.

Paste plain text, upload PDFs or images, or add links. The app processes the input with the AI model of your choice and helps you write the message.

## Features

- Candidate profiles built from text, multiple PDFs, images, and links.
- Job input from text, PDFs, images, and links, including social media posts.
- BYOK (bring your own key): use your own AI provider API key. Keys are stored encrypted by Laravel.
- Support for OpenAI-compatible providers and Google Gemini.
- Message generation per channel: email, LinkedIn, WhatsApp, portal, or social DM.
- Message revision with natural language instructions, for example "make it shorter and more natural".
- Message history grouped by month, including revised messages and copies.
- Requirement matching analysis plus interview preparation notes.
- In-browser image OCR as a fallback option.

## Tech stack

- Backend: Laravel 13, PHP 8.3+, Sanctum, SQLite for development or MySQL for production.
- Frontend: React, TypeScript, Vite, Tailwind CSS.
- Document extraction: smalot/pdfparser and Symfony DomCrawler.

## Requirements

- PHP 8.3 or newer with the `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, and `pdo_mysql` extensions, depending on the database you use.
- Composer.
- Node.js and npm.

## Run locally

From the project folder:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database/database.sqlite
```

Set `.env` for local development:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Run the migrations:

```powershell
php artisan migrate
```

Install the frontend dependencies and start Vite:

```powershell
cd frontend
npm ci
npm run dev
```

In a second terminal, from the project folder:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://localhost:5173`. Vite forwards `/api` requests to the backend on port 8000.

On macOS or Linux, use the equivalent shell commands to copy `.env.example` to `.env` and to create the SQLite file.

## Configure an AI provider

1. Create an account and sign in to the app.
2. Open **Settings**.
3. Add a provider, API key, and model.
4. Use the **Test** button to check the connection.
5. Mark the model as vision capable if the provider and model support image input.

API keys are stored using the Laravel encrypted cast. Keep `APP_KEY` safe and never change it after user keys have been stored, because encrypted data cannot be read without the same key.

## Checks

Run the backend tests:

```powershell
php artisan test
```

Type check and build the frontend:

```powershell
cd frontend
npm run build
```

## Production build

Build the frontend from the `frontend` folder:

```powershell
npm ci
npm run build
```

Vite writes `index.html` and the built assets into the `public` folder. Run the migrations on the server after configuring the production `.env`:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

A cPanel hosting guide is available in [docs/DEPLOY.md](docs/DEPLOY.md).

## Security and configuration

- Never commit `.env`, API keys, tokens, local databases, logs, or hosting credentials.
- Set `APP_DEBUG=false` in production.
- Point the web server document root to the `public` folder.
- Use HTTPS in production.
- Keep a secure backup of `APP_KEY`.

## License

Not decided yet. Add a license file before distributing the project publicly.
