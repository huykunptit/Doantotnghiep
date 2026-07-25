# ─── Stage 1: Development (docker compose target: development) ───────────────
# node:slim (glibc) — avoid alpine/musl native binding issues on some arches.
FROM node:22-slim AS development

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY . .

ENV HOST=0.0.0.0
ENV PORT=3000
EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]

# ─── Stage 2: Builder ────────────────────────────────────────────────────────
FROM node:22-slim AS builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY . .

ARG NUXT_PUBLIC_API_BASE=/api
ENV NUXT_PUBLIC_API_BASE=$NUXT_PUBLIC_API_BASE

RUN npm run build

# ─── Stage 3: Production ─────────────────────────────────────────────────────
FROM node:22-slim AS production

WORKDIR /app

COPY --from=builder /app/.output ./.output

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
