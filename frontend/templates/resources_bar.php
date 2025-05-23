<?php
// Get player's resources
$resources = null;
$productions = null;
$lastUpdate = time();
$interval = 60; // Production update interval in seconds

// Get player's city and associated resources
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $playerId = $_SESSION['user']['id'];

    // Get player's city
    $query = "SELECT * FROM cities WHERE player_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $playerCity = $result->fetch_assoc();

        // Get resources based on city_id
        $query = "SELECT * FROM resources WHERE city_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerCity['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $resources = $result->fetch_assoc();
        } else {
            // Create new resources for this city
            $query = "INSERT INTO resources (city_id, wood, iron, food, stone, oil, teleports, diamonds, last_update) VALUES (?, 500, 500, 500, 500, 500, 2, 0, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $playerCity['id'], $lastUpdate);
            $stmt->execute();

            // Get the newly created resources
            $query = "SELECT * FROM resources WHERE city_id = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $resources = $result->fetch_assoc();
            }
        }

        // Get productions based on city_id
        $query = "SELECT * FROM productions WHERE city_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerCity['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $productions = $result->fetch_assoc();
        } else {
            // Create new productions for this city
            $query = "INSERT INTO productions (city_id, wood_production, iron_production, food_production, stone_production, oil_production) VALUES (?, 10, 10, 8, 5, 2)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['id']);
            $stmt->execute();

            // Get the newly created productions
            $query = "SELECT * FROM productions WHERE city_id = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $productions = $result->fetch_assoc();
            }
        }

        //Add resource_id to the city if it doesn't exist
        if (!isset($playerCity['resources_id'])) {
            $query = "UPDATE cities SET resources_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $resources['id'], $playerCity['id']);
            $stmt->execute();
        }

        // Update resources based on production rates if we have both resources and productions
        if ($resources && $productions) {
            $currentTime = time();
            $lastUpdate = $resources['last_update'] ?? $currentTime;
            $timeDiff = $currentTime - $lastUpdate;
            $cycles = floor($timeDiff / $interval);

            if ($cycles > 0) {
                $newWood = $resources['wood'] + ($productions['wood_production'] * $cycles);
                $newIron = $resources['iron'] + ($productions['iron_production'] * $cycles);
                $newFood = $resources['food'] + ($productions['food_production'] * $cycles);
                $newStone = $resources['stone'] + ($productions['stone_production'] * $cycles);
                $newOil = $resources['oil'] + ($productions['oil_production'] * $cycles);

                $query = "UPDATE resources SET 
                        wood = ?, 
                        iron = ?, 
                        food = ?,
                        stone = ?,
                        oil = ?,
                        last_update = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iiiiiii", $newWood, $newIron, $newFood, $newStone, $newOil, $currentTime, $resources['id']);
                $stmt->execute();

                // Update local variables
                $resources['wood'] = $newWood;
                $resources['iron'] = $newIron;
                $resources['food'] = $newFood;
                $resources['stone'] = $newStone;
                $resources['oil'] = $newOil;
                $resources['last_update'] = $currentTime;
            }
        }
    }
}
?>

<!-- Resources Bar -->
<div class="resources-bar">
    <div class="resources-bar-container">
        <?php
        $nextUpdate = $resources ? ($resources['last_update'] + $interval) : (time() + $interval);
        $secondsLeft = $nextUpdate - time();
        ?>
        <div class="resources-bar-header">
            <h4>Resources</h4>
            <div class="resource-update-timer" data-seconds-left="<?= $secondsLeft ?>">
                <span class="timer-label">Next update in:</span>
                <span class="timer-value"><?= floor($secondsLeft / 60) ?>:<?= str_pad($secondsLeft % 60, 2, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>
        <div class="resources-bar-items">
            <?php if ($resources): ?>
                <div class="resource-item resource-iron" data-resource="iron">
                    <div class="resource-item-inner">
                        <div class="resource-icon-wrap">
                            <img src="frontend/images/iron.png" alt="Iron" class="resource-icon">
                        </div>
                        <div class="resource-details">
                            <span class="resource-name">Iron</span>
                            <span class="resource-value" data-value="<?= $resources['iron']; ?>"><?= number_format($resources['iron']); ?></span>
                            <div class="resource-progress">
                                <div class="resource-progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="resource-rate">(+<span class="rate-value"><?= $productions['iron_production']; ?></span>/min)</span>
                        </div>
                    </div>
                </div>
                <div class="resource-item resource-wood" data-resource="wood">
                    <div class="resource-item-inner">
                        <div class="resource-icon-wrap">
                            <img src="frontend/images/wood.png" alt="Wood" class="resource-icon">
                        </div>
                        <div class="resource-details">
                            <span class="resource-name">Wood</span>
                            <span class="resource-value" data-value="<?= $resources['wood']; ?>"><?= number_format($resources['wood']); ?></span>
                            <div class="resource-progress">
                                <div class="resource-progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="resource-rate">(+<span class="rate-value"><?= $productions['wood_production']; ?></span>/min)</span>
                        </div>
                    </div>
                </div>
                <div class="resource-item resource-stone" data-resource="stone">
                    <div class="resource-item-inner">
                        <div class="resource-icon-wrap">
                            <img src="frontend/images/stone.png" alt="Stone" class="resource-icon">
                        </div>
                        <div class="resource-details">
                            <span class="resource-name">Stone</span>
                            <span class="resource-value" data-value="<?= $resources['stone']; ?>"><?= number_format($resources['stone']); ?></span>
                            <div class="resource-progress">
                                <div class="resource-progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="resource-rate">(+<span class="rate-value"><?= $productions['stone_production']; ?></span>/min)</span>
                        </div>
                    </div>
                </div>
                <div class="resource-item resource-food" data-resource="food">
                    <div class="resource-item-inner">
                        <div class="resource-icon-wrap">
                            <img src="frontend/images/food.png" alt="Food" class="resource-icon">
                        </div>
                        <div class="resource-details">
                            <span class="resource-name">Food</span>
                            <span class="resource-value" data-value="<?= $resources['food']; ?>"><?= number_format($resources['food']); ?></span>
                            <div class="resource-progress">
                                <div class="resource-progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="resource-rate">(+<span class="rate-value"><?= $productions['food_production']; ?></span>/min)</span>
                        </div>
                    </div>
                </div>
                <div class="resource-item resource-oil" data-resource="oil">
                    <div class="resource-item-inner">
                        <div class="resource-icon-wrap">
                            <img src="frontend/images/oil.png" alt="Oil" class="resource-icon">
                        </div>
                        <div class="resource-details">
                            <span class="resource-name">Oil</span>
                            <span class="resource-value" data-value="<?= $resources['oil']; ?>"><?= number_format($resources['oil']); ?></span>
                            <div class="resource-progress">
                                <div class="resource-progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="resource-rate">(+<span class="rate-value"><?= $productions['oil_production']; ?></span>/min)</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="resource-item resource-iron" data-resource="iron">
                        <div class="resource-item-inner">
                            <div class="resource-icon-wrap">
                                <img src="frontend/images/iron.png" alt="Iron" class="resource-icon">
                            </div>
                            <div class="resource-details">
                                <span class="resource-name">Iron</span>
                                <span class="resource-value" data-value="500">500</span>
                                <div class="resource-progress">
                                    <div class="resource-progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="resource-rate">(+<span class="rate-value">10</span>/min)</span>
                            </div>
                        </div>
                    </div>
                    <div class="resource-item resource-wood" data-resource="wood">
                        <div class="resource-item-inner">
                            <div class="resource-icon-wrap">
                                <img src="frontend/images/wood.png" alt="Wood" class="resource-icon">
                            </div>
                            <div class="resource-details">
                                <span class="resource-name">Wood</span>
                                <span class="resource-value" data-value="500">500</span>
                                <div class="resource-progress">
                                    <div class="resource-progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="resource-rate">(+<span class="rate-value">10</span>/min)</span>
                            </div>
                        </div>
                    </div>
                    <div class="resource-item resource-stone" data-resource="stone">
                        <div class="resource-item-inner">
                            <div class="resource-icon-wrap">
                                <img src="frontend/images/stone.png" alt="Stone" class="resource-icon">
                            </div>
                            <div class="resource-details">
                                <span class="resource-name">Stone</span>
                                <span class="resource-value" data-value="500">500</span>
                                <div class="resource-progress">
                                    <div class="resource-progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="resource-rate">(+<span class="rate-value">5</span>/min)</span>
                            </div>
                        </div>
                    </div>
                    <div class="resource-item resource-food" data-resource="food">
                        <div class="resource-item-inner">
                            <div class="resource-icon-wrap">
                                <img src="frontend/images/food.png" alt="Food" class="resource-icon">
                            </div>
                            <div class="resource-details">
                                <span class="resource-name">Food</span>
                                <span class="resource-value" data-value="500">500</span>
                                <div class="resource-progress">
                                    <div class="resource-progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="resource-rate">(+<span class="rate-value">8</span>/min)</span>
                            </div>
                        </div>
                    </div>
                    <div class="resource-item resource-oil" data-resource="oil">
                        <div class="resource-item-inner">
                            <div class="resource-icon-wrap">
                                <img src="frontend/images/oil.png" alt="Oil" class="resource-icon">
                            </div>
                            <div class="resource-details">
                                <span class="resource-name">Oil</span>
                                <span class="resource-value" data-value="500">500</span>
                                <div class="resource-progress">
                                    <div class="resource-progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="resource-rate">(+<span class="rate-value">2</span>/min)</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
        </div>