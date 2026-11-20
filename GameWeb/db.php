<?php

    $dbHost = 'aws-1-ap-southeast-1.pooler.supabase.com';
    $dbUser =  'postgres.hyjqddspltmheoukewyx';

    $local = file_exists(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];

    $dbPassword = 'wiwdn0VhauhhAVqE';
    $dbName = 'postgres';

    $conn = pg_connect("host=$dbHost port=5432 dbname=$dbName user=$dbUser password=$dbPassword sslmode=require");

    if (!$conn) {
        exit('Database connection failed.');
    }
?>
