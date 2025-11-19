<?php
require __DIR__ . '/vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;

class PayPalConfig {
    public static function getClient() {
        // Credenciais corretas de Sandbox
        $clientId = "AaZBsrjQVwGJ2C6jdCa2UJkrIdarfHzhfBBDMr039wp11qkERhID8eKOZWdnLKPkKE8tPkGhuqhOVQ9z";
        $clientSecret = "EJxfBiICPbrUvLEvmmfSTIhoBaxWr2D3t7RkjdoEU6WwFFagpLh6cpqKWTzU2HKCgFGGa0qcwM2uaU0e";

        $environment = new SandboxEnvironment($clientId, $clientSecret);
        return new PayPalHttpClient($environment);
    }
}
