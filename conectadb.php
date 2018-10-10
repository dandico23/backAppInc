<?php
function db_connect()
{
    $pdo = new PDO("mysql:host=localhost;dbname=papafilas_homolog","root","wolfpack");
  
    return $pdo;
}
function db_connectMW()
{
    $pdo = new PDO("mysql:host=localhost;dbname=mw_homolog","root","P@p@filas2018bd");
  
    return $pdo;
}
?>
