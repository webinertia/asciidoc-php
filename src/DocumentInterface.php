<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp;

/**
 * The subset of Document that PreprocessorReader depends on.
 * Document implements this interface; tests inject a stub.
 */
interface DocumentInterface
{
    /**
     * Return the attribute value, or $default when missing / explicitly unset.
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * The base directory for resolving relative include paths, or null when
     * the source was loaded from a string with no associated path.
     */
    public function getBaseDir(): ?string;

    /**
     * The safe mode that governs include:: resolution and other security decisions.
     */
    public function getSafeMode(): SafeMode;

    /**
     * Return true when the attribute is defined and NOT explicitly unset.
     */
    public function hasAttribute(string $name): bool;

    /**
     * Set a document attribute.
     *
     * @param bool $overridable If false, the attribute is locked and cannot be
     *                          overridden or unset from within document source.
     */
    public function setAttribute(string $name, mixed $value, bool $overridable = true): void;

    /**
     * Explicitly unset a document attribute.
     * Respects the lock: a locked attribute cannot be unset from document source.
     */
    public function unsetAttribute(string $name): void;
}
