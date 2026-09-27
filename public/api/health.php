<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
try { db()->ping(); json_response(['ok'=>true,'app'=>(string)env('APP_NAME','Grewire'),'database'=>'postgresql','environment'=>(string)env('APP_ENV','local')]); }
catch (Throwable $e) { json_response(['ok'=>false,'app'=>(string)env('APP_NAME','Grewire'),'error'=>$e->getMessage()],503); }
