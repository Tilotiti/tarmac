<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class InstanceOfExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [new TwigTest('instanceof', [$this, 'instanceOfFilter'])];
    }

    public function instanceOfFilter($value, string $type)
    {
        return ('null' === $type && null === $value)
            || (\function_exists($func = 'is_' . $type) && $func($value))
            || $value instanceof $type;
    }
}