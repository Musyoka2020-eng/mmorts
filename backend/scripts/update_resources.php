<?php
// Backend script to update resources based on production rates
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../system/config.php';

// Default response
$response = [
    'success' => false,
    'message' => 'Not logged in',
    'resources' => null,
    'productions' => null
];

// Check if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $playerId = $_SESSION['user']['id'];

    // Get player's city join with productions where city_id matches

    // $query = "SELECT * FROM cities WHERE player_id = ? LIMIT 1";
    $query = "SELECT c.*, p.* FROM cities c 
    JOIN productions p ON c.id = p.city_id 
    WHERE c.player_id = ? LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $playerCity = $result->fetch_assoc();
        //log the player city
        echo json_encode($playerCity);

        // Check if city has resources
        if (isset($playerCity['resources_id']) && isset($playerCity['productions_id'])) {
            // Get resources
            $query = "SELECT * FROM resources WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['resources_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $resources = null;

            if ($result && $result->num_rows === 1) {
                $resources = $result->fetch_assoc();
            } else {
                $response['message'] = 'Resources not found';
                echo json_encode($response);
                exit;
            }

            // Get productions
            $query = "SELECT * FROM productions WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['productions_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $productions = null;

            if ($result && $result->num_rows === 1) {
                $productions = $result->fetch_assoc();
            } else {
                $response['message'] = 'Productions not found';
                echo json_encode($response);
                exit;
            }

            // Update resources based on production rates
            $currentTime = time();
            $lastUpdate = $resources['last_update'] ?? $currentTime;
            $interval = 60; // Production update interval in seconds
            $timeDiff = $currentTime - $lastUpdate;
            $cycles = floor($timeDiff / $interval);

            if ($cycles > 0) {
                $newIron = $resources['iron'] + ($productions['iron_rate'] * $cycles);
                $newOil = $resources['oil'] + ($productions['oil_rate'] * $cycles);
                $newWood = $resources['wood'] + ($productions['wood_rate'] * $cycles);
                $newStone = $resources['stone'] + ($productions['stone_rate'] * $cycles);
                $newFood = $resources['food'] + ($productions['food_rate'] * $cycles);

                $query = "UPDATE resources SET 
                        iron = ?, 
                        oil = ?,
                        wood = ?, 
                        stone = ?, 
                        food = ?,
                        last_update = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iiiiii", $newIron, $newOil, $newWood, $newStone, $newFood, $currentTime, $resources['id']);

                if ($stmt->execute()) {
                    // Update local variables for response
                    $resources['iron'] = $newIron;
                    $resources['oil'] = $newOil;
                    $resources['wood'] = $newWood;
                    $resources['stone'] = $newStone;
                    $resources['food'] = $newFood;
                    $resources['last_update'] = $currentTime;

                    $response['success'] = true;
                    $response['message'] = 'Resources updated successfully';
                    $response['resources'] = $resources;
                    $response['productions'] = $productions;
                } else {
                    $response['message'] = 'Failed to update resources: ' . $conn->error;
                }
            } else {
                // No update needed, but still return current values
                $response['success'] = true;
                $response['message'] = 'No update needed';
                $response['resources'] = $resources;
                $response['productions'] = $productions;
            }
        } else {
            $response['message'] = 'City missing resources or productions IDs';
        }
    } else {
        $response['message'] = 'Player city not found';
    }
} else {
    $response['message'] = 'Not logged in';
}

echo json_encode($response);
