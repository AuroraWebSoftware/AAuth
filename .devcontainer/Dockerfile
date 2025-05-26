# Use the official PHP image as a base
FROM mcr.microsoft.com/devcontainers/php:1-8.2-bullseye

# Install system dependencies
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get -y install --no-install-recommends default-mysql-client

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring xml tokenizer ctype json curl dom fileinfo session bcmath

# Set working directory
WORKDIR /workspaces/${localWorkspaceFolderBasename}
