# Grewire

Grewire is a private, invite-only communication platform inspired by Discord.

## Build order

1. PHP foundation
2. PostgreSQL persistence
3. Servers and channels
4. Text messaging
5. WebSocket real-time updates
6. Presence
7. WebRTC voice/video
8. Screen sharing
9. File uploads
10. Permissions and roles
11. Accounts and authentication
12. Production hardening and deployment

## Current state

The initial foundation includes PHP bootstrap, PDO PostgreSQL access, the first database schema, a development identity, a browser UI shell, and a database health endpoint.

## Local development

Copy `.env.example` to `.env`, create PostgreSQL database `grewire`, apply the migration, then run:

    php -S localhost:8080 -t public

Open http://localhost:8080.

Example database setup:

    CREATE USER grewire WITH PASSWORD 'change-me';
    CREATE DATABASE grewire OWNER grewire;

Apply the schema:

    psql -U grewire -d grewire -f database/migrations/0001_initial.sql

Authentication is deliberately disabled during early development. Never commit `.env` or real credentials.

## Architecture

PHP handles HTTP/API work. PostgreSQL is the persistent store. WebSockets will handle real-time events and presence. WebRTC will carry audio, video and screen sharing.
