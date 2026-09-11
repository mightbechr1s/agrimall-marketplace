<?php
// src/Core/csrf.php

function csrf_input(): string
{
    return csrf_field();
}

function verify_csrf(): void
{
    csrf_check();
}
