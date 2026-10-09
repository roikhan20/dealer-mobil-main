/**
 * Kalkulator kredit & TDP interaktif (Vanilla JS, tanpa reload halaman).
 * Rumus identik dengan app/Services/TdpCalculator.php (bunga flat).
 * Hasil di sini hanya ESTIMASI; angka resmi dihitung ulang oleh server.
 */
(() => {
  const modalEl = document.getElementById('creditModal');
  if (!modalEl) return;

  const $ = (id) => document.getElementById(id);
  const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  let partners = [];
  let state = { otr: 0, tenor: 36 };

  const ratesUrl = modalEl.dataset.ratesUrl;

  async function loadRates() {
    if (partners.length) return;
    try {
      const res = await fetch(ratesUrl, { headers: { Accept: 'application/json' } });
      partners = await res.json();
      $('cc_partner').innerHTML = partners.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    } catch (e) {
      $('cc_result').innerHTML = '<div class="alert alert-danger">Gagal memuat data leasing.</div>';
    }
  }

  const partner = () => partners.find(p => String(p.id) === $('cc_partner').value);

  function renderTenors() {
    const p = partner(); if (!p) return;
    const tenors = Object.keys(p.rates).map(Number).sort((a, b) => a - b);
    if (!tenors.includes(state.tenor)) state.tenor = tenors[Math.min(2, tenors.length - 1)];
    $('cc_tenors').innerHTML = tenors.map(t => `
      <input type="radio" class="btn-check" name="cc_tenor" id="t${t}" value="${t}" ${t === state.tenor ? 'checked' : ''}>
      <label class="btn btn-outline-gold btn-sm" for="t${t}">${t / 12} th <small>(${t} bln)</small></label>`).join('');
    $('cc_dp').min = p.min_dp_percent;
    if (parseFloat($('cc_dp').value) < p.min_dp_percent) $('cc_dp').value = p.min_dp_percent;
    $('cc_dpmin').textContent = p.min_dp_percent;
  }

  function calc() {
    const p = partner(); if (!p || !state.otr) return;
    const dpPct = parseFloat($('cc_dp').value);
    const r = p.rates[String(state.tenor)];
    if (!r) return;
    if (isNaN(dpPct) || dpPct < p.min_dp_percent || dpPct >= 100) {
      $('cc_result').innerHTML = `<div class="alert alert-warning mb-0">DP minimal ${p.min_dp_percent}% untuk ${p.name}.</div>`;
      return;
    }
    const insType = document.querySelector('input[name="cc_ins"]:checked').value;      // tlo | ar
    const addm = document.querySelector('input[name="cc_timing"]:checked').value === 'ADDM';

    const dp = Math.round(state.otr * dpPct / 100);
    const principal = state.otr - dp;
    const interest = principal * (r.interest / 100) * (state.tenor / 12);
    const installment = Math.round((principal + interest) / state.tenor);
    const insurance = Math.round(state.otr * r[insType] / 100);
    const first = addm ? installment : 0;
    const tdp = dp + p.admin_fee + insurance + first;

    $('cc_result').innerHTML = `
      <div class="text-center mb-3">
        <div class="text-secondary small">Estimasi Angsuran / Bulan</div>
        <div class="display-6 fw-bold text-gold">${rp(installment)}</div>
        <div class="small text-secondary">${state.tenor} bulan · bunga flat ${r.interest}% / tahun</div>
      </div>
      <div class="tdp-box">
        <div class="fw-semibold mb-2">Rincian TDP (Total Down Payment)</div>
        <table class="table table-sm mb-0">
          <tr><td>DP Murni (${dpPct}%)</td><td class="text-end">${rp(dp)}</td></tr>
          <tr><td>Biaya Administrasi</td><td class="text-end">${rp(p.admin_fee)}</td></tr>
          <tr><td>Asuransi ${insType.toUpperCase() === 'AR' ? 'All Risk' : 'TLO'} (tahun 1)</td><td class="text-end">${rp(insurance)}</td></tr>
          <tr><td>Angsuran Pertama (${addm ? 'ADDM' : 'ADDB – dibayar di belakang'})</td><td class="text-end">${rp(first)}</td></tr>
          <tr class="fw-bold"><td>TOTAL TDP</td><td class="text-end text-gold">${rp(tdp)}</td></tr>
        </table>
      </div>
      <p class="small text-secondary mt-3 mb-0">* Simulasi bersifat estimasi. Angka final mengikuti persetujuan leasing.</p>`;
  }

  modalEl.addEventListener('show.bs.modal', async (ev) => {
    const b = ev.relatedTarget;
    if (b) {
      state.otr = parseFloat(b.dataset.price);
      $('cc_name').textContent = b.dataset.name;
      $('cc_otr').textContent = rp(state.otr);
      $('cc_quote').href = b.dataset.quoteUrl || '#';
    }
    await loadRates();
    renderTenors();
    calc();
  });

  $('cc_partner').addEventListener('change', () => { renderTenors(); calc(); });
  $('cc_dp').addEventListener('input', calc);
  modalEl.addEventListener('change', (e) => {
    if (e.target.name === 'cc_tenor') state.tenor = parseInt(e.target.value, 10);
    if (['cc_tenor', 'cc_ins', 'cc_timing'].includes(e.target.name)) calc();
  });
})();
