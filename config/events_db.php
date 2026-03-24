<?php
$eventDB = new PDO(
  "mysql:host=127.0.0.1;dbname=event_db",
  "root",
  ""
);
$eventDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


