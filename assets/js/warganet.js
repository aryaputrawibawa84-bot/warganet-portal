/* WargaNet — interaksi UI (Immersive Dark). Tanpa library tambahan. */
(function () {
  'use strict';

  var d = document;
  var body = d.body;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isPublic = body.classList.contains('wn-public');
  var isPanel = body.classList.contains('wn-panel');

  /* ---------- Toast kecil ---------- */
  function toast(msg, type) {
    var t = d.createElement('div');
    t.className = 'wn-toast';
    t.setAttribute('role', 'status');
    t.innerHTML = '<i class="bi bi-' + (type === 'error' ? 'x-circle' : 'check-circle') + '"></i><span></span>';
    t.lastChild.textContent = msg;
    body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { t.remove(); }, 400);
    }, 2600);
  }

  /* ---------- Dialog konfirmasi (pengganti confirm() bawaan browser) ---------- */
  function confirmDialog(message) {
    return new Promise(function (resolve) {
      var prev = d.activeElement;
      var ov = d.createElement('div');
      ov.className = 'wn-modal';
      ov.innerHTML =
        '<div class="wn-modal-box" role="dialog" aria-modal="true" aria-labelledby="wnTitle">' +
          '<div class="wn-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>' +
          '<h5 id="wnTitle" class="fw-bold mb-2">Konfirmasi tindakan</h5><p></p>' +
          '<div class="wn-modal-actions">' +
            '<button type="button" class="btn btn-outline-secondary" data-r="0">Batal</button>' +
            '<button type="button" class="btn wn-btn-danger" data-r="1">Ya, lanjutkan</button>' +
          '</div>' +
        '</div>';
      ov.querySelector('p').textContent = message;
      body.appendChild(ov);
      requestAnimationFrame(function () { ov.classList.add('show'); });
      ov.querySelector('[data-r="1"]').focus();

      function close(result) {
        d.removeEventListener('keydown', onKey);
        ov.classList.remove('show');
        setTimeout(function () { ov.remove(); }, 220);
        if (prev && prev.focus) prev.focus();
        resolve(result);
      }
      function onKey(e) { if (e.key === 'Escape') close(false); }
      d.addEventListener('keydown', onKey);
      ov.addEventListener('click', function (e) {
        if (e.target === ov) return close(false);
        var b = e.target.closest('[data-r]');
        if (b) close(b.getAttribute('data-r') === '1');
      });
    });
  }

  // Ubah onclick="return confirm('...')" lama menjadi dialog baru, tanpa edit PHP satu per satu
  d.querySelectorAll('[onclick*="confirm("]').forEach(function (el) {
    var m = /confirm\((['"])(.*?)\1\)/.exec(el.getAttribute('onclick'));
    if (m) {
      el.removeAttribute('onclick');
      el.setAttribute('data-confirm', m[2]);
    }
  });
  d.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    e.preventDefault();
    confirmDialog(el.getAttribute('data-confirm')).then(function (ok) {
      if (ok) window.location.href = el.href;
    });
  });

  /* ---------- Navbar: efek saat di-scroll ---------- */
  var nav = d.querySelector('.navbar');
  function onScrollNav() { if (nav) nav.classList.toggle('is-scrolled', window.scrollY > 8); }
  onScrollNav();

  /* ---------- Muncul halus saat di-scroll (reveal) ---------- */
  if ((isPublic || isPanel) && !reduce && 'IntersectionObserver' in window) {
    var targets = d.querySelectorAll('.hero-banner, .about-card, .card-dark, .article-img, .content-text, h4.border-bottom, h2.fw-bold, .mb-4.text-center');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        io.unobserve(el);
        el.classList.add('is-visible');
        // bersihkan atribut agar transisi hover kartu tidak ikut tertunda
        setTimeout(function () {
          el.removeAttribute('data-reveal');
          el.classList.remove('is-visible');
          el.style.removeProperty('--d');
        }, 1000 + (parseInt(el.style.getPropertyValue('--d'), 10) || 0));
      });
    }, { threshold: .12 });
    targets.forEach(function (el) {
      var col = el.parentElement;
      var idx = col && col.parentElement ? Array.prototype.indexOf.call(col.parentElement.children, col) : 0;
      if (el.classList.contains('card-dark')) el.style.setProperty('--d', (idx % 3) * 90 + 'ms');
      el.setAttribute('data-reveal', '');
      io.observe(el);
    });
  }

  /* ---------- Spotlight mengikuti kursor pada kartu berita ---------- */
  if (isPublic) {
    d.querySelectorAll('.card-dark').forEach(function (c) {
      c.addEventListener('pointermove', function (e) {
        var r = c.getBoundingClientRect();
        c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        c.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
    });
  }

  /* ---------- Parallax lembut pada teks hero ---------- */
  var heroContent = d.querySelector('.hero-content');
  function onScrollHero() {
    if (!heroContent || reduce) return;
    var y = Math.min(window.scrollY, 600);
    heroContent.style.transform = 'translate3d(0,' + (y * .12) + 'px,0)';
    heroContent.style.opacity = String(Math.max(0, 1 - y / 700));
  }

  /* ---------- Progress membaca artikel ---------- */
  var article = d.querySelector('.content-text');
  var bar = null;
  if (article) {
    bar = d.createElement('div');
    bar.className = 'wn-progress';
    bar.setAttribute('aria-hidden', 'true');
    body.appendChild(bar);
  }
  function onScrollProgress() {
    if (!bar) return;
    var r = article.getBoundingClientRect();
    var p = (window.innerHeight * .4 - r.top) / r.height;
    bar.style.transform = 'scaleX(' + Math.min(1, Math.max(0, p)) + ')';
  }

  /* ---------- Tombol kembali ke atas ---------- */
  var topBtn = null;
  if (isPublic) {
    topBtn = d.createElement('button');
    topBtn.type = 'button';
    topBtn.className = 'wn-top';
    topBtn.setAttribute('aria-label', 'Kembali ke atas');
    topBtn.innerHTML = '<i class="bi bi-arrow-up"></i>';
    topBtn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
    body.appendChild(topBtn);
  }

  /* ---------- Satu listener scroll (dibatasi requestAnimationFrame) ---------- */
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      onScrollNav();
      onScrollHero();
      onScrollProgress();
      if (topBtn) topBtn.classList.toggle('show', window.scrollY > 500);
      ticking = false;
    });
  }, { passive: true });
  onScrollProgress();

  /* ---------- Tampilkan/sembunyikan kata sandi ---------- */
  d.querySelectorAll('input[type=password]').forEach(function (inp) {
    var g = inp.closest('.input-group');
    if (!g) return;
    var b = d.createElement('button');
    b.type = 'button';
    b.className = 'wn-eye';
    b.setAttribute('aria-label', 'Tampilkan kata sandi');
    b.innerHTML = '<i class="bi bi-eye"></i>';
    g.appendChild(b);
    b.addEventListener('click', function () {
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.firstChild.className = 'bi bi-eye' + (show ? '-slash' : '');
      b.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
  });

    /* ---------- Area unggah gambar: tarik-lepas, pratinjau, ganti & hapus ---------- */
  d.querySelectorAll('input[type=file]').forEach(function (inp) {
    var wrap = d.createElement('div');
    wrap.className = 'wn-drop';
    wrap.innerHTML =
      '<div class="wn-drop-empty">' +
        '<div class="wn-drop-icon"><i class="bi bi-cloud-arrow-up"></i></div>' +
        '<strong>Tarik &amp; lepas gambar di sini</strong>' +
        '<span>atau <u>klik untuk memilih file</u></span>' +
        '<small>JPG / PNG &bull; maksimal 2MB</small>' +
      '</div>' +
      '<div class="wn-drop-preview" hidden>' +
        '<img alt="Pratinjau gambar sampul">' +
        '<span class="wn-drop-ok"><i class="bi bi-check-circle-fill"></i> Siap diunggah</span>' +
        '<div class="wn-drop-bar">' +
          '<div class="wn-drop-meta"><strong></strong><small></small></div>' +
          '<button type="button" class="wn-drop-btn" data-act="ganti"><i class="bi bi-arrow-repeat"></i> Ganti</button>' +
          '<button type="button" class="wn-drop-btn danger" data-act="hapus"><i class="bi bi-trash3"></i> Hapus</button>' +
        '</div>' +
      '</div>';

    // input asli dipindah ke dalam area & ditumpuk transparan di atasnya (klik + drop jalan native)
    inp.parentNode.insertBefore(wrap, inp);
    wrap.insertBefore(inp, wrap.firstChild);
    var hint = wrap.nextElementSibling;
    if (hint && hint.tagName === 'SMALL') hint.hidden = true;   // petunjuk lama sudah ada di dalam area

    var preview = wrap.querySelector('.wn-drop-preview');
    var img = preview.querySelector('img');
    var objectUrl = null;

    function clearFile() {
      inp.value = '';
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      img.removeAttribute('src');
      preview.hidden = true;
      wrap.classList.remove('has-file');
    }

    ['dragenter', 'dragover'].forEach(function (ev) {
      inp.addEventListener(ev, function () { wrap.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      inp.addEventListener(ev, function () { wrap.classList.remove('is-over'); });
    });

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) { clearFile(); return; }
      if (!/^image\/(jpeg|png)$/.test(f.type)) { toast('Format gambar harus JPG atau PNG.', 'error'); clearFile(); return; }
      if (f.size > 2000000) { toast('Ukuran gambar maksimal 2MB.', 'error'); clearFile(); return; }

      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(f);
      var kb = Math.max(1, Math.round(f.size / 1024)) + ' KB';
      var info = preview.querySelector('small');
      info.textContent = kb;
      img.onload = function () { info.textContent = kb + ' \u2022 ' + img.naturalWidth + '\u00D7' + img.naturalHeight + ' px'; };
      img.src = objectUrl;
      preview.querySelector('.wn-drop-meta strong').textContent = f.name;
      preview.hidden = false;
      wrap.classList.add('has-file');
    });

    preview.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]');
      if (!b) return;
      if (b.getAttribute('data-act') === 'ganti') { inp.click(); }
      else { clearFile(); toast('Gambar dibatalkan, silakan pilih ulang.'); }
    });
  });

  
  /* ---------- Penghitung kata + tinggi otomatis pada isi artikel ---------- */
  var ta = d.querySelector('textarea[name=isi_artikel]');
  if (ta) {
    var counter = d.createElement('small');
    counter.className = 'wn-counter';
    ta.insertAdjacentElement('afterend', counter);
    var update = function () {
      var words = (ta.value.trim().match(/\S+/g) || []).length;
      counter.textContent = words + ' kata \u2022 \u00B1' + Math.max(1, Math.ceil(words / 200)) + ' menit baca';
      ta.style.height = 'auto';
      ta.style.height = Math.max(ta.scrollHeight, 240) + 'px';
    };
    ta.addEventListener('input', update);
    update();
  }

  /* ---------- Dashboard: ikon sidebar, ikon brand, avatar pengguna ---------- */
  if (isPanel) {
    var icons = { 'index.php': 'newspaper', 'kategori.php': 'tags', 'pengguna.php': 'people', 'logout.php': 'box-arrow-left' };
    d.querySelectorAll('.list-group-item').forEach(function (a) {
      var file = (a.getAttribute('href') || '').split('?')[0].split('/').pop();
      var ic = a.target === '_blank' ? 'globe2' : icons[file];
      if (!ic || a.querySelector('i')) return;
      var i = d.createElement('i');
      i.className = 'bi bi-' + ic;
      i.setAttribute('aria-hidden', 'true');
      a.insertBefore(i, a.firstChild);
    });

    var brand = d.querySelector('.navbar-brand');
    if (brand && !brand.querySelector('i')) {
      var bi = d.createElement('i');
      bi.className = 'bi bi-activity me-2';
      brand.insertBefore(bi, brand.firstChild);
    }

    var who = d.querySelector('.navbar-text');
    var m = who && /Halo,\s*(.+?)!?\s*$/.exec(who.textContent.trim());
    if (m) {
      var av = d.createElement('span');
      av.className = 'wn-avatar';
      av.textContent = m[1].charAt(0).toUpperCase();
      var nm = d.createElement('span');
      nm.textContent = m[1];
      who.textContent = '';
      who.classList.add('wn-user');
      who.appendChild(av);
      who.appendChild(nm);
    }
  }

  /* ---------- Tombol bagikan di halaman detail artikel ---------- */
  var shareMap = {
    'bi-twitter-x': function (u, t) { return 'https://twitter.com/intent/tweet?text=' + t + '&url=' + u; },
    'bi-facebook': function (u) { return 'https://www.facebook.com/sharer/sharer.php?u=' + u; },
    'bi-whatsapp': function (u, t) { return 'https://wa.me/?text=' + t + '%20' + u; }
  };
  var lastBtn = null;
  Object.keys(shareMap).forEach(function (cls) {
    var icon = d.querySelector('.' + cls);
    var btn = icon && icon.closest('button');
    if (!btn) return;
    lastBtn = btn;
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Bagikan');
    btn.addEventListener('click', function () {
      var u = encodeURIComponent(location.href);
      var t = encodeURIComponent(d.title);
      window.open(shareMap[cls](u, t), '_blank', 'noopener,noreferrer');
    });
  });
  if (lastBtn) {
    var copy = d.createElement('button');
    copy.type = 'button';
    copy.className = 'btn btn-outline-secondary rounded-circle';
    copy.setAttribute('aria-label', 'Salin tautan');
    copy.innerHTML = '<i class="bi bi-link-45deg"></i>';
    copy.addEventListener('click', function () {
      if (!navigator.clipboard) return toast('Browser tidak mendukung salin otomatis.', 'error');
      navigator.clipboard.writeText(location.href).then(
        function () { toast('Tautan berhasil disalin'); },
        function () { toast('Gagal menyalin tautan.', 'error'); }
      );
    });
    lastBtn.parentNode.appendChild(copy);
  }

  /* ---------- Status loading pada tombol submit ----------
     Tombol TIDAK di-disable, supaya nilai name="simpan"/"login" tetap terkirim ke PHP. */
  d.querySelectorAll('form').forEach(function (f) {
    if (f.method !== 'post') return;
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]');
      if (b) setTimeout(function () { b.classList.add('is-loading'); }, 0);
    });
  });
  window.addEventListener('pageshow', function () {
    d.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
  });

  /* ---------- Angka menghitung naik ---------- */
  function countUp(el) {
    var to = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduce || to === 0) { el.textContent = to; return; }
    var t0 = null;
    (function step(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / 1400);
      el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    })(performance.now());
  }
  function watchCounts() {
    var items = d.querySelectorAll('[data-count]');
    if (!('IntersectionObserver' in window)) { items.forEach(countUp); return; }
    var o = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { o.unobserve(e.target); countUp(e.target); } });
    }, { threshold: .4 });
    items.forEach(function (el) { o.observe(el); });
  }

  /* ---------- Dashboard: kartu ringkasan dari isi tabel artikel ---------- */
  if (isPanel) {
    var tbl = d.querySelector('table');
    var rows = tbl ? tbl.querySelectorAll('tbody tr .badge') : [];
    if (tbl && rows.length && tbl.querySelector('th') && /Status/i.test(tbl.textContent)) {
      var c = { all: 0, terbit: 0, pending: 0, lain: 0 };
      rows.forEach(function (b) {
        var s = b.textContent.trim().toLowerCase();
        c.all++;
        if (s === 'terbit') c.terbit++; else if (s === 'pending') c.pending++; else c.lain++;
      });
      var host = tbl.closest('.card');
      if (host) {
        var box = d.createElement('div');
        box.className = 'wn-stats wn-stats--panel';
        [['Total artikel', c.all, 'journal-text'], ['Terbit', c.terbit, 'check2-circle'], ['Menunggu review', c.pending, 'hourglass-split'], ['Draf / ditolak', c.lain, 'file-earmark-x']].forEach(function (x) {
          var s = d.createElement('div');
          s.className = 'wn-stat';
          s.innerHTML = '<i class="bi bi-' + x[2] + '"></i><b data-count="' + x[1] + '">0</b><span></span>';
          s.querySelector('span').textContent = x[0];
          box.appendChild(s);
        });
        host.parentNode.insertBefore(box, host);
      }
    }
  }
  watchCounts();

  window.WN = { toast: toast, confirm: confirmDialog };
})();