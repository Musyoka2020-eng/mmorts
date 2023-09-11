<?php

require __DIR__ . '/../../system/config.php';

$queryResources = "SELECT city_id FROM resources";
$resultResources = $conn->query($queryResources);

while ($rowResources = $resultResources->fetch_assoc()) {
    $cityId = $rowResources['city_id'];

    $queryProduction = "SELECT wood_production, water_production, electricity_production, iron_production, 
        food_production, lime_production, money_production, chips_production, machinery_production 
        FROM productions WHERE city_id = ?";
    $stmtProduction = $conn->prepare($queryProduction);
    $stmtProduction->bind_param('i', $cityId);
    $stmtProduction->execute();
    $resultProduction = $stmtProduction->get_result();
    $rowProduction = $resultProduction->fetch_assoc();

    $queryUpdate = "UPDATE resources SET 
        wood = wood + ?, 
        water = water + ?, 
        electricity = electricity + ?, 
        iron = iron + ?, 
        food = food + ?, 
        lime = lime + ?, 
        money = money + ?, 
        chips = chips + ?, 
        machinery = machinery + ?
        WHERE city_id = ?";
    $stmtUpdate = $conn->prepare($queryUpdate);
    $stmtUpdate->bind_param(
        'dddddddddi',
        $rowProduction['wood_production'],
        $rowProduction['water_production'],
        $rowProduction['electricity_production'],
        $rowProduction['iron_production'],
        $rowProduction['food_production'],
        $rowProduction['lime_production'],
        $rowProduction['money_production'],
        $rowProduction['chips_production'],
        $rowProduction['machinery_production'],
        $cityId
    );
    $stmtUpdate->execute();

    if ($stmtUpdate->affected_rows > 0) {
        echo "Cities' resources have been updated for city with ID $cityId.<br/>";
    }
}
