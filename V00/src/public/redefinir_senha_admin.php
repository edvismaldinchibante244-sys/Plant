<?php

http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
exit('Script desativado por segurança.');
