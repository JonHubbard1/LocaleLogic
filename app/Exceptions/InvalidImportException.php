<?php

namespace App\Exceptions;

use Exception;

/**
 * Invalid Import Exception
 *
 * Thrown when attempting to import older geography data over newer data.
 * Prevents data integrity issues by ensuring only same-year or newer
 * geography data can be imported.
 */
class InvalidImportException extends Exception
{
    //
}
