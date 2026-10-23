(function () {
  var btn = document.querySelector('.menu-btn'), nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', open);
    });
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }
  var show = document.getElementById('showpw'), pw = document.getElementById('pw');
  if (show && pw) show.addEventListener('change', function () { pw.type = show.checked ? 'text' : 'password'; });
})();

// Filtro del listado de alumnado del dashboard
(function () {
  var campo = document.getElementById('buscar-alumno');
  if (!campo) return;
  campo.addEventListener('input', function () {
    var q = campo.value.toLowerCase().trim();
    var filas = document.querySelectorAll('#tabla-alumnos tbody tr');
    for (var i = 0; i < filas.length; i++) {
      filas[i].style.display = filas[i].textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
    }
  });
})();

// Noticias y mensajes del formulario (index.html es estático)
(function () {
  var q = new URLSearchParams(location.search);
  var ok = document.getElementById('msg-ok'), er = document.getElementById('msg-error');
  if (ok && q.has('ok')) ok.hidden = false;
  if (er && q.has('error')) er.hidden = false;

  var box = document.getElementById('noticias-lista');
  if (!box) return;
  fetch('api_noticias.php').then(function (r) { return r.json(); }).then(function (items) {
    box.textContent = '';
    if (!items.length) { box.innerHTML = '<p class="muted">Pronto publicaremos novedades.</p>'; return; }
    items.forEach(function (n) {
      var a = document.createElement('article'); a.className = 'card news';
      var tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = n.categoria;
      var h = document.createElement('h3'); h.textContent = n.titulo;
      var p = document.createElement('p'); p.textContent = n.resumen;
      var t = document.createElement('time'); t.textContent = new Date(n.publicado.replace(' ', 'T')).toLocaleDateString('es-PE');
      a.append(tag, h, p, t); box.appendChild(a);
    });
  }).catch(function () { box.innerHTML = '<p class="muted">Pronto publicaremos novedades.</p>'; });
})();

// Modo oscuro / claro
(function () {
  var root = document.documentElement, btns = document.querySelectorAll('.theme-btn');
  function aplicar(t) {
    root.setAttribute('data-tema', t);
    for (var i = 0; i < btns.length; i++) {
      btns[i].textContent = t === 'oscuro' ? '☀️' : '🌙';
      btns[i].setAttribute('aria-label', t === 'oscuro' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
      btns[i].setAttribute('aria-pressed', t === 'oscuro');
    }
  }
  var guardado = null;
  try { guardado = localStorage.getItem('tema'); } catch (e) {}
  aplicar(guardado || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro'));
  function reducido() { return window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function cambiar(btn, t) {
    try {
      var r = btn.getBoundingClientRect();
      root.style.setProperty('--tx', (r.left + r.width / 2) + 'px');
      root.style.setProperty('--ty', (r.top + r.height / 2) + 'px');
    } catch (e) {}
    btn.classList.add('girando');
    setTimeout(function () { btn.classList.remove('girando'); }, 520);
    if (document.startViewTransition && !reducido()) {
      document.startViewTransition(function () { aplicar(t); });
    } else {
      aplicar(t);
    }
    try { localStorage.setItem('tema', t); } catch (e) {}
  }
  for (var i = 0; i < btns.length; i++) {
    btns[i].addEventListener('click', function (e) {
      var t = root.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro';
      cambiar(e.currentTarget, t);
    });
  }
})();

// Modal de inicio de sesión sobre la portada
(function () {
  var modal = document.getElementById('modal-login');
  if (!modal) return;
  var form = document.getElementById('form-login');
  var errorBox = document.getElementById('login-error');
  var tokenField = form.querySelector('input[name="csrf"]');
  var usuario = form.querySelector('input[name="usuario"]');
  var token = '';
  var ultimoFoco = null;

  function cargarToken() {
    return fetch('csrf.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { token = d.csrf || ''; if (tokenField) tokenField.value = token; })
      .catch(function () {});
  }

  function abrir(e) {
    if (e) {
      if (e.button && e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
    }
    ultimoFoco = document.activeElement;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
    if (!token) cargarToken();
    if (usuario) setTimeout(function () { usuario.focus(); }, 140);
  }

  function cerrar() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-login]'), function (el) {
    el.addEventListener('click', abrir);
  });
  Array.prototype.forEach.call(modal.querySelectorAll('[data-close]'), function (el) {
    el.addEventListener('click', cerrar);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('open')) cerrar();
  });

  var ver = document.getElementById('showpw-modal');
  var pw = document.getElementById('pw-modal');
  if (ver && pw) ver.addEventListener('change', function () { pw.type = ver.checked ? 'text' : 'password'; });

  function mostrarError(msg) {
    if (!errorBox) return;
    errorBox.textContent = msg;
    errorBox.hidden = false;
  }

  function enviar() {
    fetch('login.php', {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        if (res.d && res.d.ok) { window.location.href = res.d.redirect || 'panel.php'; return; }
        mostrarError((res.d && res.d.error) || 'No se pudo iniciar sesión.');
        if (res.d && res.d.csrf) { token = res.d.csrf; tokenField.value = token; }
      })
      .catch(function () { mostrarError('Error de conexión. Inténtalo de nuevo.'); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (errorBox) errorBox.hidden = true;
    if (!token) { cargarToken().then(enviar); } else { enviar(); }
  });

  cargarToken();
})();
