<?php
/**
 * Game Configuration - Centralized Game Data Management
 * Single source of truth for all game constants and configurations
 */

class GameConfig {
    
    /**
     * Unit costs configuration
     * Single source of truth for all unit training costs
     */
    public static function getUnitCosts() {
        return [
            'fighters' => ['wood' => 50, 'iron' => 30, 'food' => 20],
            'shooters' => ['wood' => 40, 'iron' => 50, 'food' => 20],
            'vehicles' => ['wood' => 100, 'iron' => 150, 'oil' => 50, 'food' => 30],
            'riders' => ['wood' => 80, 'iron' => 70, 'food' => 40],
            'canons' => ['wood' => 200, 'iron' => 300, 'oil' => 100, 'food' => 50],
            'skirmishers' => ['wood' => 60, 'iron' => 40, 'stone' => 30, 'food' => 25],
            'jets' => ['wood' => 300, 'iron' => 500, 'oil' => 200, 'food' => 80],
            'archers' => ['wood' => 45, 'iron' => 25, 'food' => 15],
            'marauders' => ['wood' => 120, 'iron' => 100, 'oil' => 30, 'food' => 60]
        ];
    }
    
    /**
     * Unit statistics configuration
     * Combat stats for all unit types
     */
    public static function getUnitStats() {
        return [
            'fighters' => ['attack' => 65, 'defense' => 55, 'speed' => 70],
            'shooters' => ['attack' => 80, 'defense' => 40, 'speed' => 60],
            'vehicles' => ['attack' => 90, 'defense' => 95, 'speed' => 85],
            'riders' => ['attack' => 75, 'defense' => 65, 'speed' => 95],
            'canons' => ['attack' => 120, 'defense' => 30, 'speed' => 25],
            'skirmishers' => ['attack' => 60, 'defense' => 80, 'speed' => 75],
            'jets' => ['attack' => 150, 'defense' => 60, 'speed' => 200],
            'archers' => ['attack' => 70, 'defense' => 35, 'speed' => 65],
            'marauders' => ['attack' => 100, 'defense' => 70, 'speed' => 90]
        ];
    }
    
    /**
     * Unit display information
     * Icons, names, and categories for UI presentation
     */
    public static function getUnitDisplay() {
        return [
            'fighters' => ['icon' => '⚔️', 'name' => 'Fighters', 'category' => 'basic'],
            'shooters' => ['icon' => '🏹', 'name' => 'Shooters', 'category' => 'basic'],
            'vehicles' => ['icon' => '🚗', 'name' => 'Vehicles', 'category' => 'advanced'],
            'riders' => ['icon' => '🐎', 'name' => 'Riders', 'category' => 'advanced'],
            'canons' => ['icon' => '💣', 'name' => 'Canons', 'category' => 'advanced'],
            'skirmishers' => ['icon' => '🛡️', 'name' => 'Skirmishers', 'category' => 'special'],
            'jets' => ['icon' => '✈️', 'name' => 'Jets', 'category' => 'special'],
            'archers' => ['icon' => '🏹', 'name' => 'Archers', 'category' => 'special'],
            'marauders' => ['icon' => '⚡', 'name' => 'Marauders', 'category' => 'special']
        ];
    }
    
    /**
     * Unit categories configuration
     * Tab organization for the training interface
     */
    public static function getUnitCategories() {
        return [
            'basic' => ['name' => '⚔️ Infantry Forces', 'tab' => 'basic'],
            'advanced' => ['name' => '🚗 Heavy Units', 'tab' => 'advanced'],
            'special' => ['name' => '⚡ Elite Forces', 'tab' => 'special']
        ];
    }
    
    /**
     * Resource types configuration
     * All available resources in the game
     */
    public static function getResourceTypes() {
        return [
            'wood' => ['name' => 'Wood', 'icon' => '🪵'],
            'iron' => ['name' => 'Iron', 'icon' => '⚙️'],
            'food' => ['name' => 'Food', 'icon' => '🍞'],
            'oil' => ['name' => 'Oil', 'icon' => '🛢️'],
            'stone' => ['name' => 'Stone', 'icon' => '🗿']
        ];
    }
    
    /**
     * Get unit cost for a specific unit type
     * @param string $unitType
     * @return array|null
     */
    public static function getUnitCost($unitType) {
        $costs = self::getUnitCosts();
        return isset($costs[$unitType]) ? $costs[$unitType] : null;
    }
    
    /**
     * Get all unit types
     * @return array
     */
    public static function getAllUnitTypes() {
        return array_keys(self::getUnitCosts());
    }
    
    /**
     * Get units by category
     * @param string $category
     * @return array
     */
    public static function getUnitsByCategory($category) {
        $display = self::getUnitDisplay();
        $units = [];
        
        foreach ($display as $unitType => $info) {
            if ($info['category'] === $category) {
                $units[] = $unitType;
            }
        }
        
        return $units;
    }
    
    /**
     * Generate JavaScript configuration for frontend
     * @return string JSON encoded configuration
     */
    public static function getJavaScriptConfig() {
        return json_encode([
            'unitCosts' => self::getUnitCosts(),
            'unitStats' => self::getUnitStats(),
            'unitDisplay' => self::getUnitDisplay(),
            'unitCategories' => self::getUnitCategories(),
            'resourceTypes' => self::getResourceTypes()
        ], JSON_PRETTY_PRINT);
    }
    
    /**
     * Validate if a unit type exists
     * @param string $unitType
     * @return bool
     */
    public static function isValidUnitType($unitType) {
        return array_key_exists($unitType, self::getUnitCosts());
    }
    
    /**
     * Calculate total resource cost for multiple units
     * @param array $unitQuantities ['unit_type' => quantity, ...]
     * @return array Total resources needed
     */
    public static function calculateTotalCost($unitQuantities) {
        $totalCost = [
            'wood' => 0,
            'iron' => 0,
            'food' => 0,
            'oil' => 0,
            'stone' => 0
        ];
        
        $unitCosts = self::getUnitCosts();
        
        foreach ($unitQuantities as $unitType => $quantity) {
            if (isset($unitCosts[$unitType]) && $quantity > 0) {
                foreach ($unitCosts[$unitType] as $resource => $cost) {
                    $totalCost[$resource] += $cost * $quantity;
                }
            }
        }
        
        return $totalCost;
    }
}

/**
 * Legacy compatibility function
 * For backward compatibility with existing code
 * @deprecated Use GameConfig::getUnitCosts() instead
 */
function getUnitCosts() {
    return GameConfig::getUnitCosts();
}
?>
