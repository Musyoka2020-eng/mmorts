# 🎯 MechaEmpire Development Guide

## 📋 **Project Overview**
MechaEmpire is a single-player MMORTS game featuring AI opponents, resource management, city building, and strategic combat. This guide contains all essential information for continued development.

---

## 🔧 **Global Variables System (COMPLETED)**

### **Current Implementation**
Your project now uses a modern, centralized globals management system instead of scattered global variables.

### **How to Use**
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
$isMaintenanceMode = $g->isMaintenanceMode();

// Helper functions
$loginUrl = url('login');
$user = requireLogin(); // Auto-redirects if not logged in
```

### **Key Files**
- `system/globals.php` - Main globals manager
- `system/deprecation.php` - Warning system for old patterns
- `system/routing.php` - Page routing and helper functions
- `migration_test.php` - Test page to verify system

### **Migration Benefits**
- ✅ Centralized configuration management
- ✅ Type-safe variable access
- ✅ Better error handling and debugging
- ✅ Easier testing and maintenance
- ✅ Backwards compatibility maintained

---

## 🎮 **Game Architecture**

### **Core Systems**
1. **User Management** - Authentication, sessions, permissions
2. **Resource Management** - Wood, iron, food, oil, stone production/consumption
3. **City Building** - Construction, upgrades, production facilities
4. **Combat System** - Unit training, battles, AI opponents
5. **World Map** - Navigation, exploration, territory control
6. **AI System** - Computer-controlled opponents with different strategies

### **Database Structure**
Key tables include:
- `users` - Player accounts and authentication
- `cities` - Player and AI settlements
- `resources` - Resource stockpiles for each city
- `productions` - Resource generation rates
- `units` - Military units for each player/AI
- `battles` - Combat history and results
- `ai_players` - AI opponent configurations

---

## 🤖 **AI System**

### **Current Implementation**
- **AI Manager** (`backend/ai/ai_manager.php`) - Controls AI decision making
- **AI Base** (`backend/ai/ai_base.php`) - Base AI player class
- **Battle Manager** (`backend/combat/battle_manager.php`) - Handles combat

### **AI Features**
- Multiple difficulty levels
- Different AI personalities (aggressive, defensive, balanced)
- Resource management and city development
- Military strategy and unit production
- Dynamic decision making based on game state

### **Future Enhancements**
- Campaign missions with storyline
- Achievement system
- Technology trees
- Random world events
- Procedural map generation

---

## 🗺️ **World Map System**

### **Navigation Features (COMPLETED)**
- ✅ Center button properly navigates to player city
- ✅ View toggle buttons (Terrain/Resources/Political) working
- ✅ Stable resource view with optimized animations
- ✅ Debounced interactions for better performance
- ✅ Cross-browser compatibility

### **Map Components**
- Grid-based world map (50x50 default)
- Resource distribution and terrain types
- Player and AI city locations
- Fog of war and exploration mechanics
- Strategic terrain features

---

## 🔧 **Development Practices**

### **Code Structure**
```
system/           # Core system files (globals, config, functions)
frontend/         # User interface (pages, templates, assets)
  pages/         # Individual game pages
  templates/     # Reusable UI components
  design/        # CSS, JS, images
backend/          # Server-side logic
  ai/           # AI system
  combat/       # Battle system
  scripts/      # AJAX endpoints and utilities
  cronjobs/     # Background tasks
  setup/        # Installation and initialization
```

### **Coding Standards**
- Use the new globals system: `$g = globals()`
- Include proper error handling in all functions
- Use prepared statements for database queries
- Escape output in templates: `htmlspecialchars()`
- Follow consistent naming conventions

### **Database Access Pattern**
```php
// Get database connection
$conn = globals()->getDatabase();

// Use prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
```

### **Authentication Pattern**
```php
// Check if user is logged in
$g = globals();
if (!$g->isUserLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

// Get user data
$userId = $g->getCurrentUser('id');
$username = $g->getCurrentUser('uname');
```

---

## 🧪 **Testing & Debugging**

### **Test Resources**
- `migration_test.php` - Verify globals system is working
- Browser developer tools for frontend debugging
- PHP error logs for backend issues
- Database query logs for performance monitoring

### **Common Debugging Steps**
1. Check `migration_test.php` for system status
2. Review PHP error logs
3. Test database connections
4. Verify user authentication flow
5. Check AJAX endpoints with browser network tab

---

## 🚀 **Deployment**

### **Production Setup**
1. Set `DEVELOPMENT_MODE = false` in `system/routing.php`
2. Remove or secure `migration_test.php`
3. Configure proper database credentials in `system/env.ini`
4. Set up proper file permissions
5. Configure web server (Apache/Nginx)

### **Performance Optimization**
- Enable gzip compression
- Optimize database queries with indexes
- Implement caching for frequently accessed data
- Minify CSS/JS assets
- Use CDN for static resources

---

## 📋 **Future Development Roadmap**

### **Missing Critical Systems ❌**
1. **Building System** - No building construction, upgrades, or management
2. **Mail/Messaging System** - No communication system between players and system notifications
3. **Inventory System** - No item management, storage, or equipment system
4. **Campaign System** - No structured missions, storylines, or progression system
5. **Event System** - No dynamic events, random encounters, or special occurrences
6. **Technology Tree** - Research and advancement system for unlocking new capabilities
7. **Achievement System** - Player rewards, goals, and progression tracking

### **Immediate Priorities**
1. **Building System** - Foundation for city development and resource production
2. **Inventory System** - Essential for item management and equipment
3. **Technology Tree** - Research and advancement system
4. **Achievement System** - Player rewards and goals

### **Long-term Goals**
1. **Advanced Combat** - Unit abilities, formation tactics
2. **Diplomacy System** - Trade, alliances, negotiations
3. **World Events** - Random events affecting gameplay
4. **Mod Support** - Allow community modifications

### **Technical Improvements**
1. **API Standardization** - Consistent REST endpoints
2. **Frontend Framework** - Consider modern JS framework
3. **Real-time Updates** - WebSocket implementation
4. **Mobile Support** - Responsive design improvements

---

## 🔍 **Quick Reference**

### **Common Functions**
```php
globals()                    // Get globals instance
getDatabase()               // Get DB connection
requireLogin()              // Force authentication
url($page, $params)         // Generate URLs
getErrorMessage($type)      // Get error messages
```

### **Important Files**
- `index.php` - Main entry point
- `system/includes.php` - System initialization
- `system/config.php` - Database and site configuration
- `frontend/pages/home.php` - Main game interface
- `backend/combat/battle_manager.php` - Combat system

### **Configuration**
- Database settings: `system/env.ini`
- Game constants: `system/game_config.php`
- Development mode: `system/routing.php` (DEVELOPMENT_MODE)

---

## 📞 **Support & Maintenance**

### **Regular Maintenance Tasks**
1. Monitor error logs for issues
2. Update AI difficulty based on player feedback
3. Balance resource production rates
4. Review and optimize database queries
5. Backup save data regularly

### **Troubleshooting Common Issues**
- **Login issues**: Check session configuration and database connectivity
- **AI not working**: Verify AI cron jobs are running
- **Map navigation problems**: Check world map JavaScript console for errors
- **Performance issues**: Review database query logs and optimize slow queries

---

**💡 Remember**: Always use the new globals system (`globals()`) for new development. The old global variable patterns are deprecated but still work with warnings in development mode.

This guide should be sufficient for continued development. Refer back to this document whenever you need to understand the system architecture or development practices.
