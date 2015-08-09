<?php
$inchooSwitch = new Mage_Core_Model_Config();
$inchooSwitch ->saveConfig('carriers/flatrate/title', "JNE Reguler", 'default', 0);
$inchooSwitch ->saveConfig('carriers/flatrate/price', "10000", 'default', 0);
$inchooSwitch ->saveConfig('carriers/flatrate/name', "", 'default', 0);
$inchooSwitch ->saveConfig('payment/checkmo/active', "0", 'default', 0);
$inchooSwitch ->saveConfig('customer/address/street_lines', "1", 'default', 0);
$inchooSwitch ->saveConfig('general/locale/timezone', "Asia/Jakarta", 'default', 0);
$inchooSwitch ->saveConfig('general/locale/code', "id_ID", 'default', 0);
$inchooSwitch ->saveConfig('general/country/default', "ID", 'default', 0);
$inchooSwitch ->saveConfig('general/country/allow', "ID", 'default', 0);
$inchooSwitch ->saveConfig('general/country/optional_zip_countries', "ID", 'default', 0);
$inchooSwitch ->saveConfig('sales/reorder/allow', "0", 'default', 0);
$inchooSwitch ->saveConfig('customer/integernet_removecustomeraccountlinks/items', "billing_agreements,recurring_profiles,OAuth Customer Tokens", 'default', 0);