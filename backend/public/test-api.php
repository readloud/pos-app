<?php
header("Content-Type: application/json");
echo json_encode(["message" => "Test API endpoint works", "timestamp" => date("Y-m-d H:i:s")]);
