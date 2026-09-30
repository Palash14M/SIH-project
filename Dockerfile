# ==============================================================================
# Production Dockerfile for Smart Inspection App (MoSJE SIH26095)
# Optimized for Cloud Deployment (Render.com, Railway, Cloud Run, Fly.io)
# ==============================================================================

FROM php:8.3-cli-alpine

# Install SQLite3 libraries and compile required PDO SQLite driver
RUN apk add --no-cache sqlite-libs sqlite-dev \
    && docker-php-ext-install pdo_sqlite \
    && apk del sqlite-dev

# Production PHP settings: Support photo/video evidence uploads up to 64MB
RUN { \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "memory_limit=256M"; \
        echo "display_errors=Off"; \
        echo "log_errors=On"; \
        echo "date.timezone=Asia/Kolkata"; \
    } > /usr/local/etc/php/conf.d/custom-production.ini

WORKDIR /app

# Copy application source code (excluding items specified in .dockerignore)
COPY . /app

# Ensure storage directories exist with full read/write permissions for SQLite database and media
RUN mkdir -p /app/backend/storage/media /app/backend/storage/reports \
    && chmod -R 777 /app/backend/storage

# Default environment variables
ENV PORT=10000
ENV APP_ENV=production
ENV DB_CONNECTION=sqlite
ENV DB_FILE=storage/database.sqlite

# Expose operational ports (10000 for Render.com, 8080 for standard containers)
EXPOSE 10000 8080

# Health check endpoint for automated cloud deployment monitoring
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget -q --spider http://127.0.0.1:${PORT:-10000}/api/health || exit 1

# Execution entry point: Serves web dashboards, static assets, and REST API via central router
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app backend/router.php"]
