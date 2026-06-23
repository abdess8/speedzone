<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 8px;
        font-family: DejaVu Sans, sans-serif;
        color: #000;
        font-size: 11px;
        line-height: 1.3;
    }
    .label { width: 100%; border: 2px solid #000; padding: 6px; }
    .page-break { page-break-after: always; }
    .header {
        width: 100%;
        border-bottom: 2px solid #000;
        padding-bottom: 4px;
        margin-bottom: 6px;
    }
    .header td { vertical-align: middle; }
    .logo { max-height: 34px; max-width: 120px; }
    .company { font-size: 13px; font-weight: bold; }
    .tracking {
        text-align: center;
        font-size: 17px;
        font-weight: bold;
        letter-spacing: 1px;
        margin: 4px 0;
        padding: 4px 0;
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
    }
    .qr-row td { vertical-align: top; }
    .qr { width: 120px; text-align: center; }
    .qr img { width: 110px; height: 110px; }
    .meta { font-size: 10px; }
    .section-title {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: bold;
        border-bottom: 1px solid #000;
        margin: 6px 0 3px;
        padding-bottom: 1px;
    }
    .name { font-size: 14px; font-weight: bold; }
    .phone { font-size: 13px; font-weight: bold; }
    .address { font-size: 11px; }
    .city { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .amounts { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .amounts td { padding: 2px 0; font-size: 11px; }
    .amounts .total { font-size: 15px; font-weight: bold; border-top: 1px solid #000; padding-top: 3px; }
    .pay {
        display: inline-block;
        border: 2px solid #000;
        padding: 2px 8px;
        font-weight: bold;
        font-size: 13px;
    }
    .collection {
        margin-top: 6px;
        border: 3px solid #000;
        padding: 6px 8px;
        text-align: center;
    }
    .collection.cash-required {
        background: #000;
        color: #fff;
    }
    .collection.card-paid {
        background: #f2f2f2;
    }
    .collection-title {
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .collection-row {
        font-size: 12px;
        margin-bottom: 2px;
    }
    .collection-amount {
        font-size: 14px;
        margin-top: 4px;
        padding-top: 4px;
        border-top: 1px dashed #fff;
    }
    .collection.card-paid .collection-amount {
        border-top-color: #000;
    }
    .flags { margin-top: 6px; }
    .flag {
        display: inline-block;
        border: 1.5px solid #000;
        padding: 2px 6px;
        font-weight: bold;
        font-size: 10px;
        margin-right: 4px;
    }
    .flag.on { background: #000; color: #fff; }
    .notes {
        margin-top: 6px;
        border: 1px solid #000;
        padding: 4px;
        font-size: 10px;
        min-height: 28px;
    }
    .foot { margin-top: 6px; font-size: 9px; text-align: center; }
</style>
