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
RUN pnpm build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
