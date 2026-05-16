(function () {
  var canvas = document.querySelector('[data-status-particles]');

  if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var context = canvas.getContext('2d');

  if (!context) {
    return;
  }

  var colors = ['#4285f4', '#34a853', '#fbbc05', '#ea4335', '#7c3aed'];
  var particles = [];
  var width = 0;
  var height = 0;
  var pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
  var visible = true;
  var pointer = {
    x: 0,
    y: 0,
    active: false,
    radius: window.innerWidth < 640 ? 110 : 170,
  };

  function particleCount() {
    var base = window.innerWidth < 640 ? 42 : 88;

    return Math.min(120, Math.max(36, Math.round(base * (window.innerWidth / 1440))));
  }

  function createParticle() {
    var size = 1 + Math.random() * 3.6;

    return {
      x: Math.random() * width,
      y: Math.random() * height,
      size: size,
      speed: 0.18 + Math.random() * 0.46,
      drift: -0.18 + Math.random() * 0.36,
      vx: 0,
      vy: 0,
      pull: 0.12 + Math.random() * 0.38,
      rotate: Math.random() * Math.PI,
      spin: -0.012 + Math.random() * 0.024,
      alpha: 0.28 + Math.random() * 0.5,
      color: colors[Math.floor(Math.random() * colors.length)],
    };
  }

  function resize() {
    width = window.innerWidth;
    height = window.innerHeight;
    pointer.radius = window.innerWidth < 640 ? 110 : 170;
    pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.floor(width * pixelRatio);
    canvas.height = Math.floor(height * pixelRatio);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

    var total = particleCount();

    particles = Array.from({ length: total }, createParticle);
  }

  function drawParticle(particle) {
    context.save();
    context.globalAlpha = particle.alpha;
    context.translate(particle.x, particle.y);
    context.rotate(particle.rotate);
    context.fillStyle = particle.color;

    if (typeof context.roundRect === 'function') {
      context.beginPath();
      context.roundRect(-particle.size * 1.6, -particle.size / 2, particle.size * 3.2, particle.size, particle.size / 2);
      context.fill();
    } else {
      context.fillRect(-particle.size * 1.6, -particle.size / 2, particle.size * 3.2, particle.size);
    }

    context.restore();
  }

  function tick() {
    context.clearRect(0, 0, width, height);

    particles.forEach(function (particle) {
      if (pointer.active) {
        var dx = particle.x - pointer.x;
        var dy = particle.y - pointer.y;
        var distance = Math.sqrt(dx * dx + dy * dy) || 1;

        if (distance < pointer.radius) {
          var force = (1 - distance / pointer.radius) * particle.pull;
          particle.vx += (-dx / distance) * force;
          particle.vy += (-dy / distance) * force;
          particle.rotate += force * 0.12;
        }
      }

      particle.vx *= 0.9;
      particle.vy *= 0.9;
      particle.y += particle.speed + particle.vy;
      particle.x += particle.drift + particle.vx;
      particle.rotate += particle.spin;

      if (particle.y > height + 24 || particle.x < -40 || particle.x > width + 40) {
        Object.assign(particle, createParticle(), {
          y: -24,
        });
      }

      drawParticle(particle);
    });

    if (visible) {
      window.requestAnimationFrame(tick);
    }
  }

  document.addEventListener('visibilitychange', function () {
    visible = !document.hidden;

    if (visible) {
      tick();
    }
  });

  window.addEventListener('pointermove', function (event) {
    pointer.x = event.clientX;
    pointer.y = event.clientY;
    pointer.active = true;
  }, { passive: true });

  window.addEventListener('pointerleave', function () {
    pointer.active = false;
  }, { passive: true });

  window.addEventListener('blur', function () {
    pointer.active = false;
  });

  window.addEventListener('resize', resize, { passive: true });

  resize();
  tick();
})();
