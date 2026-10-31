<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype Kasir - T006</title>
    <style>
        :root { color-scheme: light; --ink:#111827; --muted:#4b5563; --line:#d1d5db; --brand:#0f766e; --bg:#f8fafc; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, sans-serif; background:var(--bg); color:var(--ink); line-height:1.5; }
        header { background:var(--brand); color:white; padding:1rem 1.25rem; }
        main { max-width:72rem; margin:0 auto; padding:1.25rem; display:grid; gap:1rem; grid-template-columns:minmax(0,1.4fr) minmax(18rem,1fr); }
        section, aside { background:white; border:1px solid var(--line); border-radius:.75rem; padding:1rem; }
        h1 { margin:0; font-size:1.25rem; }
        h2 { margin-top:0; font-size:1rem; }
        label { display:block; font-weight:600; margin-top:.75rem; }
        input { width:100%; min-height:2.75rem; padding:.5rem; border:1px solid var(--line); border-radius:.5rem; font:inherit; }
        table { width:100%; border-collapse:collapse; margin-top:.75rem; }
        th, td { text-align:left; border-bottom:1px solid var(--line); padding:.5rem .25rem; vertical-align:top; }
        td:last-child, th:last-child { text-align:right; }
        .muted { color:var(--muted); font-size:.9rem; }
        .summary { background:#ecfdf5; border:1px solid #a7f3d0; border-radius:.75rem; padding:1rem; margin-top:1rem; }
        .summary dl { display:grid; grid-template-columns:1fr auto; gap:.5rem; margin:0; }
        .summary dt { color:var(--muted); }
        .summary dd { margin:0; font-variant-numeric:tabular-nums; font-weight:600; text-align:right; }
        .primary { width:100%; min-height:3rem; border:0; border-radius:.6rem; background:var(--brand); color:white; font:inherit; font-weight:700; margin-top:1rem; cursor:pointer; }
        .secondary { min-height:2.75rem; border:1px solid var(--line); border-radius:.5rem; background:white; font:inherit; padding:.4rem .7rem; cursor:pointer; }
        .badge { display:inline-block; padding:.15rem .5rem; border-radius:999px; background:#fef3c7; color:#92400e; font-size:.8rem; font-weight:700; }
        .notice { padding:.75rem; border:1px dashed #92400e; background:#fffbeb; border-radius:.5rem; margin-top:1rem; }
        [hidden] { display:none; }
        @media (max-width: 52rem) { main { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Prototype kasir — uji usability T006</h1>
    <div>Data contoh BR05. Prototype tidak menyimpan pesanan atau uang.</div>
</header>
<main>
    <section aria-labelledby="order-heading">
        <h2 id="order-heading">Pesanan baru</h2>
        <p class="muted">Cabang Karya Laundry 1 · Status <span class="badge">Draft lokal</span> · Estimasi selesai 2 hari kerja</p>

        <label for="kiloan">Berat kiloan (kg)</label>
        <input id="kiloan" type="number" min="0.1" step="0.1" value="2.3" inputmode="decimal">

        <label for="bedcover">Bed cover (pcs)</label>
        <input id="bedcover" type="number" min="0" step="1" value="2" inputmode="numeric">

        <label><span><input id="express" type="checkbox" style="width:auto;min-height:auto"> Express</span></label>
        <label><span><input id="delivery" type="checkbox" checked style="width:auto;min-height:auto"> Antar ke alamat</span></label>

        <table aria-label="Rincian layanan">
            <thead><tr><th>Layanan</th><th>Fisik</th><th>Ditagih</th><th>Subtotal</th></tr></thead>
            <tbody>
                <tr>
                    <td>Cuci Kering Kiloan<br><span class="muted">Rp8.000/kg · min 3 kg</span></td>
                    <td id="kilo-physical">2,3 kg</td>
                    <td id="kilo-billable">3 kg</td>
                    <td id="kilo-subtotal">Rp24.000</td>
                </tr>
                <tr>
                    <td>Bed Cover<br><span class="muted">Rp25.000/pcs</span></td>
                    <td id="bed-physical">2 pcs</td>
                    <td id="bed-billable">2 pcs</td>
                    <td>Rp50.000</td>
                </tr>
            </tbody>
        </table>

        <label for="discount">Diskon layanan (%)</label>
        <input id="discount" type="number" min="0" max="100" step="1" value="10">

        <div class="notice">
            Prototype memakai perhitungan tampilan. Angka final pada aplikasi tetap dihitung server. Kembalian dan DP tidak boleh mengubah total tagihan.
        </div>
    </section>

    <aside aria-labelledby="summary-heading">
        <h2 id="summary-heading">Ringkasan</h2>
        <div class="summary">
            <dl>
                <dt>Subtotal layanan</dt><dd id="subtotal">Rp74.000</dd>
                <dt>Diskon</dt><dd id="discount-value">−Rp7.400</dd>
                <dt>Express</dt><dd id="express-value">Rp0</dd>
                <dt>Antar</dt><dd id="delivery-value">Rp5.000</dd>
                <dt><strong>Total</strong></dt><dd id="total">Rp71.600</dd>
            </dl>
        </div>

        <label for="dp">DP diterima (Rp)</label>
        <input id="dp" type="number" min="0" step="1000" value="30000">

        <div class="summary" style="background:#f0f9ff;border-color:#bae6fd">
            <dl>
                <dt>Sisa tagihan</dt><dd id="balance">Rp41.600</dd>
                <dt>Status</dt><dd>DP / belum lunas</dd>
                <dt>Tenggat awal</dt><dd>2 hari kerja</dd>
            </dl>
        </div>

        <button class="primary" type="button" id="save">Simpan pesanan (prototype)</button>
        <button class="secondary" type="button" id="reset" style="width:100%;margin-top:.5rem">Reset contoh</button>
        <p id="message" class="notice" hidden role="status"></p>
    </aside>
</main>
<script>
    const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    const quantity = (value) => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });

    function recalculate() {
        const physical = Math.max(0, Number(document.querySelector('#kiloan').value || 0));
        const billable = Math.max(3, Math.ceil(physical * 10) / 10);
        const bedCover = Math.max(0, Number(document.querySelector('#bedcover').value || 0));
        const discount = Math.min(100, Math.max(0, Number(document.querySelector('#discount').value || 0)));
        const service = billable * 8000 + bedCover * 25000;
        const discountValue = Math.round(service * discount / 100);
        const express = document.querySelector('#express').checked ? Math.round((service - discountValue) * 0.5) : 0;
        const delivery = document.querySelector('#delivery').checked ? 5000 : 0;
        const total = service - discountValue + express + delivery;
        const dp = Math.max(0, Number(document.querySelector('#dp').value || 0));

        document.querySelector('#kilo-physical').textContent = `${quantity(physical)} kg`;
        document.querySelector('#kilo-billable').textContent = `${quantity(billable)} kg`;
        document.querySelector('#kilo-subtotal').textContent = rupiah(billable * 8000);
        document.querySelector('#bed-physical').textContent = `${quantity(bedCover)} pcs`;
        document.querySelector('#bed-billable').textContent = `${quantity(bedCover)} pcs`;
        document.querySelector('#subtotal').textContent = rupiah(service);
        document.querySelector('#discount-value').textContent = `−${rupiah(discountValue)}`;
        document.querySelector('#express-value').textContent = rupiah(express);
        document.querySelector('#delivery-value').textContent = rupiah(delivery);
        document.querySelector('#total').textContent = rupiah(total);
        document.querySelector('#balance').textContent = rupiah(Math.max(0, total - dp));
    }

    document.querySelectorAll('input').forEach((input) => input.addEventListener('input', recalculate));
    document.querySelector('#reset').addEventListener('click', () => {
        document.querySelector('#kiloan').value = '2.3';
        document.querySelector('#bedcover').value = '2';
        document.querySelector('#discount').value = '10';
        document.querySelector('#dp').value = '30000';
        document.querySelector('#express').checked = false;
        document.querySelector('#delivery').checked = true;
        recalculate();
    });
    document.querySelector('#save').addEventListener('click', () => {
        const message = document.querySelector('#message');
        message.textContent = 'Ini prototype. Pesanan, DP, dan antrean tidak disimpan.';
        message.hidden = false;
    });
    recalculate();
</script>
</body>
</html>
