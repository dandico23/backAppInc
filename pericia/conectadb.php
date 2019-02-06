<?php
function db_connect()
{
    $pdo = new PDO("mysql:host=localhost;dbname=pericia_homolog","root","wolfpack",array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    )
);

return $pdo;
}

?>