<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\View;

use Spiral\Views\ViewsInterface;

final readonly class SwaggerView
{
    public function __construct(
        private ViewsInterface $views,
    ) {}

    public function render(): string
    {
        return $this->views->render(path: 'system:swagger/index');
    }
}
