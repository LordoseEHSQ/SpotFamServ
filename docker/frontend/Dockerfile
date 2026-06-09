# Web-Image: SPA (frontend/dist) + nginx-Config in einem Image.
# Build-Context = Repo-Root (braucht frontend/ UND docker/nginx/default.conf).
#
# arm64 ohne QEMU-Strafe: Die Build-Stage wird per --platform=$BUILDPLATFORM auf die
# Runner-Arch (amd64) gepinnt -> Node/Vite laeuft IMMER nativ. Die Runtime-Stage enthaelt
# bewusst KEIN RUN; die arm64-Variante ist nur nginx:alpine(arm64) + arch-neutrale
# COPY-Schichten (statische JS/CSS/HTML, dist ist arch-unabhaengig).

FROM --platform=$BUILDPLATFORM node:22-alpine AS build
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@10 --activate
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY frontend/ ./
# Release-Version in den Build geben (CI: --build-arg APP_VERSION=<git-tag>).
# Vite bevorzugt APP_VERSION vor package.json -> Footer zeigt den echten Tag (L-016).
ARG APP_VERSION=""
# Schreibe Datei (nicht /dev/null) damit BuildKit-Layer-Hash sich bei APP_VERSION-Wechsel
# tatsächlich ändert und alle nachfolgenden Layer (pnpm build) cache-invalidiert werden.
RUN echo "$APP_VERSION" > .app_version_stamp
ENV APP_VERSION=$APP_VERSION
RUN pnpm build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
