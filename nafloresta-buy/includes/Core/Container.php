<?php

namespace NaFlorestaBuy\Core;

class Container
{
    private array $entries = [];

    public function set(string $id, callable $factory): void
    {
        $this->entries[$id] = $factory;
    }

    public function get(string $id)
    {
        if (!isset($this->entries[$id])) {
            throw new \RuntimeException(sprintf('Service "%s" not found.', $id));
        }

        if (is_callable($this->entries[$id])) {
            $this->entries[$id] = ($this->entries[$id])($this);
        }

        return $this->entries[$id];
    }
}
