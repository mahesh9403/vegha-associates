/* VEGHA & ASSOCIATES calc.js
   Client-side financial calculators. Vanilla JS, no libraries.
   Page selects its calculator via <body data-calc="slug">. */
(function () {
  "use strict";

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------------------------------------------------- number formatting - */
  function fmtINR(n, dec) {
    if (!isFinite(n)) return "0";
    return "₹" + Number(n).toLocaleString("en-IN", {
      maximumFractionDigits: dec == null ? 0 : dec,
      minimumFractionDigits: dec == null ? 0 : dec
    });
  }
  function fmtShort(n) {
    var a = Math.abs(n);
    if (a >= 1e7) return "₹" + (n / 1e7).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " Cr";
    if (a >= 1e5) return "₹" + (n / 1e5).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " L";
    return fmtINR(n);
  }
  function fmtPct(n, dec) { return Number(n).toLocaleString("en-IN", { maximumFractionDigits: dec == null ? 2 : dec }) + "%"; }

  /* ------------------------------------------------------------ donut ----- */
  function drawDonut(canvas, parts, centerTop, centerBottom) {
    if (!canvas || !canvas.getContext) return;
    var dpr = window.devicePixelRatio || 1;
    var size = canvas.clientWidth || 260;
    canvas.width = size * dpr; canvas.height = size * dpr;
    var ctx = canvas.getContext("2d");
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, size, size);
    var cx = size / 2, cy = size / 2, r = size / 2 - 10, w = size * 0.13;
    var total = parts.reduce(function (s, p) { return s + Math.max(p.value, 0); }, 0) || 1;
    var a = -Math.PI / 2;
    parts.forEach(function (p) {
      var frac = Math.max(p.value, 0) / total;
      ctx.beginPath();
      ctx.strokeStyle = p.color;
      ctx.lineWidth = w;
      ctx.lineCap = "butt";
      ctx.arc(cx, cy, r - w / 2, a, a + frac * Math.PI * 2);
      ctx.stroke();
      a += frac * Math.PI * 2;
    });
    ctx.fillStyle = "#0a1c38";
    ctx.textAlign = "center";
    ctx.font = "600 " + Math.round(size * 0.055) + "px Inter, sans-serif";
    ctx.fillStyle = "#6d7687";
    ctx.fillText(centerTop || "", cx, cy - size * 0.03);
    ctx.font = "600 " + Math.round(size * 0.085) + "px Inter, sans-serif";
    ctx.fillStyle = "#0a1c38";
    ctx.fillText(centerBottom || "", cx, cy + size * 0.07);
  }

  /* ------------------------------------------------------- field binding -- */
  function getV(id) {
    var el = document.getElementById(id);
    return el ? parseFloat(el.value) || 0 : 0;
  }
  function bindFields(recalc) {
    $$("[data-field]").forEach(function (wrap) {
      var num = $("input[type=number], input[type=text]", wrap);
      var rng = $("input[type=range]", wrap);
      if (num && rng) {
        rng.addEventListener("input", function () { num.value = rng.value; recalc(); });
        num.addEventListener("input", function () {
          var v = parseFloat(num.value);
          if (isFinite(v)) rng.value = Math.min(Math.max(v, parseFloat(rng.min)), parseFloat(rng.max));
          recalc();
        });
        num.addEventListener("blur", function () {
          var v = parseFloat(num.value), mn = parseFloat(num.getAttribute("data-clamp-min")), mx = parseFloat(num.getAttribute("data-clamp-max"));
          if (!isFinite(v)) num.value = rng.value;
          else { if (isFinite(mn) && v < mn) num.value = mn; if (isFinite(mx) && v > mx) num.value = mx; }
          recalc();
        });
      } else if (num) {
        num.addEventListener("input", recalc);
      }
    });
    $$("select[data-recalc], input[type=radio][data-recalc], input[type=checkbox][data-recalc]").forEach(function (el) {
      el.addEventListener("change", recalc);
    });
    window.addEventListener("resize", debounce(recalc, 150));
  }
  function debounce(fn, ms) {
    var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); };
  }
  function out(id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; }

  /* ------------------------------------------------------------- math ----- */
  function emi(P, annualRate, months) {
    var r = annualRate / 1200;
    if (r === 0) return P / months;
    var f = Math.pow(1 + r, months);
    return P * r * f / (f - 1);
  }
  function amortYears(P, annualRate, months, extraMonthly, oneTime, oneTimeMonth) {
    /* returns {rows:[{y, principal, interest, prepay, balance}], totalInterest, monthsTaken} */
    var r = annualRate / 1200, e = emi(P, annualRate, months);
    var bal = P, ti = 0, m = 0, rows = [], y = { p: 0, i: 0, x: 0 };
    var thisYear = new Date().getFullYear();
    while (bal > 0.5 && m < 1200) {
      m++;
      var interest = bal * r;
      var principal = Math.min(e - interest, bal);
      bal -= principal;
      var extra = 0;
      if (extraMonthly && bal > 0) { extra += Math.min(extraMonthly, bal); bal -= Math.min(extraMonthly, bal); }
      if (oneTime && m === (oneTimeMonth || 12) && bal > 0) { extra += Math.min(oneTime, bal); bal -= Math.min(oneTime, bal); }
      ti += interest; y.p += principal + extra; y.i += interest; y.x += extra;
      if (m % 12 === 0 || bal <= 0.5) {
        rows.push({ y: thisYear + Math.floor((m - 1) / 12), principal: y.p, interest: y.i, prepay: y.x, balance: Math.max(bal, 0) });
        y = { p: 0, i: 0, x: 0 };
      }
    }
    return { rows: rows, totalInterest: ti, monthsTaken: m, emi: e };
  }
  function fmtMonths(m) {
    var yr = Math.floor(m / 12), mo = m % 12, s = [];
    if (yr) s.push(yr + (yr === 1 ? " year" : " years"));
    if (mo) s.push(mo + (mo === 1 ? " month" : " months"));
    return s.join(" ") || "0 months";
  }
  function scheduleTable(el, rows, withPrepay) {
    if (!el) return;
    var maxTotal = Math.max.apply(null, rows.map(function (r) { return r.principal + r.interest; }));
    var h = '<table class="sched"><thead><tr><th>Year</th><th>Principal paid</th><th>Interest paid</th>' +
            (withPrepay ? "<th>Prepaid</th>" : "") +
            '<th>Balance</th><th class="sched__bar-h">Principal vs interest</th></tr></thead><tbody>';
    rows.forEach(function (r) {
      var tot = r.principal + r.interest;
      var pw = maxTotal ? (r.principal / maxTotal) * 100 : 0;
      var iw = maxTotal ? (r.interest / maxTotal) * 100 : 0;
      h += "<tr><td>" + r.y + "</td><td>" + fmtINR(r.principal) + "</td><td>" + fmtINR(r.interest) + "</td>" +
           (withPrepay ? "<td>" + (r.prepay ? fmtINR(r.prepay) : "—".replace("—", "-")) + "</td>" : "") +
           "<td>" + fmtINR(r.balance) + "</td>" +
           '<td class="sched__bar"><span style="width:' + pw + '%"></span><i style="width:' + iw + '%"></i></td></tr>';
    });
    el.innerHTML = h + "</tbody></table>";
  }

  /* =========================================================== calculators = */
  var CALCS = {};

  /* ---- loan EMI (home + car share this) ---- */
  function loanCalc() {
    var P = getV("amount"), rate = getV("rate"), years = getV("tenure");
    var months = Math.max(Math.round(years * 12), 1);
    var res = amortYears(P, rate, months, 0, 0, 0);
    out("r-emi", fmtINR(res.emi));
    out("r-interest", fmtShort(res.totalInterest));
    out("r-total", fmtShort(P + res.totalInterest));
    out("r-principal", fmtShort(P));
    drawDonut($("#donut"), [
      { value: P, color: "#0f2a52" },
      { value: res.totalInterest, color: "#c29a4e" }
    ], "Monthly EMI", fmtINR(res.emi));
    scheduleTable($("#schedule"), res.rows, false);
  }
  CALCS["home-loan-emi"] = loanCalc;
  CALCS["car-loan-emi"] = loanCalc;

  /* ---- prepayment ---- */
  CALCS["home-loan-prepayment"] = function () {
    var P = getV("amount"), rate = getV("rate"), years = getV("tenure");
    var extra = getV("extra"), once = getV("onetime");
    var months = Math.max(Math.round(years * 12), 1);
    var base = amortYears(P, rate, months, 0, 0, 0);
    var pre = amortYears(P, rate, months, extra, once, 12);
    out("r-emi", fmtINR(base.emi) + (extra ? " + " + fmtINR(extra) : ""));
    out("r-base-int", fmtShort(base.totalInterest));
    out("r-new-int", fmtShort(pre.totalInterest));
    out("r-saved", fmtShort(base.totalInterest - pre.totalInterest));
    out("r-tenure-cut", fmtMonths(base.monthsTaken - pre.monthsTaken));
    out("r-new-tenure", fmtMonths(pre.monthsTaken));
    drawDonut($("#donut"), [
      { value: pre.totalInterest, color: "#0f2a52" },
      { value: base.totalInterest - pre.totalInterest, color: "#c29a4e" }
    ], "Interest saved", fmtShort(base.totalInterest - pre.totalInterest));
    scheduleTable($("#schedule"), pre.rows, true);
  };

  /* ---- SIP ---- */
  CALCS["sip"] = function () {
    var p = getV("amount"), annual = getV("rate"), years = getV("tenure");
    var i = annual / 1200, n = Math.round(years * 12);
    var fv = i === 0 ? p * n : p * ((Math.pow(1 + i, n) - 1) / i) * (1 + i);
    var invested = p * n;
    out("r-maturity", fmtShort(fv));
    out("r-invested", fmtShort(invested));
    out("r-gain", fmtShort(fv - invested));
    drawDonut($("#donut"), [
      { value: invested, color: "#0f2a52" },
      { value: fv - invested, color: "#c29a4e" }
    ], "Maturity value", fmtShort(fv));
  };

  /* ---- lumpsum ---- */
  CALCS["lumpsum"] = function () {
    var P = getV("amount"), annual = getV("rate"), years = getV("tenure");
    var fv = P * Math.pow(1 + annual / 100, years);
    out("r-maturity", fmtShort(fv));
    out("r-invested", fmtShort(P));
    out("r-gain", fmtShort(fv - P));
    drawDonut($("#donut"), [
      { value: P, color: "#0f2a52" },
      { value: fv - P, color: "#c29a4e" }
    ], "Maturity value", fmtShort(fv));
  };

  /* ---- mutual fund returns (CAGR) ---- */
  CALCS["mutual-fund-returns"] = function () {
    var I = getV("initial"), F = getV("final"), years = getV("tenure");
    var abs = I > 0 ? ((F - I) / I) * 100 : 0;
    var cagr = (I > 0 && years > 0) ? (Math.pow(F / I, 1 / years) - 1) * 100 : 0;
    out("r-cagr", fmtPct(cagr));
    out("r-abs", fmtPct(abs));
    out("r-gain", fmtShort(F - I));
    drawDonut($("#donut"), [
      { value: I, color: "#0f2a52" },
      { value: Math.max(F - I, 0), color: "#c29a4e" }
    ], "CAGR", fmtPct(cagr));
  };

  /* ---- FD (quarterly compounding) ---- */
  CALCS["fd"] = function () {
    var P = getV("amount"), rate = getV("rate"), years = getV("tenure");
    var fv = P * Math.pow(1 + rate / 400, 4 * years);
    out("r-maturity", fmtShort(fv));
    out("r-invested", fmtShort(P));
    out("r-gain", fmtShort(fv - P));
    drawDonut($("#donut"), [
      { value: P, color: "#0f2a52" },
      { value: fv - P, color: "#c29a4e" }
    ], "Maturity value", fmtShort(fv));
  };

  /* ---- PPF (annual deposit, start of year) ---- */
  CALCS["ppf"] = function () {
    var dep = getV("amount"), rate = getV("rate"), years = getV("tenure");
    var bal = 0, invested = 0, rows = [], thisYear = new Date().getFullYear();
    for (var y = 1; y <= years; y++) {
      bal = (bal + dep) * (1 + rate / 100);
      invested += dep;
      rows.push({ y: thisYear + y - 1, principal: invested, interest: bal - invested, prepay: 0, balance: bal });
    }
    out("r-maturity", fmtShort(bal));
    out("r-invested", fmtShort(invested));
    out("r-gain", fmtShort(bal - invested));
    drawDonut($("#donut"), [
      { value: invested, color: "#0f2a52" },
      { value: bal - invested, color: "#c29a4e" }
    ], "Maturity value", fmtShort(bal));
    var el = $("#schedule");
    if (el) {
      var h = '<table class="sched"><thead><tr><th>Year</th><th>Total deposited</th><th>Interest earned</th><th>Balance</th></tr></thead><tbody>';
      rows.forEach(function (r) {
        h += "<tr><td>" + r.y + "</td><td>" + fmtINR(r.principal) + "</td><td>" + fmtINR(r.interest) + "</td><td>" + fmtINR(r.balance) + "</td></tr>";
      });
      el.innerHTML = h + "</tbody></table>";
    }
  };

  /* ---- GST ---- */
  CALCS["gst"] = function () {
    var amt = getV("amount"), rate = getV("rate");
    var mode = ($('input[name="mode"]:checked') || {}).value || "add";
    var base, gst, total;
    if (mode === "add") { base = amt; gst = amt * rate / 100; total = amt + gst; }
    else { total = amt; base = amt / (1 + rate / 100); gst = total - base; }
    out("r-base", fmtINR(base, 2));
    out("r-gst", fmtINR(gst, 2));
    out("r-cgst", fmtINR(gst / 2, 2));
    out("r-total", fmtINR(total, 2));
    drawDonut($("#donut"), [
      { value: base, color: "#0f2a52" },
      { value: gst, color: "#c29a4e" }
    ], mode === "add" ? "Invoice total" : "Base amount", mode === "add" ? fmtINR(total, 0) : fmtINR(base, 0));
  };

  /* ---- HRA ---- */
  CALCS["hra"] = function () {
    var basic = getV("basic"), hra = getV("hra"), rent = getV("rent");
    var metro = ($('input[name="metro"]:checked') || {}).value === "yes";
    var a = hra;
    var b = Math.max(rent - basic * 0.10, 0);
    var c = basic * (metro ? 0.50 : 0.40);
    var exempt = Math.max(Math.min(a, b, c), 0);
    var taxable = Math.max(hra - exempt, 0);
    out("r-exempt", fmtINR(exempt));
    out("r-taxable", fmtINR(taxable));
    out("r-a", fmtINR(a)); out("r-b", fmtINR(b)); out("r-c", fmtINR(c));
    drawDonut($("#donut"), [
      { value: exempt, color: "#0f2a52" },
      { value: taxable, color: "#c29a4e" }
    ], "HRA exempt", fmtShort(exempt));
  };

  /* ---- income tax: old vs new regime ---- */
  /* Rates verified Aug 2026: identical for FY 2025-26 and FY 2026-27
     (Budget 2026 made no slab changes). One edit here updates the year. */
  var TAX = {
    fyLabel: "FY 2025-26 and FY 2026-27",
    newRegime: {
      stdDeduction: 75000,
      slabs: [[400000, 0], [800000, .05], [1200000, .10], [1600000, .15], [2000000, .20], [2400000, .25], [Infinity, .30]],
      rebateLimit: 1200000, rebateMax: 60000, surchargeCap: .25
    },
    oldRegime: {
      stdDeduction: 50000,
      slabsBelow60: [[250000, 0], [500000, .05], [1000000, .20], [Infinity, .30]],
      slabsSenior:  [[300000, 0], [500000, .05], [1000000, .20], [Infinity, .30]],
      slabsSuper:   [[500000, 0], [1000000, .20], [Infinity, .30]],
      rebateLimit: 500000, rebateMax: 12500, surchargeCap: .37
    },
    surchargeTiers: [[5000000, .10], [10000000, .15], [20000000, .25], [50000000, .37]],
    cess: .04
  };
  function slabTax(income, slabs) {
    var tax = 0, prev = 0;
    for (var k = 0; k < slabs.length; k++) {
      var cap = slabs[k][0], rate = slabs[k][1];
      if (income > prev) tax += (Math.min(income, cap) - prev) * rate;
      prev = cap;
      if (income <= cap) break;
    }
    return tax;
  }
  /* base tax after the 87A rebate (incl. new-regime marginal relief) */
  function baseTax(taxable, slabs, cfg) {
    var tax = slabTax(taxable, slabs);
    if (taxable <= cfg.rebateLimit) return Math.max(tax - Math.min(tax, cfg.rebateMax), 0);
    if (cfg.rebateMax >= 60000) {
      /* new regime: marginal relief just above the rebate limit
         (pay no more than the income above the limit; binds till ~12,70,588) */
      return Math.min(tax, taxable - cfg.rebateLimit);
    }
    return tax; /* old regime has no relief above the 87A limit */
  }
  function surchargeRate(taxable, cap) {
    var rate = 0;
    TAX.surchargeTiers.forEach(function (t) { if (taxable > t[0]) rate = t[1]; });
    return Math.min(rate, cap || 1);
  }
  /* tax + surcharge with marginal relief at each surcharge threshold */
  function taxPlusSurcharge(taxable, slabs, cfg) {
    var t = baseTax(taxable, slabs, cfg);
    var gross = t * (1 + surchargeRate(taxable, cfg.surchargeCap));
    var th = null;
    TAX.surchargeTiers.forEach(function (tier) { if (taxable > tier[0]) th = tier[0]; });
    if (th) {
      var atTh = baseTax(th, slabs, cfg) * (1 + surchargeRate(th, cfg.surchargeCap));
      gross = Math.min(gross, atTh + (taxable - th));
    }
    return gross;
  }
  function computeRegime(gross, otherIncome, deductions, cfg, slabs, salaried) {
    var std = salaried ? cfg.stdDeduction : 0;
    var taxable = Math.max(gross - std + otherIncome - (deductions || 0), 0);
    var withS = taxPlusSurcharge(taxable, slabs, cfg);
    var cess = withS * TAX.cess;
    return { taxable: taxable, total: Math.round(withS + cess) };
  }
  CALCS["income-tax"] = function () {
    var gross = getV("salary"), other = getV("other");
    var age = ($("#age") || {}).value || "below60";
    var salaried = ($('input[name="salaried"]:checked') || {}).value !== "no";
    var dOld = Math.min(getV("d80c"), 150000) + Math.min(getV("dnps"), 50000)
             + Math.min(getV("d24b"), 200000) + Math.min(getV("d80d"), 100000)
             + getV("dhra") + getV("dother");
    var oldSlabs = age === "super" ? TAX.oldRegime.slabsSuper : age === "senior" ? TAX.oldRegime.slabsSenior : TAX.oldRegime.slabsBelow60;
    var oldR = computeRegime(gross, other, dOld, TAX.oldRegime, oldSlabs, salaried);
    var newR = computeRegime(gross, other, 0, TAX.newRegime, TAX.newRegime.slabs, salaried);
    out("r-old-taxable", fmtINR(oldR.taxable));
    out("r-old-tax", fmtINR(oldR.total));
    out("r-new-taxable", fmtINR(newR.taxable));
    out("r-new-tax", fmtINR(newR.total));
    var diff = oldR.total - newR.total;
    var verdict = $("#r-verdict");
    if (verdict) {
      if (Math.abs(diff) < 1) verdict.textContent = "Both regimes result in the same tax for these inputs.";
      else verdict.textContent = (diff > 0 ? "The NEW regime saves you " : "The OLD regime saves you ") + fmtINR(Math.abs(diff)) + " for these inputs.";
      verdict.className = "calc-verdict " + (diff > 0 ? "is-new" : "is-old");
    }
    drawDonut($("#donut"), [
      { value: Math.min(oldR.total, newR.total), color: "#0f2a52" },
      { value: Math.abs(diff), color: "#c29a4e" }
    ], "You could save", fmtShort(Math.abs(diff)));
  };

  /* ------------------------------------------------------------- boot ----- */
  var slug = document.body.getAttribute("data-calc");
  if (slug && CALCS[slug]) {
    var recalc = debounce(CALCS[slug], 30);
    bindFields(recalc);
    CALCS[slug]();
  }
})();
