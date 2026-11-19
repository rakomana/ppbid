<?php

/*
|--------------------------------------------------------------------------
| Invoice settings
|--------------------------------------------------------------------------
|
| The seller details printed on every invoice. Set them in .env.
| Use "|" to break the address over several lines, e.g.
| INVOICE_COMPANY_ADDRESS="12 Main Road|Germiston|1401"
|
| Prices are not subject to VAT, so invoices show the winning bid as the total.
|
*/

return [

    'company_name' => env('INVOICE_COMPANY_NAME', env('APP_NAME', 'ppbid')),

    'company_address' => env('INVOICE_COMPANY_ADDRESS', ''),

    'company_email' => env('INVOICE_COMPANY_EMAIL', ''),

    'company_phone' => env('INVOICE_COMPANY_PHONE', ''),

    'company_registration' => env('INVOICE_COMPANY_REGISTRATION', ''),

    'bank_name' => env('INVOICE_BANK_NAME', ''),

    'bank_account_name' => env('INVOICE_BANK_ACCOUNT_NAME', ''),

    'bank_account_number' => env('INVOICE_BANK_ACCOUNT_NUMBER', ''),

    'bank_branch_code' => env('INVOICE_BANK_BRANCH_CODE', ''),

    // Days after the auction ends that payment is due.
    'payment_terms_days' => (int) env('INVOICE_PAYMENT_TERMS_DAYS', 7),

    // Extra line at the bottom of the invoice, e.g. collection instructions.
    'footer_note' => env('INVOICE_FOOTER_NOTE', 'Please use the invoice number as your payment reference.'),

];
