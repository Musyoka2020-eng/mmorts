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
            'fighters' => ['attack' => 65, 'defense' => 55, 'speed' => 70, 'capacity' => 10],
            'shooters' => ['attack' => 80, 'defense' => 40, 'speed' => 60, 'capacity' => 15],
            'vehicles' => ['attack' => 90, 'defense' => 95, 'speed' => 85, 'capacity' => 20],
            'riders' => ['attack' => 75, 'defense' => 65, 'speed' => 95, 'capacity' => 12],
            'canons' => ['attack' => 120, 'defense' => 30, 'speed' => 25, 'capacity' => 5],
            'skirmishers' => ['attack' => 60, 'defense' => 80, 'speed' => 75, 'capacity' => 8],
            'jets' => ['attack' => 150, 'defense' => 60, 'speed' => 200, 'capacity' => 3],
            'archers' => ['attack' => 70, 'defense' => 35, 'speed' => 65, 'capacity' => 10],
            'marauders' => ['attack' => 100, 'defense' => 70, 'speed' => 90, 'capacity' => 5]
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
            'riders' => ['icon' => '🏍️', 'name' => 'Riders', 'category' => 'advanced'],
            'canons' => ['icon' => '💣', 'name' => 'Canons', 'category' => 'advanced'],
            'skirmishers' => ['icon' => '🛡️', 'name' => 'Skirmishers', 'category' => 'special'],
            'jets' => ['icon' => '🛩️', 'name' => 'Jets', 'category' => 'special'],
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
    
    /**
     * Building types configuration
     * Building categories and their display information
     */
    public static function getBuildingCategories() {
        return [
            'resource' => [
                'name' => '🏭 Resource Production',
                'description' => 'Buildings that produce resources',
                'tab' => 'resource'
            ],
            'military' => [
                'name' => '⚔️ Military Facilities',
                'description' => 'Buildings for training and military operations',
                'tab' => 'military'
            ],
            'defense' => [
                'name' => '🛡️ Defensive Structures',
                'description' => 'Buildings that protect your city',
                'tab' => 'defense'
            ],
            'research' => [
                'name' => '🔬 Research Facilities',
                'description' => 'Buildings for technological advancement',
                'tab' => 'research'
            ],
            'special' => [
                'name' => '🏛️ Special Buildings',
                'description' => 'Administrative and unique structures',
                'tab' => 'special'
            ]
        ];
    }
    
    /**
     * Get building information from database
     * @param mysqli $conn Database connection
     * @param string|null $category Filter by category
     * @return array
     */
    public static function getBuildingTypes($conn, $category = null) {
        $whereClause = $category ? "WHERE category = ?" : "";
        $query = "SELECT * FROM building_types $whereClause ORDER BY category, name";
        
        if ($category) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        $buildings = [];
        while ($row = $result->fetch_assoc()) {
            $buildings[] = $row;
        }
        
        return $buildings;
    }
    
    /**
     * Get building effects for a specific building type and level
     * @param mysqli $conn Database connection
     * @param int $buildingTypeId
     * @param int $level
     * @return array
     */
    public static function getBuildingEffects($conn, $buildingTypeId, $level) {
        $query = "SELECT * FROM building_effects 
                 WHERE building_type_id = ? AND level <= ? 
                 ORDER BY level DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $buildingTypeId, $level);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $effects = [];
        $processedEffects = [];
        
        // Get the highest level effect for each effect type/target combination
        while ($row = $result->fetch_assoc()) {
            $key = $row['effect_type'] . '_' . $row['effect_target'];
            if (!isset($processedEffects[$key])) {
                $effects[] = $row;
                $processedEffects[$key] = true;
            }
        }
        
        return $effects;
    }
    
    /**
     * Calculate building cost for a specific level
     * @param array $buildingType Building type data from database
     * @param int $level Target level
     * @return array Cost breakdown
     */
    public static function calculateBuildingCost($buildingType, $level) {
        $baseCosts = [
            'wood' => $buildingType['base_cost_wood'] ?? 0,
            'iron' => $buildingType['base_cost_iron'] ?? 0,
            'stone' => $buildingType['base_cost_stone'] ?? 0,
            'food' => $buildingType['base_cost_food'] ?? 0,
            'oil' => $buildingType['base_cost_oil'] ?? 0
        ];
        
        $multiplier = $buildingType['cost_multiplier'] ?? 1.5;
        $levelMultiplier = pow($multiplier, $level - 1);
        
        $costs = [];
        foreach ($baseCosts as $resource => $baseCost) {
            if ($baseCost > 0) {
                $costs[$resource] = (int)($baseCost * $levelMultiplier);
            }
        }
        
        return $costs;
    }
    
    /**
     * Calculate building construction time for a specific level
     * @param array $buildingType Building type data from database
     * @param int $level Target level
     * @return int Construction time in seconds
     */
    public static function calculateBuildingTime($buildingType, $level) {
        $baseTime = $buildingType['base_construction_time'] ?? 300;
        $multiplier = $buildingType['time_multiplier'] ?? 1.2;
        $levelMultiplier = pow($multiplier, $level - 1);
        
        return (int)($baseTime * $levelMultiplier);
    }
    
    /**
     * Check if building prerequisites are met
     * @param mysqli $conn Database connection
     * @param int $cityId City to check
     * @param int $buildingTypeId Building type to check
     * @return array [bool $met, string $reason]
     */
    public static function checkBuildingPrerequisites($conn, $cityId, $buildingTypeId) {
        // Get building type with prerequisites
        $query = "SELECT * FROM building_types WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $buildingType = $stmt->get_result()->fetch_assoc();
        
        if (!$buildingType) {
            return [false, 'Building type not found'];
        }
        
        // Check if prerequisite building is required
        if ($buildingType['prerequisite_building_id']) {
            $prereqLevel = $buildingType['prerequisite_level'] ?? 1;
            
            $query = "SELECT cb.level FROM city_buildings cb
                     WHERE cb.city_id = ? AND cb.building_type_id = ? 
                     AND cb.level >= ? AND cb.is_active = 1 
                     LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $cityId, $buildingType['prerequisite_building_id'], $prereqLevel);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Get prerequisite building name
                $query = "SELECT display_name FROM building_types WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $buildingType['prerequisite_building_id']);
                $stmt->execute();
                $prereqName = $stmt->get_result()->fetch_assoc()['display_name'];
                
                return [false, "Requires {$prereqName} level {$prereqLevel}"];
            }
        }
        
        return [true, ''];
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
