<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:5501");
header("Access-Control-Allow-Credentials: true");

session_destroy();
echo json_encode(["message" => "Logged out"]);
