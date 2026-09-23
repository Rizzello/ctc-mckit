FROM node:24.14.0-alpine AS frontend

WORKDIR /var/www/html/frontend

RUN npm install --global npm@11.12.0

COPY frontend/package.json frontend/package-lock.json ./

RUN npm ci --ignore-scripts

COPY frontend ./

RUN npm run postinstall && npm run build:pwa

FROM nginxinc/nginx-unprivileged:1.29-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /var/www/html/frontend/dist/pwa /var/www/html/public

EXPOSE 8080
