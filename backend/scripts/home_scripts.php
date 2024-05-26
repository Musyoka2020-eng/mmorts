<?php

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $query = "SELECT c.id, c.name, c.resources_id, r.wood, r.oil, r.iron, r.food, r.stone, p.wood_production, p.oil_production, p.iron_production, p.food_production, p.stone_production FROM cities c
        JOIN resources r ON c.resources_id = r.id
        JOIN productions p ON c.id = p.city_id
        WHERE c.player_id = ?;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        $cityId = $row['id'];
        $cityName = $row['name'];
        $cityResId = $row['resources_id'];
        // $cityProdId = $row[''];
        // $cityBuildingId = $row[''];
        $_SESSION['city_id'] = $cityId;

        $resources = [
            'food' => $row['food'],
            'wood' => $row['wood'],
            'iron' => $row['iron'],
            'stone' => $row['stone'],
            'oil' => $row['oil'],
            'diamonds' => 200,
        ];

        $productions = [
            'wood' => $row['wood_production'],
            'iron' => $row['iron_production'],
            'food' => $row['food_production'],
            'oil' => $row['oil_production'],
            'stone' => $row['stone_production'],
        ];
    }
} else {

    $cityName = 'None';
    $cityResId = 'None';

    $resources = [
        'food' => 0,
        'wood' => 0,
        'iron' => 0,
        'stone' => 0,
        'oil' => 0,
        'diamonds' => 0,
    ];

    $productions = [
        'food' => 0,
        'wood' => 0,
        'iron' => 0,
        'oil' => 0,
        'stone' => 0,
    ];
}




// if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {

//     $query = "SELECT * FROM cities WHERE player_id = ?";
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("s", $id);
//     $stmt->execute();

//     $result = $stmt->get_result();

//     if ($result->num_rows === 1) {
//         $row = $result->fetch_assoc();

//         $cityId = $row['id'];
//         $cityName = $row['name'];
//         $cityResId = $row['resources_id'];
//         // $cityProdId = $row[''];
//         // $cityBuildingId = $row[''];
//         $_SESSION['city_id'] = $cityId;
//     }

//     $query = "SELECT * FROM resources WHERE id = ?";
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("s", $cityResId);
//     $stmt->execute();

//     $result = $stmt->get_result();

//     if ($result->num_rows === 1) {
//         $row = $result->fetch_assoc();
//         $resources = [
//             'wood' => $row['wood'],
//             'water' => $row['water'],
//             'electricity' => $row['electricity'],
//             'iron' => $row['iron'],
//             'food' => $row['food'],
//             'lime' => $row['lime'],
//             'money' => $row['money'],
//             'chips' => $row['chips'],
//             'machinery' => $row['machinery'],
//         ];
//     }

//     $query = "SELECT * FROM productions WHERE city_id = ?";
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("s", $cityId);
//     $stmt->execute();

//     $result = $stmt->get_result();

//     if ($result->num_rows === 1) {
//         $row = $result->fetch_assoc();
//         $productions = [
//             'wood' => $row['wood_production'],
//             'water' => $row['water_production'],
//             'electricity' => $row['electricity_production'],
//             'iron' => $row['iron_production'],
//             'food' => $row['food_production'],
//             'lime' => $row['lime_production'],
//             'money' => $row['money_production'],
//             'chips' => $row['chips_production'],
//             'machinery' => $row['machinery_production'],
//         ];
//     }

// }