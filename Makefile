# ==============================================================================
# Al-Sabiqoon - Makefile
# ==============================================================================
# Convenience commands for development and deployment.
#
# Usage:
#   make help          # Show all available commands
#   make up            # Start all services
#   make down          # Stop all services
#   make test          # Run all tests
#
# Requirements:
#   - Docker Desktop 4.x+ (includes Docker Compose v2)
#   - Or: Docker Engine + Docker Compose plugin
#
# References:
#   - https://docs.docker.com/compose/
#   - https://laravel.com/docs/12.x/octane
# ==============================================================================

.PHONY: help up down build rebuild logs shell-backend shell-frontend test test-coverage lint format migrate fresh seed horizon check-docker \
        artisan tinker pail routes cache-clear cache-all restart ps logs-backend logs-frontend logs-horizon \
        model controller request resource migration seeder factory middleware policy event listener job mail notification rule command test-make \
        v1-controller v1-request v1-resource v1-crud \
        shell-mysql shell-redis install setup k8s-staging k8s-production k8s-status build-backend build-frontend build-all clean prune reset

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[34m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
NC := \033[0m # No Color

# ==============================================================================
# Docker Compose Detection
# ==============================================================================
# Detect Docker Compose v2 (docker compose) vs v1 (docker-compose)
# Docker Compose v2 is the modern Go-based version integrated into Docker CLI
# ==============================================================================

DOCKER_COMPOSE := $(shell if docker compose version > /dev/null 2>&1; then echo "docker compose"; elif docker-compose version > /dev/null 2>&1; then echo "docker-compose"; else echo ""; fi)

ifeq ($(DOCKER_COMPOSE),)
$(error Docker Compose is not installed. Please install Docker Desktop or Docker Compose plugin.)
endif

# ==============================================================================
# Help
# ==============================================================================

help: ## Show this help message
	@echo ""
	@echo "$(BLUE)Al-Sabiqoon - Development Commands$(NC)"
	@echo ""
	@echo "Using: $(GREEN)$(DOCKER_COMPOSE)$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""

# ==============================================================================
# Docker Commands
# ==============================================================================

check-docker: ## Check Docker and Docker Compose versions
	@echo "$(BLUE)Docker Version:$(NC)"
	@docker --version
	@echo ""
	@echo "$(BLUE)Docker Compose Version:$(NC)"
	@$(DOCKER_COMPOSE) version
	@echo ""
	@echo "$(GREEN)Using command: $(DOCKER_COMPOSE)$(NC)"

up: ## Start all services in detached mode
	$(DOCKER_COMPOSE) up -d
	@echo ""
	@echo "$(GREEN)Services started!$(NC)"
	@echo "  Frontend: http://localhost:5173"
	@echo "  Backend:  http://localhost:8000"
	@echo "  Mailpit:  http://localhost:8026"
	@echo "  MySQL:    localhost:3307"

down: ## Stop all services
	$(DOCKER_COMPOSE) down
	@echo "$(YELLOW)Services stopped$(NC)"

build: ## Build all Docker images
	$(DOCKER_COMPOSE) build
	@echo "$(GREEN)Build complete$(NC)"

rebuild: ## Rebuild all Docker images without cache
	$(DOCKER_COMPOSE) build --no-cache
	@echo "$(GREEN)Rebuild complete$(NC)"

restart: ## Restart all services
	$(DOCKER_COMPOSE) restart
	@echo "$(GREEN)Services restarted$(NC)"

ps: ## Show running containers
	$(DOCKER_COMPOSE) ps

logs: ## View logs from all services
	$(DOCKER_COMPOSE) logs -f

logs-backend: ## View backend logs only
	$(DOCKER_COMPOSE) logs -f backend

logs-frontend: ## View frontend logs only
	$(DOCKER_COMPOSE) logs -f frontend

logs-horizon: ## View Horizon worker logs
	$(DOCKER_COMPOSE) logs -f horizon

# ==============================================================================
# Shell Access
# ==============================================================================

shell-backend: ## Open shell in backend container
	$(DOCKER_COMPOSE) exec backend sh

shell-frontend: ## Open shell in frontend container
	$(DOCKER_COMPOSE) exec frontend sh

shell-mysql: ## Open MySQL CLI
	$(DOCKER_COMPOSE) exec mysql mysql -u alsabiqoon -psecret alsabiqoon

shell-redis: ## Open Redis CLI
	$(DOCKER_COMPOSE) exec redis redis-cli

# ==============================================================================
# Backend Commands
# ==============================================================================

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	$(DOCKER_COMPOSE) exec backend php artisan $(cmd)

migrate: ## Run database migrations
	$(DOCKER_COMPOSE) exec backend php artisan migrate
	@echo "$(GREEN)Migrations complete$(NC)"

fresh: ## Fresh migrate with seeders
	$(DOCKER_COMPOSE) exec backend php artisan migrate:fresh --seed
	@echo "$(GREEN)Fresh migration complete$(NC)"

seed: ## Run database seeders
	$(DOCKER_COMPOSE) exec backend php artisan db:seed
	@echo "$(GREEN)Seeding complete$(NC)"

horizon: ## Start Horizon in foreground (for debugging)
	$(DOCKER_COMPOSE) exec backend php artisan horizon

tinker: ## Open Laravel Tinker
	$(DOCKER_COMPOSE) exec backend php artisan tinker

pail: ## Stream Laravel logs with Pail
	$(DOCKER_COMPOSE) exec backend php artisan pail

routes: ## List all routes
	$(DOCKER_COMPOSE) exec backend php artisan route:list

cache-clear: ## Clear all Laravel caches
	$(DOCKER_COMPOSE) exec backend php artisan config:clear
	$(DOCKER_COMPOSE) exec backend php artisan cache:clear
	$(DOCKER_COMPOSE) exec backend php artisan route:clear
	$(DOCKER_COMPOSE) exec backend php artisan view:clear
	@echo "$(GREEN)All caches cleared$(NC)"

cache-all: ## Cache config, routes, views, events (production)
	$(DOCKER_COMPOSE) exec backend php artisan config:cache
	$(DOCKER_COMPOSE) exec backend php artisan route:cache
	$(DOCKER_COMPOSE) exec backend php artisan view:cache
	$(DOCKER_COMPOSE) exec backend php artisan event:cache
	@echo "$(GREEN)All caches built$(NC)"

# ==============================================================================
# Artisan Generators (Shortcuts)
# ==============================================================================
# Usage examples:
#   make model name=User                    # php artisan make:model User
#   make model name=Post flags="-mc"        # php artisan make:model Post -mc
#   make model name=Order flags="-mcf"      # php artisan make:model Order -mcf
#   make controller name=UserController     # php artisan make:controller UserController
#   make request name=StoreUserRequest      # php artisan make:request StoreUserRequest
#   make resource name=UserResource         # php artisan make:resource UserResource
# ==============================================================================

model: ## Create model (usage: make model name=User flags="-mc")
	$(DOCKER_COMPOSE) exec backend php artisan make:model $(name) $(flags)
	@echo "$(GREEN)Model created: $(name)$(NC)"

controller: ## Create controller (usage: make controller name=UserController)
	$(DOCKER_COMPOSE) exec backend php artisan make:controller $(name) $(flags)
	@echo "$(GREEN)Controller created: $(name)$(NC)"

request: ## Create form request (usage: make request name=StoreUserRequest)
	$(DOCKER_COMPOSE) exec backend php artisan make:request $(name) $(flags)
	@echo "$(GREEN)Request created: $(name)$(NC)"

resource: ## Create API resource (usage: make resource name=UserResource)
	$(DOCKER_COMPOSE) exec backend php artisan make:resource $(name) $(flags)
	@echo "$(GREEN)Resource created: $(name)$(NC)"

migration: ## Create migration (usage: make migration name=create_users_table)
	$(DOCKER_COMPOSE) exec backend php artisan make:migration $(name) $(flags)
	@echo "$(GREEN)Migration created: $(name)$(NC)"

seeder: ## Create seeder (usage: make seeder name=UserSeeder)
	$(DOCKER_COMPOSE) exec backend php artisan make:seeder $(name)
	@echo "$(GREEN)Seeder created: $(name)$(NC)"

factory: ## Create factory (usage: make factory name=UserFactory)
	$(DOCKER_COMPOSE) exec backend php artisan make:factory $(name) $(flags)
	@echo "$(GREEN)Factory created: $(name)$(NC)"

middleware: ## Create middleware (usage: make middleware name=EnsureAdmin)
	$(DOCKER_COMPOSE) exec backend php artisan make:middleware $(name)
	@echo "$(GREEN)Middleware created: $(name)$(NC)"

policy: ## Create policy (usage: make policy name=UserPolicy)
	$(DOCKER_COMPOSE) exec backend php artisan make:policy $(name) $(flags)
	@echo "$(GREEN)Policy created: $(name)$(NC)"

event: ## Create event (usage: make event name=UserCreated)
	$(DOCKER_COMPOSE) exec backend php artisan make:event $(name)
	@echo "$(GREEN)Event created: $(name)$(NC)"

listener: ## Create listener (usage: make listener name=SendWelcomeEmail)
	$(DOCKER_COMPOSE) exec backend php artisan make:listener $(name) $(flags)
	@echo "$(GREEN)Listener created: $(name)$(NC)"

job: ## Create job (usage: make job name=ProcessPayment)
	$(DOCKER_COMPOSE) exec backend php artisan make:job $(name)
	@echo "$(GREEN)Job created: $(name)$(NC)"

mail: ## Create mailable (usage: make mail name=WelcomeMail)
	$(DOCKER_COMPOSE) exec backend php artisan make:mail $(name) $(flags)
	@echo "$(GREEN)Mailable created: $(name)$(NC)"

notification: ## Create notification (usage: make notification name=OrderShipped)
	$(DOCKER_COMPOSE) exec backend php artisan make:notification $(name)
	@echo "$(GREEN)Notification created: $(name)$(NC)"

rule: ## Create validation rule (usage: make rule name=ValidPhone)
	$(DOCKER_COMPOSE) exec backend php artisan make:rule $(name) $(flags)
	@echo "$(GREEN)Rule created: $(name)$(NC)"

command: ## Create artisan command (usage: make command name=CleanupOldRecords)
	$(DOCKER_COMPOSE) exec backend php artisan make:command $(name)
	@echo "$(GREEN)Command created: $(name)$(NC)"

test-make: ## Create test (usage: make test-make name=UserTest)
	$(DOCKER_COMPOSE) exec backend php artisan make:test $(name) $(flags)
	@echo "$(GREEN)Test created: $(name)$(NC)"

# ==============================================================================
# V1 API Generators (Recommended for new features)
# ==============================================================================
# These create files in the versioned API structure:
#   - Controllers: app/Http/Controllers/Api/V1/
#   - Requests:    app/Http/Requests/Api/V1/
#   - Resources:   app/Http/Resources/Api/V1/
# ==============================================================================

v1-controller: ## Create V1 API controller (usage: make v1-controller name=UserController)
	$(DOCKER_COMPOSE) exec backend php artisan make:controller Api/V1/$(name) $(flags)
	@echo "$(GREEN)V1 Controller created: Api/V1/$(name)$(NC)"
	@echo "$(YELLOW)Remember to extend App\\Http\\Controllers\\Api\\V1\\Controller$(NC)"

v1-request: ## Create V1 API request (usage: make v1-request name=StoreUserRequest)
	$(DOCKER_COMPOSE) exec backend php artisan make:request Api/V1/$(name)
	@echo "$(GREEN)V1 Request created: Api/V1/$(name)$(NC)"
	@echo "$(YELLOW)Remember to extend App\\Http\\Requests\\Api\\V1\\ApiRequest$(NC)"

v1-resource: ## Create V1 API resource (usage: make v1-resource name=UserResource)
	$(DOCKER_COMPOSE) exec backend php artisan make:resource Api/V1/$(name)
	@echo "$(GREEN)V1 Resource created: Api/V1/$(name)$(NC)"
	@echo "$(YELLOW)Remember to extend App\\Http\\Resources\\Api\\V1\\ApiResource$(NC)"

v1-crud: ## Create V1 CRUD (controller + requests + resource) (usage: make v1-crud name=User)
	$(DOCKER_COMPOSE) exec backend php artisan make:controller Api/V1/$(name)Controller --api
	$(DOCKER_COMPOSE) exec backend php artisan make:request Api/V1/Store$(name)Request
	$(DOCKER_COMPOSE) exec backend php artisan make:request Api/V1/Update$(name)Request
	$(DOCKER_COMPOSE) exec backend php artisan make:resource Api/V1/$(name)Resource
	@echo "$(GREEN)V1 CRUD created for: $(name)$(NC)"
	@echo "  Controller: Api/V1/$(name)Controller"
	@echo "  Requests:   Api/V1/Store$(name)Request, Update$(name)Request"
	@echo "  Resource:   Api/V1/$(name)Resource"
	@echo "$(YELLOW)Remember to update parent classes to use V1 base classes$(NC)"

# ==============================================================================
# Testing
# ==============================================================================

test: ## Run all tests
	$(DOCKER_COMPOSE) exec backend php artisan test
	@echo "$(GREEN)All tests passed$(NC)"

test-unit: ## Run unit tests only
	$(DOCKER_COMPOSE) exec backend php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	$(DOCKER_COMPOSE) exec backend php artisan test --testsuite=Feature

test-coverage: ## Run tests with coverage report
	$(DOCKER_COMPOSE) exec backend php artisan test --coverage
	@echo "$(GREEN)Coverage report generated in storage/coverage/$(NC)"

test-filter: ## Run specific test (usage: make test-filter name="UserTest")
	$(DOCKER_COMPOSE) exec backend php artisan test --filter=$(name)

# ==============================================================================
# Code Quality
# ==============================================================================

lint: ## Run PHP linting (Laravel Pint)
	$(DOCKER_COMPOSE) exec backend ./vendor/bin/pint
	@echo "$(GREEN)Linting complete$(NC)"

lint-check: ## Check PHP linting without fixing
	$(DOCKER_COMPOSE) exec backend ./vendor/bin/pint --test

format: ## Format frontend code (Prettier)
	$(DOCKER_COMPOSE) exec frontend npm run format
	@echo "$(GREEN)Formatting complete$(NC)"

lint-frontend: ## Lint frontend code (ESLint)
	$(DOCKER_COMPOSE) exec frontend npm run lint
	@echo "$(GREEN)Frontend linting complete$(NC)"

# ==============================================================================
# Installation & Setup
# ==============================================================================

install: ## Install all dependencies
	$(DOCKER_COMPOSE) exec backend composer install
	$(DOCKER_COMPOSE) exec frontend npm install
	@echo "$(GREEN)Dependencies installed$(NC)"

setup: up ## Full setup: start services, install deps, run migrations
	@echo "$(YELLOW)Waiting for services to start...$(NC)"
	@sleep 10
	$(DOCKER_COMPOSE) exec backend composer install
	$(DOCKER_COMPOSE) exec backend php artisan key:generate --force
	$(DOCKER_COMPOSE) exec backend php artisan migrate --force
	$(DOCKER_COMPOSE) exec frontend npm install
	@echo ""
	@echo "$(GREEN)Setup complete!$(NC)"
	@echo "  Frontend: http://localhost:5173"
	@echo "  Backend:  http://localhost:8000"
	@echo "  Mailpit:  http://localhost:8026"
	@echo "  MySQL:    localhost:3307"

# ==============================================================================
# Kubernetes
# ==============================================================================

k8s-staging: ## Deploy to staging (K8s)
	kubectl apply -k infrastructure/kubernetes/overlays/staging
	@echo "$(GREEN)Deployed to staging$(NC)"

k8s-production: ## Deploy to production (K8s) - BE CAREFUL!
	@echo "$(RED)Are you sure you want to deploy to production?$(NC)"
	@read -p "Type 'yes' to continue: " confirm && [ "$$confirm" = "yes" ] || exit 1
	kubectl apply -k infrastructure/kubernetes/overlays/production
	@echo "$(GREEN)Deployed to production$(NC)"

k8s-status: ## Show K8s deployment status
	kubectl get all -n alsabiqoon

# ==============================================================================
# Docker Build (Production Images)
# ==============================================================================

build-backend: ## Build production backend image
	docker build -t alsabiqoon/backend:latest -f infrastructure/docker/backend/Dockerfile ./backend
	@echo "$(GREEN)Backend image built$(NC)"

build-frontend: ## Build production frontend image
	docker build -t alsabiqoon/frontend:latest -f infrastructure/docker/frontend/Dockerfile ./frontend
	@echo "$(GREEN)Frontend image built$(NC)"

build-all: build-backend build-frontend ## Build all production images
	@echo "$(GREEN)All production images built$(NC)"

# ==============================================================================
# Cleanup
# ==============================================================================

clean: ## Remove all containers, volumes, and networks
	$(DOCKER_COMPOSE) down -v --remove-orphans
	@echo "$(YELLOW)Cleanup complete$(NC)"

prune: ## Remove unused Docker resources
	docker system prune -f
	@echo "$(YELLOW)Docker pruned$(NC)"

reset: clean ## Full reset: remove everything and rebuild
	$(DOCKER_COMPOSE) build --no-cache
	@echo "$(GREEN)Reset complete. Run 'make setup' to start fresh.$(NC)"
