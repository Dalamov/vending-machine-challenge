<?php

namespace Daniella\VendingMachine\infrastructure\logger;

use Psr\Log\AbstractLogger;
use Throwable;

class ErrorLogLogger extends AbstractLogger
{
    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $normalizedContext = $this->normalizeContext($context);
        $formattedLevel = strtoupper((string) $level);

        error_log(sprintf('[%s] %s%s', $formattedLevel, (string) $message, $normalizedContext));
    }

    /**
     * @param array<mixed> $context
     */
    private function normalizeContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $normalized = array_map(
            static function ($value) {
                if ($value instanceof Throwable) {
                    return [
                        'exception' => get_class($value),
                        'message' => $value->getMessage(),
                        'trace' => $value->getTraceAsString(),
                    ];
                }

                return $value;
            },
            $context
        );

        $encoded = json_encode($normalized);

        if ($encoded === false) {
            return ' [context_encoding_failed]';
        }

        return ' ' . $encoded;
    }
}

