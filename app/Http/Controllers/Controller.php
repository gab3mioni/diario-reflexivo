<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Controlador base da aplicação.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
