FROM composer:1.5

# add a non-root user and give them ownership
RUN adduser -D -u 9000 app && \
    # repo
    mkdir /repo && \
    chown -R app:app /repo && \
    # app code
    mkdir /usr/src/app && \
    chown -R app:app /usr/src/app

# run everything from here on as non-root
USER app

# install requirements first, so we can cache this step
RUN mkdir /usr/src/app/src
ADD src/composer.json /usr/src/app/src
ADD src/composer.lock /usr/src/app/src
RUN cd /usr/src/app/src && composer install

ADD src /usr/src/app/src

WORKDIR /repo

ENTRYPOINT ["php", "/usr/src/app/src/entrypoint.php"]
