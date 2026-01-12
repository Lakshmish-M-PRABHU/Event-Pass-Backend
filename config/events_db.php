<?php
$eventDB = new PDO(
  "mysql:host=localhost;dbname=event_db",
  "root",
  ""
);
$eventDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


