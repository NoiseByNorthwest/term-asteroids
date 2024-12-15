ARG phpImageVer
FROM php:${phpImageVer}

RUN apt-get update \
    && apt-get install -y \
        sudo zlib1g-dev git libffi-dev xterm \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure ffi --with-ffi \
    && docker-php-ext-install ffi

RUN pecl install igbinary-3.2.16 && docker-php-ext-enable igbinary

RUN cd /tmp/ \
    && git clone https://github.com/NoiseByNorthwest/php-spx.git \
    && cd php-spx \
    && phpize && ./configure && make && make install \
    && cd .. && rm -rf php-spx

RUN echo > /usr/local/etc/php/conf.d/opcache.ini \
   && echo 'zend_extension=opcache.so' >> /usr/local/etc/php/conf.d/opcache.ini \
   && echo '[opcache]' >> /usr/local/etc/php/conf.d/opcache.ini \
   && echo 'opcache.enable_cli=1' >> /usr/local/etc/php/conf.d/opcache.ini \
   && echo 'opcache.jit_buffer_size=256M' >> /usr/local/etc/php/conf.d/opcache.ini \
   && echo 'opcache.jit=tracing' >> /usr/local/etc/php/conf.d/opcache.ini

RUN echo > /usr/local/etc/php/conf.d/spx.ini \
   && echo 'extension=spx.so' >> /usr/local/etc/php/conf.d/spx.ini \
   && echo 'spx.http_enabled=1' >> /usr/local/etc/php/conf.d/spx.ini \
   && echo 'spx.http_key="dev"' >> /usr/local/etc/php/conf.d/spx.ini \
   && echo 'spx.http_ip_whitelist="*"' >> /usr/local/etc/php/conf.d/spx.ini

RUN useradd -ms /bin/bash term-asteroids
RUN echo "\nterm-asteroids ALL=(ALL) NOPASSWD: ALL\n" >> /etc/sudoers
USER term-asteroids

WORKDIR /var/www/html
