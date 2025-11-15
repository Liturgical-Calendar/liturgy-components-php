<?php

namespace LiturgicalCalendar\Components\Http;

use Exception;

/**
 * HTTP Exception
 *
 * Thrown when HTTP operations fail.
 * Wraps underlying exceptions from PSR-18 clients or native PHP errors.
 */
class HttpException extends Exception
{
}
