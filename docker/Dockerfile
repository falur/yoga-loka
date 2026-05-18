FROM ubuntu:26.04

ARG TARGETARCH
ARG RR_VERSION_FILTER=2025.*

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV DEBIAN_FRONTEND=noninteractive

WORKDIR /app

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        make \
        unzip \
    ; \
    apt-cache policy php8.5-cli php8.5-redis; \
    apt-get install -y --no-install-recommends \
        php8.5-cli \
        php8.5-curl \
        php8.5-mbstring \
        php8.5-pgsql \
        php8.5-redis \
        php8.5-sockets \
        php8.5-xml \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-intl \
        postgresql-client \
    ; \
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php; \
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer; \
    rm -f /tmp/composer-setup.php; \
    composer global require --no-interaction --no-progress spiral/roadrunner-cli:^2.5; \
    case "${TARGETARCH}" in \
        amd64|arm64) RR_ARCH="${TARGETARCH}" ;; \
        *) echo "Неизвестная архитектура RoadRunner: ${TARGETARCH}" >&2; exit 1 ;; \
    esac; \
    /root/.composer/vendor/bin/rr get-binary \
        --os linux \
        --arch "${RR_ARCH}" \
        --filter "${RR_VERSION_FILTER}" \
        --location /usr/local/bin \
        --plugin http \
        --plugin jobs \
        --plugin kv \
        --plugin temporal \
        --plugin metrics \
        --plugin lock \
        --no-config \
        --no-interaction \
        --quiet; \
    chmod +x /usr/local/bin/rr; \
    php -v; \
    php -m | grep -E '^redis$'; \
    php -m | grep -E '^pdo_pgsql$'; \
    dpkg -l | grep php8.5-redis; \
    rr --version; \
    rr jobs --help >/dev/null; \
    rr workers --help >/dev/null; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/* /tmp/* /root/.cache /root/.composer/cache

CMD ["php", "-v"]
