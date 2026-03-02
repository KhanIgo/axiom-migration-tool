<?php
/**
 * Replace Engine
 * 
 * Handles serialized-safe string replacement
 */

namespace Axiom\WPMigrate\Application;

if (!defined('ABSPATH')) {
    exit;
}

class ReplaceEngine {
    
    /**
     * Replacement rules
     *
     * @var array
     */
    private $rules = [];

    /**
     * Regex replacements
     *
     * @var array
     */
    private $regexRules = [];

    /**
     * Constructor
     *
     * @param array $rules
     * @param array $regexRules
     */
    public function __construct(array $rules = [], array $regexRules = []) {
        $this->rules = $rules;
        $this->regexRules = $regexRules;
    }

    /**
     * Add a replacement rule
     *
     * @param string $search
     * @param string $replace
     */
    public function addRule(string $search, string $replace): void {
        $this->rules[$search] = $replace;
    }

    /**
     * Add a regex replacement rule
     *
     * @param string $pattern
     * @param string $replace
     */
    public function addRegexRule(string $pattern, string $replace): void {
        $this->regexRules[] = ['pattern' => $pattern, 'replace' => $replace];
    }

    /**
     * Process replacement with serialized data support
     *
     * @param mixed $data
     * @return mixed
     */
    public function process($data) {
        if (is_string($data)) {
            return $this->replaceInString($data);
        }

        if (is_array($data)) {
            return array_map([$this, 'process'], $data);
        }

        if (is_object($data)) {
            $serialized = serialize($data);
            $replaced = $this->replaceInString($serialized);
            return @unserialize($replaced) ?: $data;
        }

        return $data;
    }

    /**
     * Replace in string with serialized data awareness
     *
     * @param string $string
     * @return string
     */
    private function replaceInString(string $string): string {
        // Check if string contains serialized data
        if ($this->isSerialized($string)) {
            return $this->replaceInSerializedData($string);
        }

        // Apply regular replacements
        foreach ($this->rules as $search => $replace) {
            $string = str_replace($search, $replace, $string);
        }

        // Apply regex replacements
        foreach ($this->regexRules as $rule) {
            $string = preg_replace($rule['pattern'], $rule['replace'], $string);
        }

        return $string;
    }

    /**
     * Check if string is serialized
     *
     * @param string $string
     * @return bool
     */
    private function isSerialized(string $string): bool {
        if (!is_string($string)) {
            return false;
        }

        // Quick check for serialized data markers
        if (!preg_match('/^(O|a|s|i|d|b|N):/', $string)) {
            return false;
        }

        // Try to unserialize
        $unserialized = @unserialize($string);
        return $unserialized !== false || ($string === 'b:0;' && $unserialized === false);
    }

    /**
     * Replace in serialized data
     *
     * @param string $serialized
     * @return string
     */
    private function replaceInSerializedData(string $serialized): string {
        $data = @unserialize($serialized);
        if ($data === false && $serialized !== 'b:0;') {
            // Failed to unserialize, treat as regular string
            return $this->replaceInString($serialized);
        }

        // Recursively process the data
        $processed = $this->process($data);

        // Re-serialize
        return serialize($processed);
    }

    /**
     * Process database row with replacements
     *
     * @param object $row
     * @param string $primaryKey
     * @return object
     */
    public function processRow(object $row, string $primaryKey = 'id'): object {
        foreach ($row as $column => $value) {
            if ($column === $primaryKey) {
                continue;
            }
            $row->$column = $this->process($value);
        }
        return $row;
    }

    /**
     * Get built-in WordPress replacements
     *
     * @param string $oldUrl
     * @param string $newUrl
     * @param string $oldPath
     * @param string $newPath
     * @return array
     */
    public static function getWordPressReplacements(
        string $oldUrl,
        string $newUrl,
        string $oldPath = '',
        string $newPath = ''
    ): array {
        $rules = [
            $oldUrl => $newUrl,
            esc_url($oldUrl) => esc_url($newUrl),
            wp_specialchars_decode($oldUrl) => wp_specialchars_decode($newUrl),
        ];

        if (!empty($oldPath) && !empty($newPath)) {
            $rules[$oldPath] = $newPath;
        }

        return $rules;
    }
}
