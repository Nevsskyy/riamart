// Tampilkan unggahan bukti hanya ketika pembayaran QRIS dipilih.
const metodeBayar = document.getElementById('metodeBayar');
const bagianQris = document.getElementById('bagianQris');

function tampilkanQris() {
  if (!metodeBayar || !bagianQris) {
    return;
  }
  bagianQris.hidden = metodeBayar.value !== 'QRIS';
  const inputBukti = bagianQris.querySelector('input[type="file"]');
  if (inputBukti) {
    inputBukti.required = metodeBayar.value === 'QRIS';
  }
}

if (metodeBayar) {
  metodeBayar.addEventListener('change', tampilkanQris);
  tampilkanQris();
}

// Konfirmasi sebelum mengarsipkan produk atau menonaktifkan QRIS.
document.querySelectorAll('form[data-konfirmasi]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm(form.dataset.konfirmasi)) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('.form-status').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (form.querySelector('select[name="status"]').value === 'Dibatalkan') {
      const setuju = window.confirm(
        'Batalkan pesanan dan kembalikan stok? Pengembalian uang ditangani toko secara manual.'
      );
      if (!setuju) {
        event.preventDefault();
      }
    }
  });
});
