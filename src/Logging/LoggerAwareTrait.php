<?php

namespace LiturgicalCalendar\Components\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Logger Aware Trait
 *
 * Provides PSR-3 logger dependency injection and access for classes.
 * Falls back to NullLogger when no logger is configured.
 */
trait LoggerAwareTrait
{
    private ?LoggerInterface $logger = null;

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger PSR-3 logger instance
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Gets the logger instance.
     *
     * Returns a NullLogger if no logger has been set, ensuring
     * the application never fails due to missing logger.
     *
     * @return LoggerInterface PSR-3 logger instance
     */
    protected function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }
}
