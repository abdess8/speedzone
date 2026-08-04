<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 7px;
        font-family: DejaVu Sans, sans-serif;
        color: #16334F;
        font-size: 10px;
        line-height: 1.25;
    }

    /* dompdf ignores box-sizing, so the frame relies on auto width to keep
       its padding and border inside the page. */
    .label {
        border: 1px solid #D5DDE6;
        border-radius: 10px;
        background: #FFFFFF;
        padding: 9px 10px 8px;
        page-break-inside: avoid;
    }

    .page-break { page-break-after: always; }

    /* Arabic fields are already emitted in visual order, so the only thing left
       to do is to hang them on the right edge of their block. */
    .rtl { text-align: right; }

    /* Header ------------------------------------------------------------- */
    .brand { width: 100%; border-collapse: collapse; }
    .brand td { vertical-align: middle; padding: 0; }
    .brand-icon { width: 24px; }
    .brand-icon-cell { width: 30px; }
    .brand-name {
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.6px;
        text-align: center;
        text-transform: uppercase;
    }
    /* The lockup is ~3.3:1, so height binds well before width. 22px left the
       "DELIVERY" line under a pixel tall once dompdf rasterised it. */
    .brand-logo { max-height: 32px; max-width: 170px; }
    .rule { border-bottom: 1.5px solid #16334F; margin: 6px 0 4px; }

    /* Tracking + barcode -------------------------------------------------- */
    .tracking {
        text-align: center;
        font-size: 24px;
        font-weight: bold;
        letter-spacing: 0.5px;
        padding: 1px 0 3px;
    }
    .barcode { text-align: center; padding-bottom: 5px; }
    .barcode img { width: 78%; height: 34px; }

    /* Recipient ----------------------------------------------------------- */
    .recipient-row { width: 100%; border-collapse: collapse; }
    .recipient-row td { vertical-align: top; padding: 0; }
    .qr-cell { width: 110px; text-align: center; }
    .qr-cell img { width: 94px; height: 94px; }
    .qr-caption {
        font-size: 7.5px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: #5A6B80;
        padding-top: 2px;
    }
    .recipient {
        border: 1px solid #BFD2E4;
        border-radius: 4px;
        padding: 5px 7px 6px;
    }
    .recipient-head { width: 100%; border-collapse: collapse; }
    .recipient-head td { vertical-align: top; padding: 0; }
    .recipient-title {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #34557A;
        padding-bottom: 2px;
    }
    .recipient-name { font-size: 13px; font-weight: bold; }
    .recipient-line { font-size: 9.5px; color: #2A4360; }
    .pin-cell { width: 42px; text-align: center; }
    .pin-cell img { width: 14px; }
    .pin-label {
        font-size: 6.5px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #5A6B80;
    }
    .recipient-city {
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding-top: 3px;
    }

    /* Collection band ------------------------------------------------------ */
    .collect {
        margin-top: 7px;
        background: #1E3A5C;
        border-radius: 4px;
        color: #FFFFFF;
        padding: 6px 8px 7px;
        text-align: center;
    }
    .collect.paid { background: #33556F; }
    .collect-title {
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.6px;
    }
    .collect-sub { font-size: 10.5px; padding-top: 2px; }
    .collect-amount {
        font-size: 11px;
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px solid #6E88A5;
    }
    .collect-amount strong { font-size: 12.5px; }

    /* Order details -------------------------------------------------------- */
    .details { margin-top: 7px; }
    .details-title {
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #34557A;
        padding-bottom: 3px;
    }
    .details-grid { width: 100%; border-collapse: collapse; }
    .details-grid td { vertical-align: top; padding: 0 7px; }
    .details-grid td.first { padding-left: 0; width: 32%; }
    .details-grid td.split { border-left: 1px solid #D5DDE6; }
    .details-grid td.last { width: 38%; padding-right: 0; }
    .details-label {
        font-size: 8px;
        font-weight: bold;
        color: #5A6B80;
    }
    .details-value { font-size: 9.5px; }

    /* Totals --------------------------------------------------------------- */
    .totals {
        width: 100%;
        border-collapse: collapse;
        margin-top: 7px;
        border-top: 1px solid #D5DDE6;
    }
    .totals td { vertical-align: middle; padding: 6px 0 0; }
    .totals-label { font-size: 8.5px; color: #5A6B80; padding-bottom: 3px; }
    .pay-icon { width: 20px; }
    .pay-name { font-size: 14px; font-weight: bold; padding-left: 4px; }
    .amount-cell { text-align: right; }
    .amount-label {
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .amount-value { font-size: 14px; font-weight: bold; }
    .amount-paid { font-size: 13px; font-weight: bold; }

    /* Flags ---------------------------------------------------------------- */
    .flags { text-align: right; padding-top: 5px; }
    .flag {
        display: inline-block;
        border: 1px solid #16334F;
        border-radius: 3px;
        padding: 2px 6px;
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 0.3px;
        margin-left: 4px;
    }
    .flag.on { background: #16334F; color: #FFFFFF; }

    /* Footer --------------------------------------------------------------- */
    .foot {
        width: 100%;
        border-collapse: collapse;
        margin-top: 7px;
        border-top: 1px solid #D5DDE6;
        font-size: 7.5px;
        color: #5A6B80;
    }
    .foot td { vertical-align: middle; padding: 4px 0 0; }
    .foot-icon { width: 14px; }
    .foot-icon-cell { width: 18px; }
</style>
