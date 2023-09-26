<?php

declare(strict_types=1);

namespace StrictHelpers {

    use TypeError;

    function file_get_contents(string $path): string
    {
        $result = \file_get_contents($path);

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }

    function ob_get_contents(): string
    {
        $result = \ob_get_contents();

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    function glob(string $pattern, int $flags = 0): array
    {
        $result = \glob($pattern, $flags);

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }

    function realpath(string $path): string
    {

        $result = \realpath($path);

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }

    function filesize(string $filename): int
    {
        $result = \filesize($filename);

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }

    /**
     * @return resource
     */
    function fopen(string $filename, string $mode)
    {
        $result = \fopen($filename, $mode);

        if ($result === false) {
            throw new TypeError();
        }

        return $result;
    }
};
