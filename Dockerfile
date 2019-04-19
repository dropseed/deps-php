FROM composer:1.8.5

RUN apk --no-cache add git wget patch

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app

# add the deps utility to easily create pull requests on different git hosts
WORKDIR /usr/src/app
ENV DEPS_VERSION=2.4.1
RUN wget https://github.com/dependencies-io/deps/releases/download/${DEPS_VERSION}/deps_${DEPS_VERSION}_linux_amd64.tar.gz && \
    mkdir deps && \
    tar -zxvf deps_${DEPS_VERSION}_linux_amd64.tar.gz -C deps && \
    ln -s /usr/src/app/deps/deps /usr/local/bin/deps

RUN git config --global user.email "bot@dependencies.io"
RUN git config --global user.name "Dependencies.io Bot"

# install requirements first, so we can cache this step
RUN mkdir /usr/src/app/src
ADD src/composer.json /usr/src/app/src
ADD src/composer.lock /usr/src/app/src
RUN cd /usr/src/app/src && composer install

ADD src /usr/src/app/src

WORKDIR /repo

ENV COMPOSER_MEMORY_LIMIT=1G

ENTRYPOINT ["php", "/usr/src/app/src/entrypoint.php"]
