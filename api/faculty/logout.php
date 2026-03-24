<?php
session_start();

header("Access-Control-Allow-Origin: http://127.0.0.1:5501");
header("Access-Control-Allow-Credentials: true");

session_destroy();
echo json_encode(["message" => "Logged out"]);
