<?php
$collegeDB = new PDO(
  "mysql:host=127.0.0.1;dbname=college_db",
  "root",
  ""
);
$collegeDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

