FROM node:22-alpine AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./

RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

RUN npm run build

FROM nginxinc/nginx-unprivileged:1.29-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /var/www/html/public /var/www/html/public

EXPOSE 8080
