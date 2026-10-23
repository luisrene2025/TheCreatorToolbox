(function () {
  var C = window.LEGAL || {};
  document.querySelectorAll("[data-v]").forEach(function (e) { var v = C[e.dataset.v]; if (v) e.textContent = v; });
  document.querySelectorAll("[data-if]").forEach(function (e) { if (!C[e.dataset.if]) e.hidden = true; });
  document.querySelectorAll("[data-mail]").forEach(function (a) { if (C.email) { a.href = "mailto:" + C.email; a.textContent = C.email; } });
})();
document.getElementById("y")&&(document.getElementById("y").textContent=new Date().getFullYear());
