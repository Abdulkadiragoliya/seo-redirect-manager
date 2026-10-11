<?php

namespace abdulkadir\seoredirectmanager\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

/**
 * Control Panel Asset Bundle for SEO Redirect Manager
 */
class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CraftCpAsset::class,
        ];

        $this->css = [
            'css/cp.css',
        ];

        $this->js = [
            'js/cp.js',
        ];

        parent::init();
    }
}
