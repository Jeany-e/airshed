<?php
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Browser-side sensor writes are disabled. Use the authenticated device ingestion endpoint.';
