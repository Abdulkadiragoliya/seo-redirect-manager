<?php

require_once 'c:/wamp64/www/surreysteels/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

$sites = Craft::$app->getSites()->getAllSites();
echo "Sites in Surrey Steels:\n";
foreach ($sites as $s) {
    echo "ID: {$s->id}, Handle: {$s->handle}, Name: {$s->name}\n";
}
