# Base image with Python 3.11
FROM python:3.11-slim-bullseye

# Set working directory
WORKDIR /app

# Install system dependencies for Playwright, Firefox, Xvfb, and cron
RUN apt-get update && apt-get install -y \
    # Playwright/Firefox dependencies
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libdbus-1-3 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libpango-1.0-0 \
    libcairo2 \
    libasound2 \
    libatspi2.0-0 \
    libwayland-client0 \
    # Xvfb for headless display
    xvfb \
    # Cron for scheduled tasks
    cron \
    # Utilities
    curl \
    wget \
    procps \
    # Clean up
    && rm -rf /var/lib/apt/lists/*

# Copy requirements first for better layer caching
COPY requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Install Playwright browsers (Firefox)
RUN playwright install firefox
RUN playwright install-deps firefox

# Copy application code
COPY . .

# Create necessary directories
RUN mkdir -p /app/data/message_images \
    /app/logs \
    /app/screenshots \
    /app/debug_output \
    /app/avatar_cache \
    /app/auth

# Make scripts executable
RUN chmod +x /app/*.sh 2>/dev/null || true
RUN chmod +x /app/docker-entrypoint.sh

# Set environment variables
ENV PYTHONUNBUFFERED=1
ENV DISPLAY=:99

# Expose no ports (scraper doesn't need external access)

# Run entrypoint script
ENTRYPOINT ["/app/docker-entrypoint.sh"]

