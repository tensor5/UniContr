# Stage 1: build Angular
FROM node:22-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build -- --configuration=production

# Stage 2: serve with nginx
FROM nginx:alpine

COPY --from=builder /app/dist/UniContract-FrontEnd/browser/it /usr/share/nginx/frontend
