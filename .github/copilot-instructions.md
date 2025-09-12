# MechaEmpire Copilot Instructions

## Project Overview
MechaEmpire is a single-player MMORTS (Massively Multiplayer Online Real-Time Strategy) game built with PHP and MySQL. The game features AI opponents, resource management, city building, and strategic combat.

## Communication Protocol
**ALWAYS explain your actions before executing them:**
1. **Break down** what you're about to do and why
2. **List the steps** you'll take in order
3. **Explain the reasoning** behind your approach
4. **Wait for confirmation** for major changes or when uncertain
5. **Report results** after completing actions

## Testing & Cleanup Protocol
**Test files MUST be cleaned up after use:**
- Never leave temporary test files in the repository
- Always delete test files, debug scripts, or experimental code after completion
- Use meaningful temporary names (prefix with `temp_` or `test_`) during development
- Clean up immediately after testing, not at the end of the session
- Exception: Only keep test files if explicitly requested by the user

## Key Architecture Patterns

### Centralized Globals System
**Use the new globals system instead of scattered global variables**:
```php
// Get the globals instance
$g = globals();

// Database access
$conn = $g->getDatabase();

// User authentication
if ($g->isUserLoggedIn()) {
    $username = $g->getCurrentUser('uname');
    $userId = $g->getCurrentUser('id');
}

// Site configuration
$title = $g->getSiteConfig('title');
$baseUrl = $g->getBaseUrl();
```
- **Key file**: `system/globals.php` - Main globals manager
- **Migration**: The project is migrating from old global variables to this centralized system
- **Warning**: `system/deprecation.php` tracks deprecated global usage

### File Structure & Organization
```
system/          # Core system files (config, routing, globals)
backend/         # Business logic organized by feature
  account/       # Authentication & user management
  ai/           # AI opponent system
  buildings/    # Building management
  combat/       # Battle system
  scripts/      # API endpoints and AJAX handlers
frontend/        # UI layer
  pages/        # Full page views
  templates/    # Reusable UI components (header, footer)
  design/       # CSS, JS, images
```

### Database Integration
- **Connection**: Always use `$g->getDatabase()` through globals
- **Pattern**: Use prepared statements for all database queries
- **Tables**: Key tables include `users`, `cities`, `resources`, `productions`, `units`, `battles`, `ai_players`

### Routing System
- **Entry point**: `index.php` → `system/routing.php` → `getPage()` function
- **URL pattern**: `?page=pagename` maps to `frontend/pages/pagename.php`
- **Helper**: Use `url('pagename')` function for generating URLs
- **Authentication**: Use `requireLogin()` helper for protected pages

### Game Configuration
- **Central config**: `system/game_config.php` contains all game constants
- **JavaScript bridge**: `game_config.js.php` exposes config to frontend
- **Unit costs/stats**: Centralized in `GameConfig::getUnitCosts()` and `GameConfig::getUnitStats()`
- **Environment**: Database credentials in `system/env.ini`

## Development Workflows

### Adding New Features
1. **Backend logic**: Add to appropriate `backend/` subdirectory
2. **API endpoints**: Use `backend/scripts/` for AJAX endpoints  
3. **Frontend pages**: Add to `frontend/pages/`
4. **Routing**: Update `system/routing.php` switch statement
5. **Database**: Use globals system for connections

### AI System Integration  
- **AI Manager**: `backend/ai/ai_manager.php` controls all AI operations
- **AI Base**: `backend/ai/ai_base.php` contains core AI player logic
- **Personalities**: AI supports aggressive, balanced, defensive types
- **Cronjobs**: `backend/cronjobs/ai_turns.php` processes AI moves

### Building System
- **Manager**: `backend/buildings/building_manager.php` handles construction
- **Queue system**: Supports timed construction with queue processing
- **Resource validation**: Automatically checks costs before building
- **Database**: Uses `building_types`, `city_buildings` tables

## Code Conventions

### Template Pattern
```php
// Page structure
include_once __DIR__ . '/../templates/header.php';
include_once __DIR__ . '/../templates/topnav.php';
// Page content here
include_once __DIR__ . '/../templates/footer.php';
```

### AJAX API Pattern
```php
// In backend/scripts/
$g = globals();
$conn = $g->getDatabase();

if ($g->isUserLoggedIn()) {
    $userId = $g->getCurrentUser('id');
    // Process request
    echo json_encode(['success' => true, 'data' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
}
```

### Error Handling
- Use `error_log()` for debugging
- Return JSON responses for AJAX endpoints
- Implement proper SQL error handling with prepared statements

## Testing & Debugging
- **Test page**: `migration_test.php` verifies globals system
- **Logs**: Check `logs/deprecation_warnings.log` for old pattern usage
- **Development mode**: Set `DEVELOPMENT_MODE = true` in routing.php for extra warnings

## Critical Dependencies
- **Bootstrap 5**: Frontend UI framework
- **Font Awesome**: Icon system
- **Custom CSS**: Gaming-themed styles in `frontend/design/css/`
- **JavaScript**: Modular approach with feature-specific JS files