# ─── Stage 1: Development ─────────────────────────────────────────────────────
# Use slim (Debian/glibc) instead of alpine (musl) — oxc-parser has no
# pre-built native binding for linux-arm64-musl.
FROM node:22-slim AS development

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]

# ─── Stage 2: Builder ─────────────────────────────────────────────────────────
FROM node:22-slim AS builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

RUN npm run build

# ─── Stage 3: Production ──────────────────────────────────────────────────────
FROM node:22-slim AS production

# Docker CLI needed for health-check-log server routes
RUN apt-get update && apt-get install -y docker.io && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=builder /app/.output ./.output

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
