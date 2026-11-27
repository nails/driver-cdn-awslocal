<?php

namespace Nails\Cdn\Driver\Aws\Admin\Permission\Settings;

use Nails\Admin\Interfaces\Permission;

class Aws implements Permission
{
    public function label(): string
    {
        return 'Can manage CDN: AWS S3 settings';
    }

    public function group(): string
    {
        return 'Settings';
    }
}
