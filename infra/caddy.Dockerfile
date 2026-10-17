# Edge: Caddy con la SPA (portale-web) già buildata dentro /srv/web.
# Build context = root del repo:  docker build -f infra/caddy.Dockerfile .

# ---- stage 1: build della SPA ----
FROM node:22-alpine AS web
ARG API_DOMAIN=api.localhost
WORKDIR /app
COPY portale-web/package.json portale-web/package-lock.json ./
RUN npm ci
COPY portale-web/ .
# la SPA chiama l'API sul dominio dedicato (cross-origin, Bearer token + CORS)
ENV VITE_API_BASE_URL=https://${API_DOMAIN}/api
RUN npm run build

# ---- stage 2: edge ----
FROM caddy:2-alpine
COPY infra/Caddyfile /etc/caddy/Caddyfile
COPY --from=web /app/dist /srv/web
