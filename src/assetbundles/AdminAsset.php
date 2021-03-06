<?php
namespace verbb\comments\assetbundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class AdminAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->sourcePath = "@verbb/comments/resources/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/comments-cp.css',
        ];

        $this->js = [
            'js/comments-cp.js',
        ];

        parent::init();
    }
}
