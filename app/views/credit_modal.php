<!-- Modal Simulasi Kredit & TDP -->
<div class="modal fade" id="creditModal" tabindex="-1" aria-hidden="true" data-rates-url="<?= e(url('api/rates.php')) ?>">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="background:var(--card-bg)">
      <div class="modal-header">
        <div><h5 class="modal-title">Simulasi Kredit</h5><div class="small text-secondary"><span id="cc_name"></span> · OTR <strong id="cc_otr" class="text-gold"></strong></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-5">
            <div class="mb-3"><label class="form-label">Mitra Leasing</label><select id="cc_partner" class="form-select"></select></div>
            <div class="mb-3">
              <label class="form-label">Uang Muka (DP) %  <small class="text-secondary">min <span id="cc_dpmin">20</span>%</small></label>
              <input type="number" id="cc_dp" class="form-control" value="25" min="10" max="90" step="1">
            </div>
            <div class="mb-3"><label class="form-label d-block">Tenor</label><div id="cc_tenors" class="d-flex flex-wrap gap-1"></div></div>
            <div class="mb-3"><label class="form-label d-block">Asuransi</label>
              <div class="btn-group w-100">
                <input type="radio" class="btn-check" name="cc_ins" id="ins_tlo" value="tlo" checked><label class="btn btn-outline-gold btn-sm" for="ins_tlo">TLO</label>
                <input type="radio" class="btn-check" name="cc_ins" id="ins_ar" value="ar"><label class="btn btn-outline-gold btn-sm" for="ins_ar">All Risk</label>
              </div></div>
            <div class="mb-1"><label class="form-label d-block">Angsuran Pertama</label>
              <div class="btn-group w-100">
                <input type="radio" class="btn-check" name="cc_timing" id="tm_addm" value="ADDM" checked><label class="btn btn-outline-gold btn-sm" for="tm_addm">ADDM</label>
                <input type="radio" class="btn-check" name="cc_timing" id="tm_addb" value="ADDB"><label class="btn btn-outline-gold btn-sm" for="tm_addb">ADDB</label>
              </div></div>
          </div>
          <div class="col-md-7" id="cc_result"><div class="text-secondary">Memuat…</div></div>
        </div>
      </div>
      <div class="modal-footer"><a id="cc_quote" href="#" class="btn btn-gold">Minta Penawaran Resmi</a></div>
    </div>
  </div>
</div>
