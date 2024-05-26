<?php

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_resource'])) {
    $resourceLevel = 1;
    $resourceType = $_POST['resource_type'];

    if (is_string($resourceType)) {
    }
}
