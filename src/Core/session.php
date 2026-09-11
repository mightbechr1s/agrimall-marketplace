<?php
// src/Core/session.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
