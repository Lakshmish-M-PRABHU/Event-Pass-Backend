<?php
$collegeDB = new PDO(
  "mysql:host=localhost;dbname=college_db",
  "root",
  ""
);
$collegeDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

