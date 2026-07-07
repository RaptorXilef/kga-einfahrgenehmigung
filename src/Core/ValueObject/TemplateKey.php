<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a template identifier (e.g., for documents or emails).
 */
final readonly class TemplateKey
{
    /**
     * @var string The normalized template key.
     */
    public string $value;

    /**
     * @param  string                    $value The raw template key (e.g. 'std_7').
     * @throws \InvalidArgumentException If empty or contains invalid characters.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Der Template-Key darf nicht leer sein.');
        }

        // Template Keys sollten idealerweise alphanumerisch mit Unterstrichen/Bindestrichen sein
        if (! \preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            throw new \InvalidArgumentException("Ungültiges Format für Template-Key: {$value}");
        }

        $this->value = \strtolower($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
