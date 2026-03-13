# PromptWalls - Privacy Content Firewall

## Quick Start

### Prerequisites
- PHP 8.2+ with Composer
- Node.js 18+
- MySQL 8.0+
- Ollama (running locally with models: glm-ocr-lastest, qwen3:14b)

### Backend Setup

```bash
cd backend

# Copy env and configure MySQL credentials
cp .env.example .env
# Edit .env with your DB credentials

# Install dependencies
composer install

# Generate app key
php artisan key:generate

# Create database
mysql -u root -e "CREATE DATABASE promptwalls;"

# Run migrations
php artisan migrate

# Start server
php artisan serve

# Start file watcher (in separate terminal)
php artisan files:watch
```

### Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

Visit http://localhost:5173

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/dashboard/stats | Dashboard statistics |
| GET | /api/dashboard/recent | Recent activity |
| POST | /api/files/upload | Upload files |
| GET | /api/files?folder=incoming | List files |
| POST | /api/reidentify/process | Re-identify text |
| GET | /api/settings | Get configuration |
| PUT | /api/settings | Update configuration |
