<?php
class VisaConfig {
    public static function getClientConfig() {
        return [
            'apiKey' => 'FK3XF20AHE38BKJ6P8D8219r9YBA1JENmvhdg1HkZUQ5ltWBo',
            'sharedSecret' => '9A6WkmvskQ',
            'certificatePath' => __DIR__ . '/visa_certificate.p12', // Caminho do certificado .p12
            'certificatePassword' => 'sua_senha_do_certificado',
            'sandboxUrl' => 'https://sandbox.api.visa.com', // Endpoint sandbox
        ];
    }
}