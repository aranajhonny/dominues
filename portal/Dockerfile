# ---- Dominues portal (Vue 3) build → nginx ----

# Stage 1: build SPA
FROM node:20-alpine AS build
WORKDIR /app
ARG VITE_API_URL=http://localhost:8000/public
ARG VITE_GAME_URL=http://localhost:8081
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_GAME_URL=$VITE_GAME_URL

COPY package.json package-lock.json* ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: serve
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]