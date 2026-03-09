.PHONY: run build up down logs logs-app logs-queue logs-ollama migrate fresh shell

# Avvia tutto l'ambiente Docker (build + up + migrate)
run: build up migrate
	@echo ""
	@echo "Ambiente avviato!"
	@echo "  - App:      http://localhost:8000"
	@echo "  - Postgres: localhost:5432"
	@echo "  - Ollama:   localhost:11434"
	@echo ""
	@echo "Comandi utili:"
	@echo "  make logs       - Tutti i log"
	@echo "  make logs-app   - Log applicazione"
	@echo "  make logs-queue - Log queue worker"
	@echo "  make down       - Ferma tutto"

# Build immagine Docker
build:
	docker compose build

# Avvia i container
up:
	docker compose up -d
	@echo "Attendi il download dei modelli Ollama (prima volta ~5GB)..."
	@echo "Segui il progresso con: make logs-ollama"

# Ferma i container
down:
	docker compose down

# Rimuove tutto (container, volumi, immagini)
clean:
	docker compose down -v --rmi local

# Database
migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

# Shell nel container app
shell:
	docker compose exec app bash

# Logs
logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-queue:
	docker compose logs -f queue

logs-ollama:
	docker compose logs -f ollama-pull
