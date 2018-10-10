<?php
function db_connect()
{
    $pdo = new PDO("mysql:host=localhost;dbname=pericia_homolog","root","wolfpack");
  
    return $pdo;
}

?>