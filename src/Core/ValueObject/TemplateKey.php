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
     * @param  string                    $value The raw template key (e.g. 'std_7' or legacy 'std.7').
     * @throws \InvalidArgumentException If empty or contains invalid characters.
     */
    public function __construct(string $value)
    {
        // 1. Trimmen
        $value = \trim($value);

        // 2. Self-Healing für alte Datenbank-Einträge / Tippfehler (std.7 -> std_7)
        $value = \str_replace('.', '_', $value);

        if ($value === '') {
            throw new \InvalidArgumentException('Der Template-Key darf nicht leer sein.');
        }

        // 3. Strikte Validierung: alphanumerisch + Unterstrich + Bindestrich
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
