/* WargaNet — pengaturan font & ukuran huruf untuk editor artikel (Quill 2).
   Muat SETELAH quill.js dan SEBELUM editor dibuat (new Quill).
   Daftar di bawah harus sama dengan:
     - assets/css/artikel.css & editor.css  (tampilan)
     - sanitize_article_html() di includes/security.php  (daftar kelas yang diizinkan) */
(function () {
  'use strict';
  if (typeof Quill === 'undefined') return;

  // value '' = bawaan (Inter). Nilai lain menjadi kelas ql-font-<value>.
  var FONTS = [
    { value: '', label: 'Inter' },
    { value: 'poppins', label: 'Poppins' },
    { value: 'roboto', label: 'Roboto' },
    { value: 'montserrat', label: 'Montserrat' },
    { value: 'merriweather', label: 'Merriweather' },
    { value: 'lora', label: 'Lora' },
    { value: 'playfair', label: 'Playfair Display' },
    { value: 'serif', label: 'Georgia' },
    { value: 'monospace', label: 'Monospace' }
  ];

  // value '' = ukuran normal artikel. Nilai lain menjadi kelas ql-size-<value>.
  var SIZES = ['12px', '14px', '16px', '', '20px', '24px', '28px', '32px', '36px', '48px'];

  // Ukuran lama tetap dikenali supaya artikel lama tidak kehilangan format saat diedit.
  var LEGACY_SIZES = ['small', 'large', 'huge'];

  var fontWhitelist = FONTS.filter(function (f) { return f.value; }).map(function (f) { return f.value; });
  var sizeWhitelist = SIZES.filter(Boolean).concat(LEGACY_SIZES);

  var Font = Quill.import('formats/font');
  Font.whitelist = fontWhitelist;
  Quill.register(Font, true);

  var Size = Quill.import('formats/size');
  Size.whitelist = sizeWhitelist;
  Quill.register(Size, true);

  function fillSelect(select, items) {
    while (select.firstChild) select.removeChild(select.firstChild);
    items.forEach(function (it) {
      var o = document.createElement('option');
      if (it.value) o.value = it.value;          // tanpa atribut value = pilihan bawaan
      else o.setAttribute('selected', 'selected');
      select.appendChild(o);
    });
  }

  // Isi dropdown toolbar dari daftar di atas (label dibuat oleh editor.css).
  document.querySelectorAll('select.ql-font').forEach(function (s) { fillSelect(s, FONTS); });
  document.querySelectorAll('select.ql-size').forEach(function (s) {
    fillSelect(s, SIZES.map(function (v) { return { value: v }; }));
  });

  window.WNEditor = { fonts: FONTS, sizes: SIZES };
})();
