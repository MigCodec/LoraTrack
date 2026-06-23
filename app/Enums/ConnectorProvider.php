<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorProvider: string
{
    case TtiWebhook = 'tti_webhook';
    case MerakiLocation = 'meraki_location';
    case Mqtt = 'mqtt';
    case SapS4Hana = 'sap_s4hana';
    case BusinessCentral = 'business_central';
    case Shopify = 'shopify';
    case Odoo = 'odoo';
    case Csv = 'csv';
}
