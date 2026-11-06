// Landing page: the color dots recolor the page and the sample cassette.
(() => {
  const swatches = [...document.querySelectorAll('.swatch')];
  const cassette = document.querySelector('.cassette');
  swatches.forEach((swatch) => {
    swatch.addEventListener('click', () => {
      document.body.dataset.tape = swatch.dataset.swatch;
      if (cassette) cassette.dataset.tape = swatch.dataset.swatch;
      swatches.forEach((other) => other.setAttribute('aria-pressed', String(other === swatch)));
    });
  });
})();
