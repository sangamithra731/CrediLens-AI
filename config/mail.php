<?php
/**
 * CrediLens AI — Email & SMTP Configuration
 */

return [
    'driver'     => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => '',
    'password'   => '',
    'from_email' => 'notifications@credilens.ai',
    'from_name'  => 'CrediLens AI',
    'timeout'    => 10,
];
