<?php

declare(strict_types=1);

namespace App\Contracts\System;

interface AssetHelperInterface
{
    public function url(string $assetPath): string;
}
