# NewsBlendr 2.0

NewsBlendr is a modern news aggregation and personalization platform built with Laravel and Docker, utilizing Laravel Sail for local development.

## Features

- 🔍 Full-text search powered by Meilisearch
- 🎯 Personalized news feed based on user preferences
- 📱 RESTful API for seamless integration
- 🔐 JWT Authentication with Laravel Sanctum
- 📊 Article analytics and trending content
- 🔖 Bookmark functionality
- 📂 Category and source-based filtering
- 👥 Author tracking and popularity metrics
- 🚀 Docker-based development environment with Laravel Sail

## Prerequisites

Before you begin, ensure you have the following installed:
- Docker (20.10.x or higher)
- Docker Compose (2.x or higher)
- Git

For local development without Docker, you'll also need:
- PHP 8.2+
- Composer 2.x

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/newsBlendr2.0.git
cd newsBlendr2.0
```

2. Install Laravel Sail:
```bash
composer require laravel/sail --dev
php artisan sail:install
```

3. Copy the environment file:
```bash
cp .env.example .env
```

4. Configure your environment variables in `.env`:
```env
APP_NAME=NewsBlendr
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=newsBlendr
DB_USERNAME=sail
DB_PASSWORD=password

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey

# Add your API keys for news sources
NEWSAPI_KEY=your_newsapi_key
GUARDIAN_API_KEY=your_guardian_key
NYTIMES_API_KEY=your_nytimes_key
NYTIMES_API_SECRET=your_nytimes_secret
```

5. Create a Sail alias (optional but recommended):
```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

6. Start the Sail containers:
```bash
sail up -d
```

7. Install PHP dependencies:
```bash
sail composer install
```

8. Generate application key:
```bash
sail artisan key:generate
```

9. Run database migrations:
```bash
sail artisan migrate
```

10. Seed the database with initial data:
```bash
# Seed all data
sail artisan db:seed


11. Initialize Meilisearch:
```bash
sail artisan meilisearch:initialize
```

## Laravel Sail Services

The application uses several Docker containers managed by Laravel Sail:

- **laravel.test**: Main application container (PHP 8.2)
- **mysql**: MySQL 8.0 database
- **redis**: Redis for caching and queues
- **meilisearch**: Meilisearch search engine
- **mailpit**: Local email testing
- **selenium**: For browser testing
- **horizon**: Laravel Horizon for queue monitoring
- **scheduler**: Laravel scheduler for cron jobs

To manage these services:
```bash
# Start all services
sail up -d

# Stop all services
sail down

# View logs
sail logs

# View logs for specific service
sail logs service-name

# Run artisan commands
sail artisan [command]

# Run composer commands
sail composer [command]

# Run npm commands
sail npm [command]
```

## API Documentation

The API provides the following main endpoints:

### Public Endpoints
- `GET /api/articles/preview`: Get a preview of articles
- `GET /api/articles/{id}/preview`: Get a preview of a specific article
- `POST /api/login`: User authentication
- `POST /api/logout`: User logout (requires authentication)

### Protected Endpoints (require authentication)
- `GET /api/articles`: List articles with search and filters
- `GET /api/articles/trending`: Get trending articles
- `GET /api/articles/latest`: Get latest articles
- `GET /api/articles/{id}`: Get specific article
- `POST /api/articles/{id}/bookmark`: Bookmark an article
- `DELETE /api/articles/{id}/bookmark`: Remove bookmark
- `GET /api/bookmarks`: Get user's bookmarked articles
- `GET /api/categories`: List categories
- `GET /api/sources`: List sources
- `GET /api/authors`: List authors
- `GET /api/preferences`: Get user preferences
- `POST /api/preferences`: Update user preferences
- `GET /api/feed`: Get personalized news feed

## Troubleshooting

### Meilisearch Issues
If Meilisearch keeps restarting or fails to initialize:
1. Check container logs:
```bash
sail logs meilisearch
```
2. Ensure sufficient memory is allocated in `docker-compose.yml`:
```yaml
deploy:
    resources:
        limits:
            memory: 1G
        reservations:
            memory: 512M
```
3. Reinitialize the search index:
```bash
sail artisan scout:flush "App\Models\Article"
sail artisan meilisearch:initialize
```

### Permission Issues
If you encounter permission issues:
```bash
sail root-shell
chown -R www-data:www-data storage
chmod -R 775 storage
```

### Database Connection Issues
If MySQL connection fails:
1. Check if MySQL is running:
```bash
sail ps
```
2. Verify credentials in `.env` match `docker-compose.yml`
3. Wait for MySQL to fully initialize (can take up to 30 seconds)
4. Try resetting the database:
```bash
sail down -v
sail up -d
sail artisan migrate:fresh
```

## Development

### Database Management
```bash
# Fresh migration with seeds
sail artisan migrate:fresh --seed

# Only run seeds
sail artisan db:seed

# Create a new seeder
sail artisan make:seeder YourNewSeederName

# Rollback last migration
sail artisan migrate:rollback
```

### Running Tests
```bash
sail test
# or
sail artisan test
```

### Code Style
The project follows PSR-12 coding standards. To check code style:
```bash
sail pint --test
```

To automatically fix code style:
```bash
sail pint
```

### Queue Processing
The project uses Redis for queue processing. Horizon is included for queue monitoring:
```bash
# Start Horizon
sail up horizon -d

# Access Horizon dashboard
http://localhost/horizon
```

## Production Deployment

For production deployment:

1. Update `.env` for production:
```env
APP_ENV=production
APP_DEBUG=false
MEILISEARCH_KEY=your_secure_key
```

2. Configure proper SSL certificates for Meilisearch:
```env
MEILISEARCH_HOST=https://search.yourdomain.com
```

3. Set up proper memory limits for containers in `docker-compose.yml`:
```yaml
deploy:
    resources:
        limits:
            memory: 2G
        reservations:
            memory: 1G
```

4. Enable queue workers and scheduler:
```bash
sail up -d horizon scheduler
```

5. Set up proper backup strategy for MySQL and Meilisearch data volumes

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- [Laravel](https://laravel.com)
- [Laravel Sail](https://laravel.com/docs/sail)
- [Meilisearch](https://www.meilisearch.com)
- [Docker](https://www.docker.com)
