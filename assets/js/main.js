(() => {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('nav');

  // Header turns solid after the hero
  const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 40);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  // Mobile menu
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  nav.addEventListener('click', (event) => {
    if (event.target.closest('a')) {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Scroll reveal
  const items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    items.forEach((el, i) => {
      el.style.transitionDelay = `${(i % 4) * 80}ms`;
      io.observe(el);
    });
  } else {
    items.forEach((el) => el.classList.add('in'));
  }

  // Shop: category filter
  const filters = document.querySelectorAll('.filter');
  const products = document.querySelectorAll('.product');
  filters.forEach((btn) => {
    btn.addEventListener('click', () => {
      filters.forEach((b) => {
        b.classList.toggle('is-active', b === btn);
        b.setAttribute('aria-pressed', String(b === btn));
      });
      products.forEach((card) => {
        card.hidden = btn.dataset.filter !== 'all' && card.dataset.category !== btn.dataset.filter;
      });
    });
  });

  // Shop: quantity stepper keeps the WhatsApp message in sync
  document.querySelectorAll('.p-buy').forEach((box) => {
    const out = box.querySelector('output');
    const buy = box.querySelector('.buy');
    let qty = 1;

    const sync = () => {
      out.textContent = String(qty);
      if (!buy.dataset.wa) return;
      const text = `Hi Svayamev, I would like to know the price of:\n\n• ${buy.dataset.name} × ${qty}\n\nPlease share the price and delivery details.`;
      buy.href = `https://wa.me/${encodeURIComponent(buy.dataset.wa)}?text=${encodeURIComponent(text)}`;
    };

    box.querySelectorAll('[data-step]').forEach((btn) => {
      btn.addEventListener('click', () => {
        qty = Math.min(20, Math.max(1, qty + Number(btn.dataset.step)));
        sync();
      });
    });
  });

  // Gallery lightbox
  const box = document.getElementById('lightbox');
  const boxImg = box.querySelector('img');
  const boxCap = box.querySelector('figcaption');
  let lastFocus = null;

  const close = () => {
    box.hidden = true;
    document.body.classList.remove('no-scroll');
    boxImg.src = '';
    if (lastFocus) lastFocus.focus();
  };

  document.querySelectorAll('.thumb').forEach((btn) => {
    btn.addEventListener('click', () => {
      lastFocus = btn;
      boxImg.src = btn.dataset.full;
      boxImg.alt = btn.dataset.caption || 'Gallery photo';
      boxCap.textContent = btn.dataset.caption || '';
      box.hidden = false;
      document.body.classList.add('no-scroll');
      box.querySelector('.lb-close').focus();
    });
  });

  box.addEventListener('click', (event) => {
    if (event.target === box || event.target.closest('.lb-close')) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !box.hidden) close();
  });
})();
