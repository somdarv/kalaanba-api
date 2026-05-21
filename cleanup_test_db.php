<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::connection('pgsql_testing')->statement('DROP SCHEMA IF EXISTS analytics CASCADE');
    echo "Dropped analytics schema\n";
} catch (Exception $e) {
    echo 'Error dropping analytics: '.$e->getMessage()."\n";
}

try {
    DB::connection('pgsql_testing')->statement('DROP SCHEMA IF EXISTS admin_governance CASCADE');
    echo "Dropped admin_governance schema\n";
} catch (Exception $e) {
    echo 'Error dropping admin_governance: '.$e->getMessage()."\n";
}

echo "Done\n";
