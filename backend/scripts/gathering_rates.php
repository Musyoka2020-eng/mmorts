<?php
// AJAX endpoint for getting gathering rates
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../system/config.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $response['message'] = 'Invalid JSON data';
    echo json_encode($response);
    exit;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'get_rate':
        $resourceType = $input['resource_type'] ?? '';
        
        if (empty($resourceType)) {
            $response['message'] = 'Resource type required';
            echo json_encode($response);
            exit;
        }
        
        // Get gathering rate for this resource
        $stmt = $conn->prepare("SELECT base_rate, description FROM gathering_rates WHERE resource_type = ?");
        $stmt->bind_param("s", $resourceType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = [
                'resource_type' => $resourceType,
                'rate' => floatval($row['base_rate']),
                'description' => $row['description']
            ];
        } else {
            // Return default rate if not found
            $response['success'] = true;
            $response['data'] = [
                'resource_type' => $resourceType,
                'rate' => 10.0,
                'description' => 'Standard gathering rate'
            ];
        }
        break;
        
    case 'get_all_rates':
        // Get all gathering rates
        $stmt = $conn->prepare("SELECT * FROM gathering_rates ORDER BY resource_type");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rates = [];
        while ($row = $result->fetch_assoc()) {
            $rates[$row['resource_type']] = [
                'rate' => floatval($row['base_rate']),
                'description' => $row['description']
            ];
        }
        
        $response['success'] = true;
        $response['data'] = $rates;
        break;
        
    default:
        $response['message'] = 'Invalid action';
        break;
}

echo json_encode($response);
?>
