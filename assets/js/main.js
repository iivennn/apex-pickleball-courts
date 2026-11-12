// Apex Pickleball Courts — small enhancements (the site also works without JavaScript)
(function () {
  // Mobile menu
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Booking page: live summary + max hours
  var form = document.getElementById('booking-form');
  if (!form) return;

  var rate = parseFloat(form.dataset.rate || '0');
  var max = parseInt(form.dataset.max || '4', 10);
  var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="slots[]"]'));
  var sumTime = document.getElementById('sum-time');
  var sumHours = document.getElementById('sum-hours');
  var sumTotal = document.getElementById('sum-total');
  var peso = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });

  function label(h) {
    var suffix = (h % 24) >= 12 ? 'PM' : 'AM';
    var hr = h % 12 === 0 ? 12 : h % 12;
    return hr + ':00 ' + suffix;
  }

  function update() {
    var hours = boxes.filter(function (b) { return b.checked; })
      .map(function (b) { return parseInt(b.dataset.hour, 10); })
      .sort(function (a, b) { return a - b; });

    var ranges = [];
    hours.forEach(function (h) {
      var last = ranges[ranges.length - 1];
      if (last && last[1] === h) { last[1] = h + 1; } else { ranges.push([h, h + 1]); }
    });
    sumTime.textContent = ranges.length
      ? ranges.map(function (r) { return label(r[0]) + ' – ' + label(r[1]); }).join(', ')
      : 'Pick a time';
    sumHours.textContent = hours.length;
    sumTotal.textContent = '₱' + peso.format(hours.length * rate);
  }

  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      var count = boxes.filter(function (x) { return x.checked; }).length;
      if (b.checked && count > max) {
        b.checked = false;
        alert('You can book up to ' + max + ' hours at a time.');
      }
      update();
    });
  });
  form.addEventListener('submit', function (ev) {
    if (!boxes.some(function (b) { return b.checked; })) {
      ev.preventDefault();
      alert('Please choose at least one time slot.');
    }
  });
  update();
})();
