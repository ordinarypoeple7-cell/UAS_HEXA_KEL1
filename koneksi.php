<?php
$servername = "localhost";
$databasename = "hexa_db";
$username = "root";
$password = "";

$conn = mysqli_connect("localhost", "root", "", "hexa_db");

if (!$conn) {
    die("koneksi gagal: " . mysqli_connect_error());
}
