<?php
header('Content-Type: application/json');
echo json_encode([
    ['id' => 1, 'name' => 'Testgerät', 'status' => 'on'],
    ['id' => 2, 'name' => 'Lampe', 'status' => 'off']
]);