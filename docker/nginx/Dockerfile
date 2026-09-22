FROM node:26-alpine AS frontend

WORKDIR /var/www/html/frontend

COPY frontend/package.json frontend/package-lock.json ./

RUN npm ci --ignore-scripts

COPY frontend ./

RUN npm run postinstall && npm run build:pwa

FROM nginxinc/nginx-unprivileged:1.29-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /var/www/html/frontend/dist/pwa /var/www/html/public

EXPOSE 8080
