const phone = "5511994472244";

document.querySelectorAll("[data-flavor]").forEach((link) => {
  const flavor = link.dataset.flavor;
  link.href = `https://wa.me/${phone}?text=${encodeURIComponent(`Oi! Quero pedir o brownie ${flavor}.`)}`;
});
