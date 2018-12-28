<?php
$output = [
    'SERVER' => $_SERVER,
    'GET' => $_GET,
    'POST' => $_POST,
    'FILES' => $_FILES,
    'COOKIE' => $_COOKIE,
    'INPUT' => file_get_contents('php://input')
];

if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        setcookie($name, $value);
    }
}

echo json_encode($output);
