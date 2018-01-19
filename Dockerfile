FROM composer:1.5

RUN apk --no-cache add git

# add a non-root user and give them ownership
RUN adduser -D -u 9000 app && \
    # repo
    mkdir /repo && \
    chown -R app:app /repo && \
    # app code
    mkdir /usr/src/app && \
    chown -R app:app /usr/src/app

# add the pullrequest utility to easily create pull requests on different git hosts
WORKDIR /usr/src/app
ENV PULLREQUEST_VERSION=2.0.0-alpha.11
ADD https://github.com/dependencies-io/pullrequest/releases/download/${PULLREQUEST_VERSION}/pullrequest_${PULLREQUEST_VERSION}_linux_amd64.tar.gz .
RUN mkdir pullrequest && \
    tar -zxvf pullrequest_${PULLREQUEST_VERSION}_linux_amd64.tar.gz -C pullrequest && \
    ln -s /usr/src/app/pullrequest/pullrequest /usr/local/bin/pullrequest

# run everything from here on as non-root
USER app

RUN git config --global user.email "bot@dependencies.io"
RUN git config --global user.name "Dependencies.io Bot"

# install requirements first, so we can cache this step
RUN mkdir /usr/src/app/src
ADD src/composer.json /usr/src/app/src
ADD src/composer.lock /usr/src/app/src
RUN cd /usr/src/app/src && composer install

ADD src /usr/src/app/src

WORKDIR /repo

ENTRYPOINT ["php", "/usr/src/app/src/entrypoint.php"]
