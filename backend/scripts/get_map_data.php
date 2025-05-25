<?php
// File: get_map_data.php - Provides map data for AJAX requests

// Include necessary files
require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../world/map_generator.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if coordinates are provided
if (!isset($_GET['x']) || !isset($_GET['y'])) {
    echo json_encode(['error' => 'Missing coordinates']);
    exit;
}

// Get coordinates
$playerX = intval($_GET['x']);
$playerY = intval($_GET['y']);

// Get custom view radius if provided
$viewRadiusX = isset($_GET['radiusX']) ? intval($_GET['radiusX']) : 8; // Default horizontal radius
$viewRadiusY = isset($_GET['radiusY']) ? intval($_GET['radiusY']) : 5; // Default vertical radius

// Initialize map generator
$mapGenerator = new MapGenerator($conn);

// Check if coordinates are valid for the map size
$mapSize = $mapGenerator->getMapSize();
if ($playerX < 0 || $playerX >= $mapSize || $playerY < 0 || $playerY >= $mapSize) {
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Get map data for the area around the player with different X/Y radiuses
$mapData = $mapGenerator->getMapArea($playerX, $playerY, null, $viewRadiusX, $viewRadiusY);

// Convert map data to a format suitable for the client
$response = [
    'coordinates' => [
        'x' => $playerX,
        'y' => $playerY
    ],
    'mapSize' => [
        'width' => ($viewRadiusX * 2) + 1,
        'height' => ($viewRadiusY * 2) + 1
    ],
    'tiles' => []
];

// Process map data
foreach ($mapData as $tile) {
    $tileData = [
        'x' => $tile['location_x'],
        'y' => $tile['location_y'],
        'terrain' => $tile['terrain_type'],
        'occupied' => (bool)$tile['occupied'],
        'occupierType' => $tile['occupier_type'],
        'resource' => null
    ];
    
    // Add resource data if present
    if (!empty($tile['resource_type'])) {
        $tileData['resource'] = [
            'type' => $tile['resource_type'],
            'amount' => $tile['resource_amount']
        ];
    }
    
    // Add city data if occupied
    if ($tile['occupied']) {
        $tileData['city'] = [
            'name' => $tile['city_name'] ?? 'Unknown City',
            'owner' => $tile['occupier_name'] ?? 'Unknown'
        ];
    }
    
    $response['tiles'][] = $tileData;
}

// Return JSON data
echo json_encode($response);
?>
