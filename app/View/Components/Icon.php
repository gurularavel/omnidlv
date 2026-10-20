<?php

namespace App\View\Components;

use Illuminate\View\Component;
use InvalidArgumentException;

/** Inlines a Lucide icon from resources/icons so icons inherit currentColor and need no JS. */
class Icon extends Component
{
    private static array $cache = [];

    public function __construct(public string $name, public int $size = 18, public string $stroke = '1.75') {}

    public function render(): string
    {
        $svg = self::$cache[$this->name] ??= $this->load();

        $svg = preg_replace(
            ['/\swidth="24"/', '/\sheight="24"/', '/stroke-width="2"/', '/class="[^"]*"/'],
            [" width=\"{$this->size}\"", " height=\"{$this->size}\"", "stroke-width=\"{$this->stroke}\"", 'class="{{ $attributes->get(\'class\') }}" aria-hidden="true" focusable="false"'],
            $svg,
        );

        return $svg;
    }

    private function load(): string
    {
        $path = resource_path("icons/{$this->name}.svg");

        if (! is_file($path)) {
            throw new InvalidArgumentException("Unknown icon [{$this->name}].");
        }

        return trim(preg_replace('/<!--.*?-->/s', '', file_get_contents($path)));
    }
}
