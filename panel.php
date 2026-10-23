<?php
require_once __DIR__ . '/config.php';
$u = require_role('admin', 'profesor', 'alumno', 'familia', 'personal');
$vista = $u['rol'] === 'personal' ? 'admin' : $u['rol'];
require __DIR__ . '/dash/' . $vista . '.php';
