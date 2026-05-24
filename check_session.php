<?php
session_start();

echo "<h2>Session Data</h2>";
echo "<pre>"; // pre tag makes it easy to read
print_r($_SESSION);
echo "</pre>";
?>