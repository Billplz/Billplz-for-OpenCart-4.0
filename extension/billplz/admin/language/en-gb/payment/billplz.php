<?php

// Heading
$_['heading_title']          = 'Billplz. Fair Payment Platform';

// Text
$_['text_billplz']           = '<a href="https://www.billplz.com/" target="_blank"><img src="../extension/billplz/admin/view/image/payment/billplz.svg" alt="Billplz" title="Billplz" style="width: 69px; height: 25px;" /></a>';
$_['text_extension']         = 'Extensions';
$_['text_payment']           = 'Payment';
$_['text_success']           = 'Success: You have modified Billplz!';
$_['text_edit']              = 'Edit Billplz';

// Settings
$_['text_api_key']           = 'API Secret Key';
$_['text_x_signature']       = 'XSignature Key';
$_['text_collection_id']     = 'Collection ID';
$_['text_is_sandbox']        = 'Sandbox Mode';

// Entry
$_['entry_total']            = 'Total';
$_['entry_completed_status'] = 'Completed Status';
$_['entry_pending_status']   = 'Pending Status';
$_['entry_geo_zone']         = 'Geo Zone';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_status']           = 'Status';

// Tab
$_['tab_api_credentials']    = 'API Credentials';
$_['tab_general']            = 'General';
$_['tab_order_status']       = 'Order Status';

// Help
$_['help_api_key']           = 'API key can be obtained from Billplz Dashboard > Settings, under Keys & Integration section.';
$_['help_x_signature']       = 'X-Signature key can be obtained from Billplz Dashboard > Settings, under Keys & Integration section.';
$_['help_collection_id']     = 'Collection ID can be obtained from Billplz Billing page.';
$_['help_is_sandbox']        = sprintf( 'Enable sandbox mode. Billplz sandbox can be used to test payments. Sign up for a <a href="%s" target="_blank">sandbox account</a>.', 'https://billplz-sandbox.com/' );
$_['help_total']             = 'The checkout total the order must reach before this payment method becomes active.';

// Error
$_['error_permission']       = 'Warning: You do not have permission to modify Billplz!';
$_['error_api_key']          = '<strong>Billplz API Secret Key</strong> is required.';
$_['error_collection_id']    = '<strong>Billplz Collection ID</strong> is required.';
$_['error_x_signature']      = '<strong>Billplz X Signature Key</strong> is required.';
$_['error_api_credentials']  = 'Invalid Billplz API credentials.';
